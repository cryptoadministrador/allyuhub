<?php

namespace Tests\Feature;

use App\Console\Commands\SeedPracticeBank;
use App\Jobs\PushLtiScore;
use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\LtiPlatform;
use App\Models\LtiResourceLink;
use App\Models\ObjectiveMastery;
use App\Models\PracticeItem;
use App\Models\User;
use Database\Seeders\CurriculumSeeder;
use Database\Seeders\PracticeItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Dos propiedades EMERGENTES que ninguna pieza tenía mal por su cuenta.
 *
 * 1. DOMINIO. Una destreza con un único ítem de opción múltiple se «dominaba»
 *    en tres clics: el ítem no re-aleatoriza nada entre intentos, al fallar se
 *    revela cuál era la buena, y con racha ≥3 se sella `mastered_at` —que no se
 *    borra nunca— y se empuja la nota a Moodle. El numérico era inmune porque
 *    los números cambian. La respuesta NO es esconder la explicación (con 4
 *    opciones se fuerza bruta igual, y encima se pierde el aprendizaje): es que
 *    el dominio de una destreza con un solo ítem no significa nada, haya
 *    trampa o no. Hacen falta aciertos en ≥2 ítems DISTINTOS.
 *
 * 2. FIRMA. `attrs.revision.alineado_a = 'bloque'` era una etiqueta que no leía
 *    nadie: `practica:sembrar` publicaba las 80 preguntas sin revisar a alumnos
 *    reales, al instante. El precedente correcto estaba en el propio repo —el
 *    crosswalk no navega sin `reviewed_at`— y ahora se aplica igual aquí.
 */
class DominioYFirmaTest extends TestCase
{
    use RefreshDatabase;

    private LearningObjective $objective;

    private User $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $fw = Framework::create([
            'code' => 'EC-MINEDEC', 'authority' => 'MINEDEC', 'kind' => 'national',
            'country' => 'EC', 'label' => ['es' => 'Currículo Nacional'],
        ]);
        $version = FrameworkVersion::create(['framework_id' => $fw->id, 'label' => '2016+2023']);
        $nodo = CurNode::create([
            'version_id' => $version->id, 'node_type' => 'grado',
            'native_code' => 'g8', 'title' => ['es' => '8.º EGB'], 'path' => 'egb.sup.g8',
        ]);
        $this->objective = LearningObjective::create([
            'node_id' => $nodo->id, 'version_id' => $version->id,
            'native_code' => 'LL.4.1.1', 'statement' => ['es' => 'Reconocer la variedad lingüística.'],
            'is_verified' => true,
        ]);

