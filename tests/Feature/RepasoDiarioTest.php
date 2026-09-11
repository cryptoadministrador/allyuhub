<?php

namespace Tests\Feature;

use App\Jobs\PushLtiScore;
use App\Models\LearningObjective;
use App\Models\ObjectiveMastery;
use App\Models\PracticeAttempt;
use App\Models\PracticeItem;
use App\Models\User;
use App\Services\Practice\AttemptTicket;
use App\Services\Practice\RachaDeAlumno;
use Database\Seeders\CefrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * PR 9 · «TU REPASO DE HOY» y la racha.
 *
 * Un sitio al que el alumno vuelve cada día: hasta diez ítems por prioridad
 * (vencidos del repaso espaciado → fallos recientes → relleno), jugados como
 * la práctica. El primer repaso del día sube la racha; un día sin nada la
 * rompe. El invitado juega el repaso genérico y no escribe nada.
 */
class RepasoDiarioTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CefrSeeder::class);
        $this->ana = User::factory()->create();
    }

    private function obj(string $code): string
    {
        return LearningObjective::where('native_code', $code)->firstOrFail()->id;
    }

    private function item(string $code, int $seq, string $lengua = 'it'): PracticeItem
    {
        return PracticeItem::create([
            'objective_id' => $this->obj($code), 'kind' => 'hueco', 'lengua' => $lengua,
            'statement' => ['es' => "{$code} #{$seq} ({$lengua})"], 'params' => [],
            'solucion' => ['lengua' => $lengua, 'textos' => ['ciao']],
            'seq' => $seq, 'reviewed_at' => now(),
        ]);
    }

    private function intento(PracticeItem $item, bool $acierto, $cuando = null): void
    {
        $cuando ??= now();
        PracticeAttempt::create([
            'item_id' => $item->id, 'user_id' => $this->ana->id,
            'attempt_no' => PracticeAttempt::where('item_id', $item->id)->where('user_id', $this->ana->id)->count() + 1,
            'seed' => str_repeat('a', 64), 'params' => [], 'respuesta' => ['texto' => 'x'],
            'is_correct' => $acierto, 'created_at' => $cuando, 'updated_at' => $cuando,
        ]);
    }

    private function diario(string $lengua = 'it'): array
    {
        return $this->actingAs($this->ana)->getJson("/api/v1/practice/repaso-diario?lengua={$lengua}")
            ->assertOk()->json();
    }

    // ================= las tres prioridades, en orden =================

    public function test_vencidos_primero_luego_fallos_luego_relleno(): void
    {
        // Prioridad 1: A1.CO.2 vencido (dos ítems; el #1 tocado ayer, el #2 nunca).
        $co1 = $this->item('A1.CO.2', 1);
        $co2 = $this->item('A1.CO.2', 2);
        $this->intento($co1, true, now()->subDay());
        ObjectiveMastery::create([
            'user_id' => $this->ana->id, 'objective_id' => $this->obj('A1.CO.2'),
            'mastery' => 0.8, 'streak' => 2, 'attempts_count' => 2,
            'repaso_intervalo' => 2, 'repaso_en' => now()->subDay(), 'last_attempt_at' => now()->subDay(),
        ]);

        // Prioridad 2: A1.IO.3 con el último intento FALLADO (un solo ítem: no
        // entra en la cola de vencidos, sí en la de fallos).
        $io = $this->item('A1.IO.3', 1);
        $this->intento($io, false, now()->subHours(2));

        // Prioridad 3: A1.CE.1 sin tocar.
        $ce1 = $this->item('A1.CE.1', 1);
        $ce2 = $this->item('A1.CE.1', 2);

        $d = $this->diario();
        $ids = collect($d['items'])->pluck('item_id')->all();
        $prioridades = collect($d['items'])->pluck('prioridad')->all();

        // 1.º el OTRO ítem del descriptor vencido (el que nunca tocó), no el de ayer.
        $this->assertSame($co2->id, $ids[0], 'El repaso no empezó por el descriptor vencido con OTRO ítem.');
        // 2.º el ítem que falló.
        $this->assertSame($io->id, $ids[1]);
        // Después el relleno, sin repetir y sin el ya tocado hoy… el #1 de CO.2
        // sí puede entrar de relleno solo si no se ha intentado nunca: se
        // intentó ayer, así que NO entra.
        $this->assertNotContains($co1->id, $ids);
        $this->assertEqualsCanonicalizing([$ce1->id, $ce2->id], array_slice($ids, 2));
        $this->assertSame([1, 2, 3, 3], $prioridades);
        $this->assertSame(4, $d['total']);
    }

    /** Lo vencido y lo fallado van con `repaso: true` en el billete; el relleno, no. */
    public function test_el_billete_lleva_el_flag_de_repaso_segun_la_prioridad(): void
    {
        $co1 = $this->item('A1.CO.2', 1);
        $co2 = $this->item('A1.CO.2', 2);
        $this->intento($co1, true, now()->subDay());
        ObjectiveMastery::create([
            'user_id' => $this->ana->id, 'objective_id' => $this->obj('A1.CO.2'),
            'mastery' => 0.8, 'streak' => 2, 'attempts_count' => 2,
            'repaso_intervalo' => 2, 'repaso_en' => now()->subDay(), 'last_attempt_at' => now()->subDay(),
        ]);
        $io = $this->item('A1.IO.3', 1);
        $this->intento($io, false, now()->subHours(2));   // prioridad 2
        $this->item('A1.CE.1', 1);

        $items = $this->diario()['items'];
        $this->assertSame([1, 2, 3], collect($items)->pluck('prioridad')->all());
        foreach ($items as $it) {
            $abierto = AttemptTicket::abrir($it['billete'], $it['item_id'], $this->ana->id);
            $this->assertSame($it['prioridad'] < 3, $abierto['repaso'],
                "El billete de prioridad {$it['prioridad']} lleva repaso={$abierto['repaso']}.");
            $this->assertSame($it['repaso'], $abierto['repaso'], 'El JSON y el billete no coinciden.');
        }
    }

    public function test_el_mismo_dia_es_el_mismo_repaso(): void
    {
        foreach (range(1, 15) as $i) {
            $this->item('A1.CO.2', $i);
        }
        $this->travelTo(now(RachaDeAlumno::ZONA)->setTime(10, 0));

        $a = collect($this->diario()['items'])->pluck('item_id')->all();
        $b = collect($this->diario()['items'])->pluck('item_id')->all();
        $this->assertSame($a, $b);
        $this->assertCount(10, $a);
        $this->assertCount(10, array_unique($a));

        // Otro alumno sin historia, mismo día: OTRO orden (la semilla es por quién).
        $otro = User::factory()->create();
        $o = collect($this->actingAs($otro)->getJson('/api/v1/practice/repaso-diario?lengua=it')->json('items'))
            ->pluck('item_id')->all();
        $this->assertNotSame($a, $o, 'Dos alumnos distintos recibieron el mismo repaso.');

        // Mañana, otro (con 15 ítems, el orden por semilla del día cambia).
        $this->travelTo(now()->addDay());
        $c = collect($this->actingAs($this->ana)->getJson('/api/v1/practice/repaso-diario?lengua=it')->json('items'))
            ->pluck('item_id')->all();
        $this->assertNotSame($a, $c);
    }

    // ================= qué NO entra =================

    /** Un fallo que después se corrigió ya no es «reciente»: cuenta el ÚLTIMO intento. */
    public function test_un_fallo_seguido_de_acierto_no_es_un_fallo_reciente(): void
    {
        $io = $this->item('A1.IO.3', 1);
        $this->intento($io, false, now()->subHours(3));
        $this->intento($io, true, now()->subHours(2));
        $this->item('A1.CE.1', 1);

        $d = $this->diario();
        $this->assertNotContains($io->id, collect($d['items'])->pluck('item_id')->all(),
            'Un ítem acertado en su último intento volvió como «fallo reciente».');
        $this->assertNotContains(2, collect($d['items'])->pluck('prioridad')->all());
    }

    /** Un descriptor con cita FUTURA no está vencido: no entra en la prioridad 1. */
    public function test_un_descriptor_con_repaso_en_el_futuro_no_esta_vencido(): void
    {
        $co1 = $this->item('A1.CO.2', 1);
        $this->item('A1.CO.2', 2);
        $this->intento($co1, true, now()->subDay());
        ObjectiveMastery::create([
            'user_id' => $this->ana->id, 'objective_id' => $this->obj('A1.CO.2'),
            'mastery' => 0.8, 'streak' => 2, 'attempts_count' => 2,
            'repaso_intervalo' => 4, 'repaso_en' => now()->addDays(3), 'last_attempt_at' => now()->subDay(),
        ]);

        $prioridades = collect($this->diario()['items'])->pluck('prioridad')->unique()->all();
        $this->assertSame([3], $prioridades, 'Un descriptor con cita para dentro de tres días entró como vencido.');
    }

    /** Un ítem sin firmar no llega al repaso: ni al del alumno ni al del invitado. */
    public function test_un_item_sin_firmar_no_entra_en_el_repaso(): void
    {
        $this->item('A1.CO.2', 1);
        $sinFirmar = $this->item('A1.CO.2', 2);
        $sinFirmar->forceFill(['reviewed_at' => null])->save();

        $alumno = collect($this->diario()['items'])->pluck('item_id')->all();
        $invitado = collect($this->getJson('/api/v1/practice/repaso-diario?lengua=it')->json('items'))->pluck('item_id')->all();
        $this->assertNotContains($sinFirmar->id, $alumno);
        $this->assertNotContains($sinFirmar->id, $invitado);
        $this->assertCount(1, $alumno);
    }

    /**
     * El billete del repaso lleva el número de intento REAL del alumno para ese
     * ítem (el mismo que daría `next`): si el vencido se sirve con un ítem ya
     * tocado, el POST es el intento 2, no un 409 por «intento 1 repetido».
     */
    public function test_el_billete_del_repaso_lleva_el_intento_real(): void
    {
        $x = $this->item('A1.CO.2', 1);
        $y = $this->item('A1.CO.2', 2);
        $this->intento($x, true, now()->subDays(3));   // el más antiguo: se sirve x
        $this->intento($y, true, now()->subDay());
        ObjectiveMastery::create([
            'user_id' => $this->ana->id, 'objective_id' => $this->obj('A1.CO.2'),
            'mastery' => 0.8, 'streak' => 2, 'attempts_count' => 2,
            'repaso_intervalo' => 2, 'repaso_en' => now()->subDay(), 'last_attempt_at' => now()->subDay(),
        ]);

        $it = $this->diario()['items'][0];
        $this->assertSame($x->id, $it['item_id']);
        $this->assertSame(2, $it['attempt_no']);
        $this->actingAs($this->ana)->postJson("/api/v1/practice/items/{$x->id}/attempts", [
            'respuesta' => ['texto' => 'ciao'], 'billete' => $it['billete'],
        ])->assertCreated()->assertJsonPath('attempt_no', 2);
    }

    // ================= se juega COMO LA PRÁCTICA =================

    /**
     * Cada respuesta del repaso va al endpoint de intentos de siempre con el
     * billete que vino con el ítem: un intento real, con dominio, y como es
     * repaso NO empuja AGS aunque califique.
     */
    public function test_responder_el_repaso_es_un_intento_de_practica_sin_ags(): void
    {
        Queue::fake();
        $co1 = $this->item('A1.CO.2', 1);
        $co2 = $this->item('A1.CO.2', 2);
        $this->intento($co1, true, now()->subDay());
        ObjectiveMastery::create([
            'user_id' => $this->ana->id, 'objective_id' => $this->obj('A1.CO.2'),
            'mastery' => 0.8, 'streak' => 2, 'attempts_count' => 2,
            'repaso_intervalo' => 2, 'repaso_en' => now()->subDay(), 'last_attempt_at' => now()->subDay(),
        ]);

        $it = $this->diario()['items'][0];
        $this->assertSame($co2->id, $it['item_id']);
        $this->assertTrue($it['repaso']);

        $antes = PracticeAttempt::where('user_id', $this->ana->id)->count();
        $this->actingAs($this->ana)->postJson("/api/v1/practice/items/{$it['item_id']}/attempts", [
            'respuesta' => ['texto' => 'ciao'], 'billete' => $it['billete'],
        ])->assertCreated()->assertJsonPath('is_correct', true)->assertJsonPath('se_guarda', true);

        $this->assertSame($antes + 1, PracticeAttempt::where('user_id', $this->ana->id)->count());
        Queue::assertNotPushed(PushLtiScore::class);
    }

    // ================= la racha =================

    public function test_el_primer_repaso_del_dia_sube_la_racha(): void
    {
        $item = $this->item('A1.CO.2', 1);
        $this->item('A1.CO.2', 2);   // algo por ver, para que hoy haya repaso
        $this->intento($item, true, now(RachaDeAlumno::ZONA)->subDay()->setTime(12, 0)->setTimezone('UTC'));

        // Ayer sí, hoy todavía no: viva con 1, y sin actividad hoy.
        $antes = $this->actingAs($this->ana)->getJson('/api/v1/practice/racha')->assertOk()->json();
        $this->assertSame(['dias' => 1, 'viva' => true, 'activo_hoy' => false, 'se_guarda' => true], $antes);

        // Se responde el repaso de hoy.
        $it = $this->diario()['items'][0];
        $this->actingAs($this->ana)->postJson("/api/v1/practice/items/{$it['item_id']}/attempts", [
            'respuesta' => ['texto' => 'ciao'], 'billete' => $it['billete'],
        ])->assertCreated();

        $despues = $this->actingAs($this->ana)->getJson('/api/v1/practice/racha')->json();
        $this->assertSame(2, $despues['dias']);
        $this->assertTrue($despues['activo_hoy']);
    }

    /**
     * «Hoy» es el de ECUADOR también para `activo_hoy`: practicar a las ocho de
     * la tarde de Quito (01:00 UTC del día siguiente) es AYER si en Quito ya
     * es la una de la madrugada del día siguiente, aunque en UTC sea el mismo día.
     */
    public function test_activo_hoy_se_mide_en_hora_de_ecuador(): void
    {
        $item = $this->item('A1.CO.2', 1);
        // Ahora: 2026-09-11 01:00 en Quito (= 06:00 UTC del 11).
        $this->travelTo(Carbon::parse('2026-09-11 01:00', RachaDeAlumno::ZONA));
        // Ayer a las 20:00 en Quito (= 01:00 UTC del 11: MISMO día en UTC).
        $this->intento($item, true, Carbon::parse('2026-09-10 20:00', RachaDeAlumno::ZONA)->setTimezone('UTC'));

        $r = $this->actingAs($this->ana)->getJson('/api/v1/practice/racha')->json();
        $this->assertFalse($r['activo_hoy'], 'Las ocho de la tarde de ayer en Quito contaron como hoy (¿UTC?).');
        $this->assertSame(1, $r['dias']);
        $this->assertTrue($r['viva']);
    }

    // ================= regla de oro =================

    public function test_el_invitado_juega_el_repaso_generico_y_no_escribe_nada(): void
    {
        Queue::fake();
        $this->item('A1.CO.2', 1);
        $this->item('A1.CO.2', 2);
        $this->item('A1.CE.1', 1);
        $antes = [PracticeAttempt::count(), ObjectiveMastery::count(), User::count()];

        $d = $this->getJson('/api/v1/practice/repaso-diario?lengua=it')->assertOk()->json();
        $this->assertFalse($d['se_guarda']);
        $this->assertSame(3, $d['total']);
        // Solo relleno: sin historia no hay vencidos ni fallos.
        $this->assertSame([3, 3, 3], collect($d['items'])->pluck('prioridad')->all());
        $this->assertSame(['dias' => 0, 'viva' => false, 'activo_hoy' => false], $d['racha']);

        // Y lo juega entero por el endpoint de siempre.
        foreach ($d['items'] as $it) {
            $this->postJson("/api/v1/practice/items/{$it['item_id']}/attempts", [
                'respuesta' => ['texto' => 'ciao'], 'billete' => $it['billete'],
            ])->assertOk()->assertJsonPath('se_guarda', false);
        }

        $this->assertSame($antes, [PracticeAttempt::count(), ObjectiveMastery::count(), User::count()]);
        Queue::assertNothingPushed();
        $this->getJson('/api/v1/practice/racha')->assertOk()->assertJsonPath('dias', 0);
    }

    // ================= lengua cerrada, con las cinco =================

    public function test_el_repaso_de_una_lengua_no_trae_items_de_otra(): void
    {
        foreach (['it', 'fr', 'de', 'zh'] as $lengua) {
            $this->item('A1.CO.2', 1, $lengua);
            $this->item('A1.CO.2', 2, $lengua);
        }

        foreach (['it', 'fr', 'de', 'zh'] as $lengua) {
            $lenguas = collect($this->diario($lengua)['items'])
                ->map(fn ($it) => PracticeItem::find($it['item_id'])->lengua)->unique()->values()->all();
            $this->assertSame([$lengua], $lenguas, "El repaso de «{$lengua}» mezcló otra lengua.");
        }

        $this->diario('en')['total'] === 0 || $this->fail('El inglés no tiene ítems y trajo repaso.');
        $this->actingAs($this->ana)->getJson('/api/v1/practice/repaso-diario?lengua=klingon')->assertStatus(422);
    }

    // ================= la página =================

    public function test_la_pagina_del_repaso_es_abierta(): void
    {
        $this->get('/corso/it/repaso')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('repaso')
                ->where('lengua', 'it')->where('se_guarda', false)->where('racha.dias', 0));
        $this->get('/corso/klingon/repaso')->assertNotFound();
    }
}
