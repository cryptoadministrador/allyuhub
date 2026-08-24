<?php

namespace Tests\Feature;

use App\Jobs\PushLtiScore;
use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\ObjectiveMastery;
use App\Models\PracticeAttempt;
use App\Models\PracticeItem;
use App\Models\Resource;
use App\Models\ResourceVersion;
use App\Models\User;
use App\Services\Lesson\Bloques;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * AUDIO — la pieza sin la que no hay curso de idiomas.
 *
 * En chino, `mā má mǎ mà` son cuatro palabras distintas y sobre el papel son la
 * misma sílaba: sin oír, no hay nada que aprender. Este fichero fija las
 * propiedades del bloque `audio` de las lecciones y del tipo de ítem `escucha`.
 *
 * Cuatro reglas que no se negocian:
 *
 *  1. La TRANSCRIPCIÓN existe siempre (accesibilidad y pedagogía A1), pero en
 *     un ítem se revela DESPUÉS de responder: si se ve antes, el ejercicio de
 *     escucha no existe.
 *  2. El audio no viaja en el JSON ni viene de terceros: ruta propia con nombre
 *     derivado del contenido, servida con caché inmutable.
 *  3. `escucha` reutiliza la mecánica de `choice` entera: clave inmutable,
 *     corrección por clave, billete firmado, barajado solo de pintado.
 *  4. La regla de oro y la puerta de firma aplican igual que a todo lo demás.
 */
class EscuchaTest extends TestCase
{
    use RefreshDatabase;