        $this->ana = User::factory()->create();
    }

    /** Un ítem de opción múltiple FIRMADO, con la correcta en 'b'. */
    private function choice(int $seq, ?string $firmado = 'ahora'): PracticeItem
    {
        return PracticeItem::create([
            'objective_id' => $this->objective->id,
            'kind' => PracticeItem::CHOICE,
            'seq' => $seq,
            'statement' => ['es' => "Pregunta {$seq}"],
            'params' => [],
            'solution_expr' => null,
            'options' => [
                ['key' => 'a', 'text' => ['es' => 'Mal']],
                ['key' => 'b', 'text' => ['es' => 'Bien']],
                ['key' => 'c', 'text' => ['es' => 'Peor']],
            ],
            'answer_key' => 'b',
            'reviewed_at' => $firmado === null ? null : now(),
        ]);
    }

    private function responder(PracticeItem $item, string $clave)
    {
        return $this->actingAs($this->ana)
            ->postJson("/api/v1/practice/items/{$item->id}/attempts", ['answer_key' => $clave]);
    }

    // ================= 1. EL DOMINIO NO SE FALSIFICA =================

    /**
     * EL ESCENARIO EXACTO: fallar una vez para que te digan cuál era, y
     * repetirla hasta sellar el dominio. Con un solo ítem, ya no sella.
     */
    public function test_con_un_solo_item_no_se_sella_dominio_por_repetir(): void
    {
        $unico = $this->choice(0);

        // Falla y le revelan la buena…
        $this->responder($unico, 'a')->assertCreated()->assertJsonPath('expected_key', 'b');

        // …y ahora la repite todas las veces que quiera.
        foreach (range(1, 8) as $i) {
            $this->responder($unico, 'b')->assertCreated()->assertJsonPath('is_correct', true);
        }

        $dominio = ObjectiveMastery::where('user_id', $this->ana->id)->firstOrFail();
        $this->assertGreaterThan(0.8, (float) $dominio->mastery, 'La EMA sí sube: eso no cambia.');
        $this->assertNull($dominio->mastered_at,
            'Se selló el dominio de una destreza con UN solo ítem: tres clics y destreza dominada para siempre.');
    }

    /** Con dos ítems distintos, el dominio se sella como siempre. */
    public function test_con_dos_items_distintos_el_dominio_se_sella(): void
    {
        $uno = $this->choice(0);
        $dos = $this->choice(1);

        // Cuatro aciertos para cruzar el umbral 0.8 de la EMA (α=0.35), pero
        // repartidos entre los dos ítems: eso es lo que cambia.
        $this->responder($uno, 'b')->assertCreated();
        $this->responder($dos, 'b')->assertCreated();
        $this->responder($uno, 'b')->assertCreated();
        $this->responder($dos, 'b')->assertCreated();

        $dominio = ObjectiveMastery::where('user_id', $this->ana->id)->firstOrFail();
        $this->assertNotNull($dominio->mastered_at);
    }

    /**
     * Acertar el MISMO ítem muchas veces no cuenta como dos: lo que se exige
     * son ítems distintos, no intentos distintos.
     */
    public function test_acertar_dos_veces_el_mismo_item_no_vale_por_dos(): void
    {
        $uno = $this->choice(0);
        $this->choice(1);   // existe, pero el alumno no lo ha acertado

        foreach (range(1, 6) as $i) {
            $this->responder($uno, 'b')->assertCreated();
        }

        $this->assertNull(
            ObjectiveMastery::where('user_id', $this->ana->id)->firstOrFail()->mastered_at,
        );
    }

    /** Y fallar el segundo ítem tampoco lo desbloquea: hay que acertarlo. */
    public function test_fallar_el_segundo_item_no_desbloquea_el_dominio(): void
    {
        $uno = $this->choice(0);
        $dos = $this->choice(1);

        foreach (range(1, 4) as $i) {
            $this->responder($uno, 'b')->assertCreated();
        }
        $this->responder($dos, 'a')->assertCreated()->assertJsonPath('is_correct', false);
        $this->responder($uno, 'b')->assertCreated();
        $this->responder($uno, 'b')->assertCreated();
        $this->responder($uno, 'b')->assertCreated();

        $this->assertNull(
            ObjectiveMastery::where('user_id', $this->ana->id)->firstOrFail()->mastered_at,
        );
    }

    /** La NOTA tampoco viaja a Moodle mientras el dominio no signifique nada. */
    public function test_con_un_solo_item_la_nota_no_viaja_al_aula(): void
    {
        Queue::fake();
        $this->crearResourceLink();
        $unico = $this->choice(0);

        foreach (range(1, 5) as $i) {
            $this->responder($unico, 'b')->assertCreated();
        }

        Queue::assertNotPushed(PushLtiScore::class);
    }

    public function test_con_dos_items_la_nota_si_viaja(): void
    {
        Queue::fake();
        $this->crearResourceLink();

        $this->responder($this->choice(0), 'b')->assertCreated();
        $this->responder($this->choice(1), 'b')->assertCreated();

        Queue::assertPushed(PushLtiScore::class);
    }

    /**
     * El camino NUMÉRICO era inmune al truco —los números cambian en cada
     * intento— pero la regla es la misma para los dos: un solo ítem no basta.
     * Si no, quedaría una asimetría que nadie sabría explicar.
     */
    public function test_la_regla_es_la_misma_para_el_camino_numerico(): void
    {
        $numerico = PracticeItem::create([
            'objective_id' => $this->objective->id, 'seq' => 0,
            'statement' => ['es' => 'Suma {a} + {b}'],
            'params' => ['a' => ['const' => 2], 'b' => ['const' => 3]],
            'solution_expr' => 'a + b', 'tolerance' => 0.01, 'tolerance_kind' => 'abs',
            'reviewed_at' => now(),
        ]);

        foreach (range(1, 5) as $i) {
            $this->actingAs($this->ana)
                ->postJson("/api/v1/practice/items/{$numerico->id}/attempts", ['answer' => 5])
                ->assertCreated();
        }

        $this->assertNull(
            ObjectiveMastery::where('user_id', $this->ana->id)->firstOrFail()->mastered_at,
        );
    }

    // ================= 2. LA FIRMA ES UNA PUERTA =================

    public function test_un_item_sin_firmar_no_se_sirve(): void
    {
        $this->choice(0, firmado: null);

        $this->getJson("/api/v1/objectives/{$this->objective->id}/practice/next")
            ->assertNotFound();
    }

    public function test_en_cuanto_se_firma_se_sirve(): void
    {
        $item = $this->choice(0, firmado: null);

        $this->getJson("/api/v1/objectives/{$this->objective->id}/practice/next")->assertNotFound();

        $item->update(['reviewed_at' => now()]);

        $this->getJson("/api/v1/objectives/{$this->objective->id}/practice/next")
            ->assertOk()
            ->assertJsonPath('item_id', $item->id);
    }

    /**
     * Y la destreza no puede ANUNCIAR ejercicios que no va a servir: un botón
     * que lleva a un 404 es peor que un botón ausente (regla de la casa).
     */
    public function test_una_destreza_con_solo_items_sin_firmar_no_dice_tener_ejercicios(): void
    {
        $this->choice(0, firmado: null);

        $this->get("/destreza/{$this->objective->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('objective.has_items', false));

        $this->get("/practicar/{$this->objective->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('objective.has_items', false));
    }

    /**
     * REGRESIÓN QUE HAY QUE EVITAR: los 17 ítems de física del banco viejo no
     * llevan marca de revisión. Si la puerta se cierra sin más, la práctica de
     * física desaparece en silencio — que es peor que no haber puesto puerta.
     */
    public function test_el_banco_viejo_sigue_sirviendose_tras_la_migracion(): void
    {
        $this->sembrarElCurriculoDeVerdad();
        $this->seed(PracticeItemSeeder::class);

        $conItems = LearningObjective::whereHas('practiceItems')->count();
        $this->assertGreaterThanOrEqual(8, $conItems,
            'El banco de física dejó de servirse: la puerta se cerró sobre lo que ya existía.');

        $plano = LearningObjective::where('native_code', 'CN.F.5.1.9')->firstOrFail();
        $this->getJson("/api/v1/objectives/{$plano->id}/practice/next")->assertOk();
    }

    /**
     * MUTACIÓN SUPERVIVIENTE (bucle B): quitar el backfill de la migración no
     * ponía nada rojo, porque en la suite las migraciones corren sobre una base
     * VACÍA — no hay nada preexistente que firmar. En producción sí lo hay: los
     * 17 ítems de física llevan meses ahí, y sin backfill la puerta los habría
     * hecho desaparecer en silencio el día del despliegue.
     *
     * Se prueba ejecutando la migración de verdad contra una fila que existe
     * desde ANTES: `down()` retira la puerta, se inserta el ítem como estaba, y
     * `up()` vuelve a ponerla.
     */
    public function test_la_migracion_firma_lo_que_ya_existia(): void
    {
        $migracion = require database_path(
            'migrations/2026_08_22_000001_add_review_gate_to_practice_items.php',
        );

        $migracion->down();   // la base, tal y como estaba antes del parche

        $id = (string) Str::uuid7();
        DB::table('practice_items')->insert([
            'id' => $id,
            'objective_id' => $this->objective->id,
            'kind' => 'numeric',
            'statement' => json_encode(['es' => 'Ítem de siempre {m}']),
            'params' => json_encode(['m' => ['const' => 2]]),
            'solution_expr' => 'm',
            'tolerance' => 0.02, 'tolerance_kind' => 'rel', 'seq' => 0,
            'attrs' => '{}', 'shuffle' => true, 'origen' => 'curado',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $migracion->up();

        $this->assertNotNull(
            DB::table('practice_items')->where('id', $id)->value('reviewed_at'),
            'La migración dejó sin firmar un ítem que ya existía: la práctica de física '.
            'habría desaparecido en silencio al desplegar.',
        );

        // Y de verdad se sirve.
        $this->getJson("/api/v1/objectives/{$this->objective->id}/practice/next")
            ->assertOk()
            ->assertJsonPath('item_id', $id);
    }

    /** Lo que siembra `practica:sembrar` nace SIN firmar: no se publica solo. */
    public function test_el_banco_nuevo_nace_sin_firmar(): void
    {
        $this->sembrarElCurriculoDeVerdad();
        $this->artisan('practica:sembrar', ['--incluir-no-verificadas' => true])->assertSuccessful();

        $delBanco = PracticeItem::where('seq', SeedPracticeBank::BASE_SEQ)->get();
        $this->assertGreaterThan(0, $delBanco->count());

        $this->assertSame(0, $delBanco->whereNotNull('reviewed_at')->count(),
            'La siembra publicó ítems sin revisar a alumnos reales.');
    }

    /** Y el comando lo DICE, en vez de dejar 240 ítems invisibles sin avisar. */
    public function test_la_siembra_avisa_de_lo_que_queda_pendiente_de_firma(): void
    {
        $this->sembrarElCurriculoDeVerdad();

        $this->artisan('practica:sembrar', ['--incluir-no-verificadas' => true])
            ->expectsOutputToContain('pendiente(s) de firma')
            ->expectsOutputToContain('practica:firmar')
            ->assertSuccessful();
    }

    /** El informe también dice cuántas destrezas no pueden dominarse todavía. */
    public function test_la_siembra_avisa_de_las_destrezas_con_un_solo_item(): void
    {
        $this->sembrarElCurriculoDeVerdad();

        $this->artisan('practica:sembrar', ['--incluir-no-verificadas' => true])
            ->expectsOutputToContain('un solo ítem')
            ->assertSuccessful();
    }

    // ================= El comando de firma =================

    public function test_firmar_publica_un_bloque_y_deja_el_resto_quieto(): void
    {
        $this->sembrarElCurriculoDeVerdad();
        $this->artisan('practica:sembrar', ['--incluir-no-verificadas' => true])->assertSuccessful();

        $this->artisan('practica:firmar', ['--bloque' => 'LL.4.1'])->assertSuccessful();

        $firmados = PracticeItem::whereNotNull('reviewed_at')->get();
        $this->assertGreaterThan(0, $firmados->count());
        foreach ($firmados as $item) {
            $this->assertSame('LL.4.1', $item->attrs['revision']['bloque']);
        }
    }

    public function test_firmar_sin_decir_que_no_firma_nada(): void
    {
        $this->sembrarElCurriculoDeVerdad();
        $this->artisan('practica:sembrar', ['--incluir-no-verificadas' => true])->assertSuccessful();

        $this->artisan('practica:firmar')->assertFailed();
        $this->assertSame(0, PracticeItem::whereNotNull('reviewed_at')->count());
    }

    /**
     * Siembra el currículo de la semilla. Hay que retirar antes el marco
     * mínimo del setUp: CurriculumSeeder es un NO-OP si EC-MINEDEC ya existe
     * —protege un import real de que la semilla lo pise— así que sin esto
     * sembraría cero destrezas y el test pasaría por vacío.
     */
    private function sembrarElCurriculoDeVerdad(): void
    {
        LearningObjective::query()->delete();
        CurNode::query()->delete();
        FrameworkVersion::query()->delete();
        Framework::query()->delete();

        $this->seed(CurriculumSeeder::class);
    }

    private function crearResourceLink(): void
    {
        $platform = LtiPlatform::create([
            'issuer' => 'https://moodle.example.edu', 'client_id' => 'abc123',
            'deployment_ids' => ['dep-1'],
            'auth_login_url' => 'x', 'auth_token_url' => 'x', 'jwks_url' => 'x',
        ]);
        LtiResourceLink::create([
            'platform_id' => $platform->id, 'resource_link_id' => 'rl-1',
            'user_id' => $this->ana->id, 'objective_id' => $this->objective->id,
            'ags' => ['lineitem' => 'https://moodle.example.edu/lineitem/1'],
            'last_launched_at' => now(),
        ]);
    }
}
