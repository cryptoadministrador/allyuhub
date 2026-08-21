<?php

namespace Tests\Feature;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\ObjectiveMastery;
use App\Models\PracticeAttempt;
use App\Models\PracticeItem;
use App\Models\User;
use App\Services\Practice\PracticeEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * ORÁCULOS del segundo tipo de ítem: OPCIÓN MÚLTIPLE.
 *
 * El motor solo sabía corregir números, así que Lengua y Sociales —la mitad
 * del currículo importado— no podían tener práctica. Un ítem `choice` se
 * corrige igual de en-servidor que uno numérico, y con la MISMA semilla
 * sha256(item:quien:intento) que ya existía.
 *
 * Dos propiedades que son el corazón de este frente:
 *
 *  1. La marca de «correcta» NO SALE del servidor. Vive en su propia columna
 *     `answer_key`, nunca en `params` ni en `attrs` —que sí se serializan— y
 *     el payload de `next` se arma por lista blanca, campo a campo.
 *  2. Se responde por CLAVE inmutable, no por posición. La semilla baraja el
 *     orden de pintado y nada más. Eso hace IMPOSIBLE por construcción que un
 *     barajado divergente entre servir y corregir califique mal: el orden no
 *     entra en la comparación. No es algo que un test defienda; es algo que el
 *     diseño no permite.
 */
class PracticaOpcionMultipleTest extends TestCase
{
    use RefreshDatabase;

    private const ITEM_ID = '0198e2c0-0000-7000-8000-00000000cc01';

    private LearningObjective $objective;

    private PracticeItem $item;

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

        $this->item = PracticeItem::create([
            'id' => self::ITEM_ID,
            'objective_id' => $this->objective->id,
            'kind' => 'choice',
            'statement' => ['es' => '¿Cuál de estas palabras es un sustantivo?'],
            'params' => [],
            'solution_expr' => null,
            'options' => [
                ['key' => 'a', 'text' => ['es' => 'Rápidamente']],
                ['key' => 'b', 'text' => ['es' => 'Montaña']],
                ['key' => 'c', 'text' => ['es' => 'Corrió']],
                ['key' => 'd', 'text' => ['es' => 'Azul']],
            ],
            'answer_key' => 'b',
        ]);

