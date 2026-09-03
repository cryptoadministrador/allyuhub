<?php

namespace Tests\Feature;

use App\Jobs\PushLtiScore;
use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\LtiPlatform;
use App\Models\LtiResourceLink;
use App\Models\ObjectiveMastery;
use App\Models\PracticeAttempt;
use App\Models\PracticeItem;
use App\Models\User;
use App\Services\Practice\RepasoService;
use Database\Seeders\CefrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * PR 2 · LA MEMORIA (repaso espaciado).
 *
 * Sin esto un alumno aprende las 30 palabras de la U1 y las ha perdido en la
 * U4. Tres decisiones tomadas y fijadas aquí:
 *
 *  1. SE REPASA EL DESCRIPTOR, no el ítem: otro ítem del mismo descriptor. Un
 *     descriptor con un solo ítem no entra en la cola (no hay «otro»).
 *  2. EL REPASO NO CUENTA PARA LA NOTA (AGS), sí para el dominio: una nota que
 *     sube repasando lo sabido es una nota inflada. El flag va en el BILLETE
 *     firmado — forjarlo para inflar la nota es justo lo que se impide.
 *  3. TECHO POR SESIÓN: 12 repasos. Una cola de 200 el lunes se abandona.
 *
 * Algoritmo, el mínimo que funciona: intervalo ×2 con el acierto (1,2,4,8,16,
 * 32 días), vuelve a 1 con el fallo. Sobre `ObjectiveMastery`.
 */
class RepasoTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CefrSeeder::class);
        $this->ana = User::factory()->create();
    }

    private function itemDe(string $code, string $lengua, int $seq): PracticeItem
    {
        return PracticeItem::create([
            'objective_id' => LearningObjective::where('native_code', $code)->firstOrFail()->id,
            'kind' => 'hueco', 'lengua' => $lengua,
            'statement' => ['es' => "Completa ({$lengua})."], 'params' => [],
            'solucion' => ['lengua' => $lengua, 'textos' => ['x']],
            'seq' => $seq, 'reviewed_at' => now(),
        ]);
    }

    // ================= el algoritmo, determinista =================

    public function test_el_intervalo_duplica_con_el_acierto_y_vuelve_a_uno_con_el_fallo(): void
    {
        $repaso = new RepasoService;
        $obj = LearningObjective::where('native_code', 'A1.CO.2')->firstOrFail()->id;

        // 1 → 2 → 4 → 8 con tres aciertos seguidos.
        $esperados = [1, 2, 4, 8];
        foreach ([true, true, true] as $i => $_) {
            $repaso->programar($this->ana->id, $obj, acierto: true);
            $m = ObjectiveMastery::where('user_id', $this->ana->id)->where('objective_id', $obj)->firstOrFail();
            // repaso_en cae dentro del intervalo servido.
            $this->assertEqualsWithDelta(
                now()->addDays($esperados[$i])->timestamp,
                $m->repaso_en->timestamp, 5,
                "Tras {$i} aciertos el repaso no cayó a los {$esperados[$i]} días.",
            );
        }

        // El fallo lo desploma a 1 día.
        $repaso->programar($this->ana->id, $obj, acierto: false);
        $m = ObjectiveMastery::where('user_id', $this->ana->id)->where('objective_id', $obj)->firstOrFail();
        $this->assertEqualsWithDelta(now()->addDay()->timestamp, $m->repaso_en->timestamp, 5,
            'El fallo no reinició el intervalo a un día.');

        // Y el fallo reinició el INTERVALO GUARDADO a 1, no solo el `repaso_en`
        // de esta vez: el siguiente acierto vuelve a caer a un día (usa el 1),
        // no a ocho. Sin esto, «vuelve a 1» pasa aunque el intervalo se quede
        // en 8 —el `repaso_en` de un fallo es +1 día lo reinicie o no—.
        $repaso->programar($this->ana->id, $obj, acierto: true);
        $m = ObjectiveMastery::where('user_id', $this->ana->id)->where('objective_id', $obj)->firstOrFail();
        $this->assertEqualsWithDelta(now()->addDay()->timestamp, $m->repaso_en->timestamp, 5,
            'Tras el fallo el intervalo no había vuelto a 1: el acierto siguiente no cayó a un día.');
    }

    public function test_el_intervalo_no_pasa_de_treinta_y_dos_dias(): void
    {
        $repaso = new RepasoService;
        $obj = LearningObjective::where('native_code', 'A1.CO.2')->firstOrFail()->id;

        foreach (range(1, 10) as $_) {
            $repaso->programar($this->ana->id, $obj, acierto: true);
        }
        $m = ObjectiveMastery::where('user_id', $this->ana->id)->where('objective_id', $obj)->firstOrFail();

        $this->assertLessThanOrEqual(now()->addDays(32)->timestamp + 5, $m->repaso_en->timestamp,
            'El intervalo pasó del techo de 32 días.');
    }

    // ================= la cola =================

    public function test_un_descriptor_con_un_solo_item_no_entra_en_la_cola(): void
    {
        $repaso = new RepasoService;

        // Descriptor A: DOS ítems, vencido → entra.
        $this->itemDe('A1.CO.2', 'it', 1);
        $this->itemDe('A1.CO.2', 'it', 2);
        $a = LearningObjective::where('native_code', 'A1.CO.2')->firstOrFail()->id;

        // Descriptor B: UN ítem, vencido → NO entra (no hay «otro» que servir).
        $this->itemDe('A1.IO.3', 'it', 1);
        $b = LearningObjective::where('native_code', 'A1.IO.3')->firstOrFail()->id;

        foreach ([$a, $b] as $obj) {
            ObjectiveMastery::create([
                'user_id' => $this->ana->id, 'objective_id' => $obj,
                'mastery' => 0.8, 'streak' => 2, 'attempts_count' => 2,
                'repaso_intervalo' => 2, 'repaso_en' => now()->subDay(), 'last_attempt_at' => now()->subDay(),
            ]);
        }

        $cola = $repaso->cola($this->ana->id, 'it');
        $this->assertSame(1, $cola['pendientes'], 'El descriptor de un solo ítem entró en la cola.');
        $this->assertSame($a, $cola['siguiente']['descriptor_id']);
    }

    public function test_la_cola_tiene_techo_de_doce(): void
    {
        $repaso = new RepasoService;

        // 15 descriptores vencidos, cada uno con dos ítems.
        $codes = ['A1.CO.1', 'A1.CO.2', 'A1.CO.3', 'A1.CE.1', 'A1.CE.2', 'A1.CE.3',
            'A1.IO.1', 'A1.IO.2', 'A1.IO.3', 'A1.PO.1', 'A1.PO.2', 'A1.EE.1', 'A1.EE.2'];
        foreach ($codes as $i => $code) {
            $this->itemDe($code, 'it', 1);
            $this->itemDe($code, 'it', 2);
            ObjectiveMastery::create([
                'user_id' => $this->ana->id,
                'objective_id' => LearningObjective::where('native_code', $code)->firstOrFail()->id,
                'mastery' => 0.8, 'streak' => 2, 'attempts_count' => 2,
                'repaso_intervalo' => 2, 'repaso_en' => now()->subDays($i + 1),
                'last_attempt_at' => now()->subDay(),
            ]);
        }

        $cola = $repaso->cola($this->ana->id, 'it');
        $this->assertLessThanOrEqual(12, $cola['pendientes'], 'La cola pasó del techo de 12.');
        $this->assertSame(13, count($codes));   // había más de 12 vencidos
    }

    /** La lengua es cerrada en la cola: un descriptor italiano no sale pidiendo alemán. */
    public function test_la_cola_es_cerrada_por_lengua_con_dos_lenguas_sembradas(): void
    {
        $repaso = new RepasoService;
        $obj = LearningObjective::where('native_code', 'A1.CO.2')->firstOrFail()->id;

        // El MISMO descriptor con dos ítems en italiano y dos en alemán.
        $this->itemDe('A1.CO.2', 'it', 1);
        $this->itemDe('A1.CO.2', 'it', 2);
        $this->itemDe('A1.CO.2', 'de', 3);
        $this->itemDe('A1.CO.2', 'de', 4);
        ObjectiveMastery::create([
            'user_id' => $this->ana->id, 'objective_id' => $obj,
            'mastery' => 0.8, 'streak' => 2, 'attempts_count' => 2,
            'repaso_intervalo' => 2, 'repaso_en' => now()->subDay(), 'last_attempt_at' => now()->subDay(),
        ]);

        // Italiano ve su repaso; el enlace lleva ?lengua=it.
        $it = $repaso->cola($this->ana->id, 'it');
        $this->assertSame(1, $it['pendientes']);
        $this->assertStringContainsString('lengua=it', $it['siguiente']['url']);

        // Alemán ve el suyo, con su lengua.
        $de = $repaso->cola($this->ana->id, 'de');
        $this->assertStringContainsString('lengua=de', $de['siguiente']['url']);
    }

    /**
     * Un descriptor SIN ítems en la lengua pedida no entra en su cola, aunque
     * tenga ≥2 en OTRA lengua y esté vencido. Si no, `?lengua=it` filtraría el
     * ENLACE pero no los REPASABLES, y un descriptor solo-alemán aparecería en
     * la cola italiana. La lengua es cerrada en las dos consultas, no en una.
     */
    public function test_un_descriptor_sin_items_en_esa_lengua_no_entra_en_la_cola(): void
    {
        $repaso = new RepasoService;

        // A: dos ítems italianos, vencido → entra en la cola de italiano.
        $this->itemDe('A1.CO.2', 'it', 1);
        $this->itemDe('A1.CO.2', 'it', 2);
        $a = LearningObjective::where('native_code', 'A1.CO.2')->firstOrFail()->id;

        // B: dos ítems ALEMANES (ninguno italiano), vencido → NO entra en italiano.
        $this->itemDe('A1.IO.3', 'de', 1);
        $this->itemDe('A1.IO.3', 'de', 2);
        $b = LearningObjective::where('native_code', 'A1.IO.3')->firstOrFail()->id;

        foreach ([$a, $b] as $obj) {
            ObjectiveMastery::create([
                'user_id' => $this->ana->id, 'objective_id' => $obj,
                'mastery' => 0.8, 'streak' => 2, 'attempts_count' => 2,
                'repaso_intervalo' => 2, 'repaso_en' => now()->subDay(), 'last_attempt_at' => now()->subDay(),
            ]);
        }

        $cola = $repaso->cola($this->ana->id, 'it');
        $this->assertSame(1, $cola['pendientes'], 'Un descriptor sin ítems italianos se coló en la cola italiana.');
        $this->assertSame($a, $cola['siguiente']['descriptor_id']);
    }

    /**
     * Un descriptor cuyos ítems no están FIRMADOS no entra en la cola, aunque
     * sean ≥2 y esté vencido: repasar es servir OTRO ítem, y un ítem sin firmar
     * no llega jamás a un alumno. La puerta de la firma vale también en el repaso.
     */
    public function test_un_descriptor_con_items_sin_firmar_no_entra_en_la_cola(): void
    {
        $repaso = new RepasoService;

        // A: dos ítems FIRMADOS → entra.
        $this->itemDe('A1.CO.2', 'it', 1);
        $this->itemDe('A1.CO.2', 'it', 2);
        $a = LearningObjective::where('native_code', 'A1.CO.2')->firstOrFail()->id;

        // B: dos ítems SIN firmar → NO entra (no hay «otro» que se pueda servir).
        $b = LearningObjective::where('native_code', 'A1.IO.3')->firstOrFail()->id;
        foreach ([1, 2] as $seq) {
            PracticeItem::create([
                'objective_id' => $b, 'kind' => 'hueco', 'lengua' => 'it',
                'statement' => ['es' => 'Sin firmar.'], 'params' => [],
                'solucion' => ['lengua' => 'it', 'textos' => ['x']],
                'seq' => $seq, 'reviewed_at' => null,
            ]);
        }

        foreach ([$a, $b] as $obj) {
            ObjectiveMastery::create([
                'user_id' => $this->ana->id, 'objective_id' => $obj,
                'mastery' => 0.8, 'streak' => 2, 'attempts_count' => 2,
                'repaso_intervalo' => 2, 'repaso_en' => now()->subDay(), 'last_attempt_at' => now()->subDay(),
            ]);
        }

        $cola = $repaso->cola($this->ana->id, 'it');
        $this->assertSame(1, $cola['pendientes'], 'Un descriptor con ítems sin firmar se coló en la cola.');
        $this->assertSame($a, $cola['siguiente']['descriptor_id']);
    }

    /** El invitado no tiene cola: cero, y sin consultar filas de nadie. */
    public function test_el_invitado_no_tiene_cola_de_repaso(): void
    {
        $this->itemDe('A1.CO.2', 'it', 1);
        $this->itemDe('A1.CO.2', 'it', 2);

        $cola = (new RepasoService)->cola(null, 'it');
        $this->assertSame(0, $cola['pendientes']);
        $this->assertNull($cola['siguiente']);
    }

    // ================= el endpoint y la regla de oro =================

    public function test_el_endpoint_de_repasos_sirve_la_cola_del_alumno(): void
    {
        $this->itemDe('A1.CO.2', 'it', 1);
        $this->itemDe('A1.CO.2', 'it', 2);
        $obj = LearningObjective::where('native_code', 'A1.CO.2')->firstOrFail()->id;
        ObjectiveMastery::create([
            'user_id' => $this->ana->id, 'objective_id' => $obj,
            'mastery' => 0.8, 'streak' => 2, 'attempts_count' => 2,
            'repaso_intervalo' => 2, 'repaso_en' => now()->subDay(), 'last_attempt_at' => now()->subDay(),
        ]);

        // El invitado PRIMERO (actingAs persiste en el test): 200 con cola
        // vacía, jamás la de otro.
        $this->getJson('/api/v1/practice/repasos?lengua=it')
            ->assertOk()
            ->assertJsonPath('pendientes', 0)
            ->assertJsonPath('se_guarda', false);

        // Y con sesión, la cola de Ana.
        $this->actingAs($this->ana)->getJson('/api/v1/practice/repasos?lengua=it')
            ->assertOk()
            ->assertJsonPath('pendientes', 1)
            ->assertJsonPath('se_guarda', true);
    }

    /** Una lengua fuera de la lista es 422 también aquí. */
    public function test_el_endpoint_rechaza_una_lengua_fuera_de_lista(): void
    {
        $this->getJson('/api/v1/practice/repasos?lengua=klingon')->assertStatus(422);
    }

    // ================= AGS: el repaso no cuenta para la nota =================

    /**
     * EL CORAZÓN DE LA DECISIÓN 2. Un ítem con enlace AGS: practicarlo NORMAL
     * empuja la nota; practicarlo COMO REPASO (billete con repaso) actualiza el
     * dominio pero NO empuja AGS. Las dos mitades, o el test pasa con un push
     * que nunca dispara.
     */
    public function test_el_repaso_actualiza_dominio_pero_no_empuja_ags(): void
    {
        Queue::fake();
        [$item, $user] = $this->itemConEnlaceAgs();

        // Dos ítems del descriptor para que el dominio pueda subir (2 distintos).
        $item2 = $this->itemDe('A1.CO.2', 'it', 77);

        // NORMAL: acierta los dos → la nota viaja (control positivo).
        $this->responder($user, $item, repaso: false);
        $this->responder($user, $item2, repaso: false);
        Queue::assertPushed(PushLtiScore::class);

        // REPASO: mismo acierto, NO se encola otra vez.
        Queue::fake();
        $this->responder($user, $item, repaso: true);
        Queue::assertNotPushed(PushLtiScore::class);

        // Pero el dominio SÍ registró el intento de repaso.
        $this->assertGreaterThanOrEqual(3,
            PracticeAttempt::where('user_id', $user->id)->count());
    }

    /**
     * PRACTICAR POR EL ENDPOINT reprograma el repaso del descriptor. El
     * algoritmo se prueba por unidad arriba, pero eso no demuestra que el
     * controlador lo LLAME: sin esta prueba, borrar la línea de `programar` en
     * `submitAttempt` deja el repaso muerto y todo sigue verde (auditoría).
     */
    public function test_practicar_por_el_endpoint_reprograma_el_repaso_del_descriptor(): void
    {
        $item = $this->itemDe('A1.CO.2', 'it', 1);
        $obj = $item->objective_id;

        // Antes de practicar no hay cita de repaso.
        $this->assertNull(
            ObjectiveMastery::where('user_id', $this->ana->id)->where('objective_id', $obj)->value('repaso_en'),
        );

        $this->responder($this->ana, $item, repaso: false);

        // Un acierto: la cita cae a un día y el intervalo guardado dobla a 2.
        $m = ObjectiveMastery::where('user_id', $this->ana->id)->where('objective_id', $obj)->firstOrFail();
        $this->assertNotNull($m->repaso_en, 'Practicar no programó el repaso: el controlador no llamó a programar.');
        $this->assertSame(2, $m->repaso_intervalo);
        $this->assertEqualsWithDelta(now()->addDay()->timestamp, $m->repaso_en->timestamp, 5);
    }

    /**
     * > El invitado usa todo lo nuevo y no escribe ni una fila ni encola AGS.
     */
    public function test_el_invitado_no_deja_rastro_al_mirar_repasos(): void
    {
        Queue::fake();
        $this->itemDe('A1.CO.2', 'it', 1);

        $antes = [PracticeAttempt::count(), ObjectiveMastery::count(), User::count()];
        $this->getJson('/api/v1/practice/repasos?lengua=it')->assertOk();

        $this->assertSame($antes, [PracticeAttempt::count(), ObjectiveMastery::count(), User::count()]);
        Queue::assertNotPushed(PushLtiScore::class);
    }

    // ---- helpers ----

    private function itemConEnlaceAgs(): array
    {
        $item = $this->itemDe('A1.CO.2', 'it', 1);
        $user = User::factory()->create(['lti_iss' => 'https://moodle.test', 'lti_sub' => 'sub-1']);

        $platform = LtiPlatform::create([
            'issuer' => 'https://moodle.test', 'client_id' => 'c1',
            'deployment_ids' => ['d1'], 'auth_login_url' => 'x', 'auth_token_url' => 'x', 'jwks_url' => 'x',
        ]);
        LtiResourceLink::create([
            'platform_id' => $platform->id, 'resource_link_id' => 'rl-1',
            'user_id' => $user->id, 'objective_id' => $item->objective_id,
            'ags' => ['lineitem' => 'https://moodle.test/li/1'], 'last_launched_at' => now(),
        ]);

        return [$item, $user];
    }

    private function responder(User $user, PracticeItem $item, bool $repaso): void
    {
        $intento = PracticeAttempt::where('item_id', $item->id)->where('user_id', $user->id)->count() + 1;
        $billete = $this->billete($item->id, $user->id, $intento, repaso: $repaso);

        $this->actingAs($user)->postJson("/api/v1/practice/items/{$item->id}/attempts", [
            'respuesta' => ['texto' => 'x'], 'billete' => $billete,
        ])->assertCreated()->assertJsonPath('is_correct', true);
    }
}