    private const ITEM_ID = '0198e2c0-aaaa-7000-8000-0000000000e1';

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
            'native_code' => 'g11', 'title' => ['es' => '1.º BGU'], 'path' => 'bgu.g11',
        ]);
        $this->objective = LearningObjective::create([
            'node_id' => $nodo->id, 'version_id' => $version->id,
            'native_code' => 'EXT.FR.1.1.1',
            'statement' => ['es' => 'Comprender saludos y presentaciones muy básicas.'],
            'is_verified' => true,
        ]);

        // El ítem de escucha: se oye un saludo y se elige qué significa.
        // La transcripción lleva un CENTINELA inconfundible y sin acentos
        // (Inertia/JSON escapan unicode: un centinela acentuado nunca se
        // encontraría, mintiera o no el test — lección de #25).
        PracticeItem::create([
            'id' => self::ITEM_ID,
            'objective_id' => $this->objective->id,
            'kind' => 'escucha',
            'statement' => ['es' => 'Escucha el saludo. ¿Qué dice?'],
            'params' => [],
            'options' => [
                ['key' => 'a', 'text' => ['es' => 'Buenos días']],
                ['key' => 'b', 'text' => ['es' => 'Buenas noches']],
                ['key' => 'c', 'text' => ['es' => 'Hasta luego']],
            ],
            'answer_key' => 'a',
            'audio_src' => '/audio/aabbccddeeff0011.mp3',
            'transcripcion' => 'CENTINELA-BONJOUR-TRANSCRIPCION',
            'shuffle' => true,
            'reviewed_at' => now(),
        ]);

        $this->ana = User::factory()->create();
    }

    // ========== ORÁCULO 3 — el bloque malo revienta al sembrar ==========

    public function test_un_bloque_audio_sin_transcripcion_revienta(): void
    {
        foreach ([
            ['tipo' => 'audio', 'src' => '/audio/aabbccddeeff0011.mp3'],
            ['tipo' => 'audio', 'src' => '/audio/aabbccddeeff0011.mp3', 'texto' => []],
            ['tipo' => 'audio', 'src' => '/audio/aabbccddeeff0011.mp3', 'texto' => ['fr' => '   ']],
        ] as $malo) {
            try {
                (new Bloques)->validar([$malo], 'FR.U1');
                $this->fail('Pasó un audio sin transcripción: '.json_encode($malo));
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('FR.U1', $e->getMessage());
            }
        }
    }

    public function test_un_bloque_audio_con_src_ajeno_revienta(): void
    {
        foreach ([
            'https://cdn.ajeno.test/clip.mp3',   // terceros: un rastreador esperando
            'javascript:alert(1)',                // el agujero de siempre
            '/img/clip.mp3',                      // fuera de la ruta de audio
            '/audio/../secreto.mp3',              // path traversal
            '/audio/clip.exe',                    // extensión fuera de la lista
        ] as $src) {
            try {
                (new Bloques)->validar([
                    ['tipo' => 'audio', 'src' => $src, 'texto' => ['fr' => 'Bonjour']],
                ], 'FR.U1');
                $this->fail("Pasó un src ajeno: {$src}");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('FR.U1', $e->getMessage());
            }
        }
    }

    public function test_un_bloque_audio_valido_se_normaliza(): void
    {
        $bloques = (new Bloques)->validar([
            ['tipo' => 'audio', 'src' => '/audio/aabbccddeeff0011.mp3',
                'texto' => ['fr' => 'Bonjour !', 'es' => 'Buenos días'], 'duracion_s' => 3],
        ]);

        $this->assertSame('audio', $bloques[0]['tipo']);
        $this->assertSame('/audio/aabbccddeeff0011.mp3', $bloques[0]['src']);
        $this->assertSame('Bonjour !', $bloques[0]['texto']['fr']);
        $this->assertSame(3, $bloques[0]['duracion_s']);
    }

    // ========== ORÁCULO 4 — la transcripción no se sirve antes de responder ==========

    /**
     * Como el oráculo de no-filtración de #25: se busca el TEXTO en el cuerpo
     * serializado COMPLETO, no un nombre de campo. Y con control positivo al
     * final — un oráculo que nunca puede encontrar su centinela pasa en verde
     * mintiendo.
     */
    public function test_next_no_sirve_la_transcripcion_ni_la_respuesta(): void
    {
        $cuerpo = $this->getJson("/api/v1/objectives/{$this->objective->id}/practice/next")
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('CENTINELA-BONJOUR-TRANSCRIPCION', $cuerpo,
            'La transcripción viajó antes de responder: el ejercicio de escucha no existe.');
        $this->assertStringNotContainsString('transcripcion', $cuerpo);
    }

    /** El payload de `escucha` es una LISTA CERRADA: cualquier campo nuevo cae aquí. */
    public function test_el_payload_de_escucha_es_lista_blanca_cerrada(): void
    {
        $json = $this->getJson("/api/v1/objectives/{$this->objective->id}/practice/next")
            ->assertOk()->json();

        $this->assertSame('escucha', $json['kind']);
        $this->assertSame([
            'item_id', 'kind', 'objective_id', 'objective_code', 'objective_statement',
            'attempt_no', 'billete', 'statement', 'options', 'audio_src', 'reason', 'se_guarda',
        ], array_keys($json),
            'El payload de escucha cambió: cada campo nuevo es una vía de fuga.');

        $this->assertSame('/audio/aabbccddeeff0011.mp3', $json['audio_src']);
        // Cada opción trae SOLO clave y texto, como en choice.
        foreach ($json['options'] as $opcion) {
            $this->assertSame(['key', 'text'], array_keys($opcion));
        }
    }

    /** Y DESPUÉS de responder, la transcripción llega: es pedagogía, no un secreto. */
    public function test_responder_revela_la_transcripcion(): void
    {
        $servido = $this->getJson("/api/v1/objectives/{$this->objective->id}/practice/next")
            ->assertOk()->json();

        $veredicto = $this->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', [
            'answer_key' => 'b',   // fallo a propósito: también al fallar se lee
            'billete' => $servido['billete'],
        ])->assertOk()->json();

        $this->assertFalse($veredicto['is_correct']);
        $this->assertSame('a', $veredicto['expected_key']);
        // El control positivo del oráculo de arriba: el centinela SÍ aparece
        // cuando toca, así que la búsqueda de no-filtración funciona.
        $this->assertSame('CENTINELA-BONJOUR-TRANSCRIPCION', $veredicto['transcripcion']);
    }

    /**
     * El cinturón del descuido: un `toArray()` del modelo en cualquier
     * respuesta futura tampoco filtra la transcripción. La defensa de verdad
     * es la lista blanca de `next`; esto cierra el mismo descuido que
     * `$hidden` ya cierra para `answer_key` y `solution_expr`.
     */
    public function test_ni_un_toarray_del_modelo_filtra_la_transcripcion(): void
    {
        $volcado = json_encode(PracticeItem::findOrFail(self::ITEM_ID)->toArray());

        $this->assertStringNotContainsString('CENTINELA-BONJOUR-TRANSCRIPCION', $volcado);
        $this->assertStringNotContainsString('answer_key', $volcado);
    }

    // ========== la mecánica de choice, entera ==========

    public function test_corrige_por_clave_con_billete_como_choice(): void
    {
        $servido = $this->actingAs($this->ana)
            ->getJson("/api/v1/objectives/{$this->objective->id}/practice/next")
            ->assertOk()->json();

        $this->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', [
            'answer_key' => 'a', 'billete' => $servido['billete'],
        ])->assertCreated()
            ->assertJsonPath('is_correct', true)
            ->assertJsonPath('se_guarda', true);

        $this->assertDatabaseHas('practice_attempts', [
            'item_id' => self::ITEM_ID, 'user_id' => $this->ana->id,
            'answer_key' => 'a', 'is_correct' => true,
        ]);
    }

    public function test_una_clave_inventada_es_422_no_un_falso_incorrecto(): void
    {
        $this->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', [
            'answer_key' => 'z', 'billete' => $this->billete(self::ITEM_ID),
        ])->assertStatus(422)->assertJsonValidationErrors('answer_key');
    }

    public function test_no_acepta_la_via_de_respuesta_numerica(): void
    {
        $this->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', [
            'answer' => 3, 'billete' => $this->billete(self::ITEM_ID),
        ])->assertStatus(422)->assertJsonValidationErrors('answer_key');
    }

    // ========== ORÁCULO 5 — la regla de oro ==========

    /**
     * > Un invitado no escribe NI UNA FILA atribuida a un usuario
     * > —practice_attempts, objective_masteries, users— ni encola PushLtiScore.
     */
    public function test_el_invitado_escucha_responde_y_no_deja_rastro(): void
    {
        Queue::fake();

        $antes = [PracticeAttempt::count(), ObjectiveMastery::count(), User::count()];

        $servido = $this->getJson("/api/v1/objectives/{$this->objective->id}/practice/next")
            ->assertOk()
            ->assertJsonPath('se_guarda', false)
            ->json();

        $this->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', [
            'answer_key' => 'a', 'billete' => $servido['billete'],
        ])->assertOk()
            ->assertJsonPath('is_correct', true)
            ->assertJsonPath('se_guarda', false);

        $this->assertSame($antes, [PracticeAttempt::count(), ObjectiveMastery::count(), User::count()],
            'Un invitado que escucha escribió una fila.');
        Queue::assertNotPushed(PushLtiScore::class);
    }

    // ========== ORÁCULO 6 — la puerta de firma ==========

    public function test_una_leccion_generada_con_audio_y_sin_firmar_no_se_sirve(): void
    {
        $recurso = Resource::create([
            'slug' => 'leccion-fr-saludos', 'kind' => Resource::LECTURA,
            'origen' => Resource::GENERADO, 'status' => 'published',
            'title' => ['es' => 'Saludos en francés'],
        ]);
        $version = ResourceVersion::create([
            'resource_id' => $recurso->id, 'semver' => '1.0.0',
            'config' => ['bloques' => (new Bloques)->validar([
                ['tipo' => 'parrafo', 'texto' => ['es' => 'CENTINELA DE AUDIO SIN FIRMAR']],
                ['tipo' => 'audio', 'src' => '/audio/aabbccddeeff0011.mp3',
                    'texto' => ['fr' => 'Bonjour']],
            ])],
            'published_at' => now(),
            'reviewed_at' => null,
        ]);
        $recurso->update(['current_version_id' => $version->id]);

        $this->get("/recurso/{$recurso->id}")->assertNotFound();

        // Control positivo: firmada, se sirve — la puerta no es un muro.
        $version->update(['reviewed_at' => now()]);
        $this->assertStringContainsString('CENTINELA DE AUDIO SIN FIRMAR',
            $this->get("/recurso/{$recurso->id}")->assertOk()->getContent());
    }

    // ========== ORÁCULO 9 — no regresión ==========

    /** `choice` no sabe nada del audio: su payload no crece. */
    public function test_choice_sigue_exactamente_igual(): void
    {
        PracticeItem::whereKey(self::ITEM_ID)->update([
            'kind' => 'choice', 'audio_src' => null, 'transcripcion' => null,
        ]);

        $json = $this->getJson("/api/v1/objectives/{$this->objective->id}/practice/next")
            ->assertOk()->json();

        $this->assertSame([
            'item_id', 'kind', 'objective_id', 'objective_code', 'objective_statement',
            'attempt_no', 'billete', 'statement', 'options', 'reason', 'se_guarda',
        ], array_keys($json));
    }
}