        $this->ana = User::factory()->create();
    }

    /** La posición (1..n) en la que se PINTA la correcta en ese intento. */
    private function posicionCorrecta(int $intento, int|string $quien): int
    {
        $engine = new PracticeEngine;
        $seed = $engine->seedFor(self::ITEM_ID, $quien, $intento);
        $barajadas = $engine->shuffleOptions($this->item->options, $seed);

        return array_search('b', array_column($barajadas, 'key'), true) + 1;
    }

    // ============ ORÁCULO 1 — la respuesta correcta NO se filtra ============

    public function test_el_payload_de_next_no_dice_cual_es_la_correcta(): void
    {
        // La respuesta correcta lleva un TEXTO inconfundible y se deja un
        // rastro en `attrs` a propósito: así la comprobación no depende de que
        // un campo se llame «correcta», sino de que la respuesta no salga por
        // NINGUNA vía en el cuerpo serializado.
        $this->item->update([
            'options' => [
                ['key' => 'a', 'text' => ['es' => 'Distractor uno']],
                ['key' => 'b', 'text' => ['es' => 'Distractor dos']],
                ['key' => 'c', 'text' => ['es' => 'Distractor tres']],
            ],
            'answer_key' => 'b',
            'attrs' => ['nota_interna' => 'La buena es Distractor dos'],
        ]);

        $respuesta = $this->getJson("/api/v1/objectives/{$this->objective->id}/practice/next")
            ->assertOk();
        $json = $respuesta->json();

        $this->assertSame('choice', $json['kind']);
        $this->assertCount(3, $json['options']);

        // Cada opción trae SOLO su clave y su texto: lista blanca, no volcado.
        foreach ($json['options'] as $opcion) {
            $this->assertSame(['key', 'text'], array_keys($opcion));
        }

        // EL ORÁCULO: sobre el cuerpo COMPLETO, ni una pista de cuál es la
        // buena — ni el nombre del campo, ni lo que alguien dejara en attrs.
        $cuerpo = $respuesta->getContent();
        $this->assertStringNotContainsString('La buena es', $cuerpo);
        $this->assertStringNotContainsString('nota_interna', $cuerpo);
        $this->assertStringNotContainsString('answer_key', $cuerpo);
        $this->assertStringNotContainsString('solution_expr', $cuerpo);
        $this->assertArrayNotHasKey('expected_key', $json);

        // Las tres opciones viajan con su clave inmutable, indistinguibles.
        $this->assertEqualsCanonicalizing(['a', 'b', 'c'], array_column($json['options'], 'key'));
    }

    /**
     * Y con el ítem tal cual está en el banco: el texto de la correcta aparece
     * en el cuerpo UNA sola vez —como opción, igual que los distractores— y
     * ningún campo extra lo señala.
     */
    public function test_la_opcion_correcta_es_indistinguible_de_los_distractores(): void
    {
        $respuesta = $this->getJson("/api/v1/objectives/{$this->objective->id}/practice/next")
            ->assertOk();

        $formas = array_map(fn (array $o) => array_keys($o), $respuesta->json('options'));
        $this->assertSame(array_fill(0, 4, ['key', 'text']), $formas);

        // El texto de la buena sale una vez, ni más ni menos que los otros.
        $cuerpo = $respuesta->getContent();
        foreach (['Monta', 'pidamente', 'Corri', 'Azul'] as $fragmento) {
            $this->assertSame(1, substr_count($cuerpo, $fragmento),
                "«{$fragmento}» no aparece exactamente una vez: algo la distingue.");
        }
    }

    // ============ ORÁCULO 2 — la correcta se baraja por semilla ============

    public function test_la_opcion_correcta_no_cae_siempre_en_el_mismo_sitio(): void
    {
        $posiciones = [];
        foreach (range(1, 12) as $intento) {
            $posiciones[] = $this->posicionCorrecta($intento, 'invitado');
        }

        $this->assertGreaterThan(1, count(array_unique($posiciones)),
            'La correcta cae siempre en la misma posición: la barajada no depende de la semilla.');
    }

    public function test_la_barajada_es_reproducible_con_la_misma_semilla(): void
    {
        $engine = new PracticeEngine;
        $seed = $engine->seedFor(self::ITEM_ID, 'invitado', 7);

        $una = $engine->shuffleOptions($this->item->options, $seed);
        $otra = $engine->shuffleOptions($this->item->options, $seed);

        $this->assertSame($una, $otra);
        // Y no pierde ni duplica opciones.
        $this->assertEqualsCanonicalizing(
            array_column($this->item->options, 'key'),
            array_column($una, 'key'),
        );
    }

    /** Dos personas no ven la correcta pintada en el mismo sitio. */
    public function test_dos_personas_no_ven_la_correcta_en_el_mismo_sitio_siempre(): void
    {
        $deCadaUno = [];
        foreach (range(1, 12) as $usuario) {
            $deCadaUno[] = $this->posicionCorrecta(1, $usuario);
        }

        $this->assertGreaterThan(1, count(array_unique($deCadaUno)));
    }

    // ============ ORÁCULO 3 — corrección en servidor ============

    public function test_elegir_la_buena_es_correcto_y_otra_es_incorrecto(): void
    {
        $this->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', ['answer_key' => 'b'])
            ->assertOk()
            ->assertJsonPath('is_correct', true)
            ->assertJsonPath('expected_key', 'b');

        foreach (['a', 'c', 'd'] as $mala) {
            $this->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', ['answer_key' => $mala])
                ->assertOk()
                ->assertJsonPath('is_correct', false)
                // La explicación llega DESPUÉS de responder, como en numérico.
                ->assertJsonPath('expected_key', 'b');
        }
    }

    /**
     * El veredicto NO depende del número de intento, y eso importa: el camino
     * numérico re-deriva la semilla contando filas, así que un 409 y su
     * reintento pueden desalinear lo servido de lo corregido. En `choice` no
     * hay nada que desalinear — la clave es la misma en todos los intentos.
     */
    public function test_el_veredicto_no_depende_del_numero_de_intento(): void
    {
        foreach ([1, 2, 7, 99, 500] as $intento) {
            $this->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', [
                'answer_key' => 'b', 'intento' => $intento,
            ])->assertOk()->assertJsonPath('is_correct', true);
        }
    }

    public function test_una_clave_que_no_estaba_entre_las_servidas_es_422(): void
    {
        foreach (['9', 'z', '0', '', 'todas', 'B'] as $inventada) {
            $this->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', [
                'answer_key' => $inventada,
            ])->assertStatus(422);
        }
    }

    /** Un ítem de opción múltiple no se responde con un número suelto. */
    public function test_un_choice_no_acepta_una_respuesta_numerica(): void
    {
        $this->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', ['answer' => 3])
            ->assertStatus(422)
            ->assertJsonValidationErrors('answer_key');
    }

    // ============ ORÁCULO 4 — la regla de oro se conserva ============

    public function test_el_invitado_responde_un_choice_y_no_escribe_ni_una_fila(): void
    {
        Queue::fake();

        $antes = [PracticeAttempt::count(), ObjectiveMastery::count(), User::count()];

        foreach (range(1, 3) as $intento) {
            $this->getJson("/api/v1/objectives/{$this->objective->id}/practice/next?intento={$intento}")
                ->assertOk();
            $this->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', [
                'answer_key' => 'b',
                'intento' => $intento,
            ])->assertOk()->assertJsonPath('se_guarda', false);
        }

        $this->assertSame($antes, [PracticeAttempt::count(), ObjectiveMastery::count(), User::count()],
            'Un invitado escribió filas respondiendo un choice: la regla de oro está rota.');
        Queue::assertNothingPushed();
    }

    public function test_con_sesion_el_choice_si_persiste_y_mueve_el_dominio(): void
    {
        $this->actingAs($this->ana);

        $this->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', ['answer_key' => 'b'])
            ->assertCreated()
            ->assertJsonPath('is_correct', true)
            ->assertJsonPath('se_guarda', true);

        $this->assertDatabaseHas('practice_attempts', [
            'item_id' => self::ITEM_ID,
            'user_id' => $this->ana->id,
            'answer_key' => 'b',
            'is_correct' => true,
        ]);

        // INVARIANTE del esquema: en un choice la vía numérica queda VACÍA, no
        // rellena con 0.0. Un cero fingido escondería un bug de bifurcación.
        $this->assertDatabaseHas('practice_attempts', [
            'item_id' => self::ITEM_ID, 'answer' => null, 'expected' => null,
        ]);

        $dominio = ObjectiveMastery::where('user_id', $this->ana->id)->first();
        $this->assertNotNull($dominio);
        $this->assertGreaterThan(0, (float) $dominio->mastery);
    }

    /**
     * Lo que sirve `next` es lo que corrige `attempts`, por el camino REAL de
     * la API. Con la respuesta por clave esto no puede fallar aunque el orden
     * de pintado cambie entre las dos peticiones — que es exactamente el
     * motivo de responder por clave y no por posición.
     */
    public function test_lo_que_sirve_next_es_lo_que_corrige_el_attempt(): void
    {
        $servido = $this->getJson("/api/v1/objectives/{$this->objective->id}/practice/next")
            ->assertOk()->json();

        $veredictos = [];
        foreach ($servido['options'] as $opcion) {
            $veredictos[] = [
                'key' => $opcion['key'],
                'text' => $opcion['text']['es'],
            ] + $this->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', [
                'answer_key' => $opcion['key'],
            ])->assertOk()->json();
        }

        // Exactamente UNA de las opciones servidas es la correcta…
        $correctas = array_values(array_filter($veredictos, fn ($v) => $v['is_correct']));
        $this->assertCount(1, $correctas, 'Ninguna o más de una opción servida es correcta.');

        // …y es la que el servidor señala, igual en las cuatro respuestas.
        foreach ($veredictos as $v) {
            $this->assertSame($correctas[0]['key'], $v['expected_key']);
            $this->assertSame($v['key'] === $v['expected_key'], $v['is_correct']);
        }

        // Y la que resultó correcta es, de verdad, la opción buena del ítem.
        $this->assertSame('Montaña', $correctas[0]['text']);
    }

    // ============ ORÁCULO 5 — el camino numérico, intacto ============

    public function test_el_item_numerico_sigue_comportandose_igual(): void
    {
        $numerico = PracticeItem::create([
            'objective_id' => $this->objective->id,
            'seq' => 1,
            'statement' => ['es' => 'Suma {a} + {b}'],
            'params' => ['a' => ['const' => 2], 'b' => ['const' => 3]],
            'solution_expr' => 'a + b',
            'tolerance' => 0.01, 'tolerance_kind' => 'abs',
        ]);

        // Sin declarar `kind`, un ítem es numérico: los 17 del banco viejo no
        // se tocaron y tienen que seguir funcionando igual.
        $this->assertSame('numeric', $numerico->fresh()->kind);

        $this->postJson("/api/v1/practice/items/{$numerico->id}/attempts", ['answer' => 5])
            ->assertOk()
            ->assertJsonPath('is_correct', true)
            ->assertJsonPath('expected', 5);

        $this->postJson("/api/v1/practice/items/{$numerico->id}/attempts", ['answer' => 99])
            ->assertOk()
            ->assertJsonPath('is_correct', false);

        // Y un numérico no acepta que le respondan con una posición.
        $this->postJson("/api/v1/practice/items/{$numerico->id}/attempts", ['answer_key' => '1'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('answer');
    }
}
