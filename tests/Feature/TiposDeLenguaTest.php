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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * LOS CUATRO TIPOS DE LENGUA: hueco, orden, pares y dictado.
 *
 * Tres reglas estructurales, heredadas de tres cicatrices:
 *
 *  1. LA SOLUCIÓN VIVE EN `solucion` (jsonb, en $hidden) y jamás se serializa.
 *     El payload de `next` es lista blanca campo a campo, y el oráculo busca
 *     el TEXTO de la solución en el cuerpo completo — no un nombre de campo
 *     (#22), y con centinelas SIN acentos (#25).
 *  2. LA RESPUESTA VIAJA POR ID INMUTABLE O POR TEXTO, nunca por posición
 *     pintada: el barajado no participa en la corrección por construcción.
 *  3. LA FORMA DE `solucion` LA VALIDA EL GUARDIÁN `saving` (#26): un ítem
 *     mal formado revienta al guardarse, no en la pantalla de un alumno.
 */
class TiposDeLenguaTest extends TestCase
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
            'native_code' => 'g11', 'title' => ['es' => '1.º BGU'], 'path' => 'bgu.g11',
        ]);
        $this->objective = LearningObjective::create([
            'node_id' => $nodo->id, 'version_id' => $version->id,
            'native_code' => 'EXT.FR.1.1.2',
            'statement' => ['es' => 'Producir frases muy básicas en presente.'],
            'is_verified' => true,
        ]);

        $this->ana = User::factory()->create();
    }

    // ================= fixtures =================

    /** Un hueco de francés: los acentos importan y hay dos formas aceptadas. */
    private function hueco(): PracticeItem
    {
        return PracticeItem::create([
            'objective_id' => $this->objective->id, 'kind' => 'hueco',
            'statement' => ['es' => 'Completa: « Tu habites ___ ? » (dónde)'],
            'params' => [],
            // Centinela SIN acentos dentro de las formas aceptadas: si algún
            // día viaja en un payload, la búsqueda lo encuentra seguro.
            'solucion' => ['lengua' => 'fr', 'textos' => ['où', 'CENTINELA-HUECO-SOLUCION']],
            'seq' => 0, 'reviewed_at' => now(),
        ]);
    }

    /** Un orden de alemán con DOS órdenes válidos (V2: el verbo fijo, resto móvil). */
    private function orden(): PracticeItem
    {
        return PracticeItem::create([
            'objective_id' => $this->objective->id, 'kind' => 'orden',
            'statement' => ['es' => 'Ordena: mañana / voy / yo / al colegio (en alemán)'],
            'params' => [],
            'options' => [
                ['key' => 'w1', 'text' => ['de' => 'morgen']],
                ['key' => 'w2', 'text' => ['de' => 'gehe']],
                ['key' => 'w3', 'text' => ['de' => 'ich']],
                ['key' => 'w4', 'text' => ['de' => 'zur Schule']],
            ],
            'solucion' => ['secuencias' => [
                ['w3', 'w2', 'w1', 'w4'],   // Ich gehe morgen zur Schule
                ['w1', 'w2', 'w3', 'w4'],   // Morgen gehe ich zur Schule
            ]],
            'seq' => 1, 'reviewed_at' => now(),
        ]);
    }

    /** Pares de chino: TRES columnas — carácter, pinyin, significado. */
    private function pares(): PracticeItem
    {
        return PracticeItem::create([
            'objective_id' => $this->objective->id, 'kind' => 'pares',
            'statement' => ['es' => 'Empareja cada carácter con su pinyin y su significado.'],
            'params' => [],
            'options' => [
                ['key' => 'c1', 'col' => 'a', 'text' => ['zh' => '你']],
                ['key' => 'c2', 'col' => 'a', 'text' => ['zh' => '好']],
                ['key' => 'p1', 'col' => 'b', 'text' => ['zh' => 'ni3']],
                ['key' => 'p2', 'col' => 'b', 'text' => ['zh' => 'hao3']],
                ['key' => 's1', 'col' => 'c', 'text' => ['es' => 'tu / usted']],
                ['key' => 's2', 'col' => 'c', 'text' => ['es' => 'bien / bueno']],
            ],
            'solucion' => ['parejas' => [['c1', 'p1', 's1'], ['c2', 'p2', 's2']]],
            'seq' => 2, 'reviewed_at' => now(),
        ]);
    }

    /** Un dictado de italiano sobre el audio de #26. */
    private function dictado(): PracticeItem
    {
        return PracticeItem::create([
            'objective_id' => $this->objective->id, 'kind' => 'dictado',
            'statement' => ['es' => 'Escucha y escribe exactamente lo que oyes.'],
            'params' => [],
            'audio_src' => '/audio/aabbccddeeff0011.mp3',
            'transcripcion' => 'CENTINELA-DICTADO-TRANSCRIPCION',
            'solucion' => ['lengua' => 'it', 'textos' => ['perché no?']],
            'seq' => 3, 'reviewed_at' => now(),
        ]);
    }

    private function next(): array
    {
        return $this->getJson("/api/v1/objectives/{$this->objective->id}/practice/next")
            ->assertOk()->json();
    }

    private function responder(PracticeItem $item, array $respuesta, string $billete)
    {
        return $this->postJson("/api/v1/practice/items/{$item->id}/attempts", [
            'respuesta' => $respuesta, 'billete' => $billete,
        ]);
    }

    // ========== ORÁCULO 1 — la solución no se filtra, un test por tipo ==========

    public function test_next_de_hueco_no_sirve_la_solucion(): void
    {
        $this->hueco();
        $cuerpo = $this->getJson("/api/v1/objectives/{$this->objective->id}/practice/next")
            ->assertOk()->getContent();

        $this->assertStringNotContainsString('CENTINELA-HUECO-SOLUCION', $cuerpo);
        $this->assertStringNotContainsString('solucion', $cuerpo);
    }

    public function test_next_de_dictado_no_sirve_ni_solucion_ni_transcripcion(): void
    {
        $this->dictado();
        $cuerpo = $this->getJson("/api/v1/objectives/{$this->objective->id}/practice/next")
            ->assertOk()->getContent();

        $this->assertStringNotContainsString('perch', $cuerpo);   // sin acentos, como #25
        $this->assertStringNotContainsString('CENTINELA-DICTADO-TRANSCRIPCION', $cuerpo);
        $this->assertStringNotContainsString('solucion', $cuerpo);
    }

    public function test_next_de_orden_sirve_las_palabras_pero_no_las_secuencias(): void
    {
        $this->orden();
        $json = $this->next();

        // Las palabras SÍ viajan (sin ellas no hay ejercicio), cada una con su
        // id inmutable y su texto — y NADA más.
        $this->assertCount(4, $json['options']);
        foreach ($json['options'] as $opcion) {
            $this->assertSame(['key', 'text'], array_keys($opcion));
        }

        // Las secuencias válidas, jamás. Se busca la FORMA serializada de una
        // secuencia entera, no un nombre de campo.
        $cuerpo = json_encode($json);
        $this->assertStringNotContainsString('"w3","w2","w1","w4"', $cuerpo);
        $this->assertStringNotContainsString('secuencias', $cuerpo);
        $this->assertStringNotContainsString('solucion', $cuerpo);
    }

    public function test_next_de_pares_sirve_columnas_pero_no_parejas(): void
    {
        $this->pares();
        $json = $this->next();

        $this->assertCount(6, $json['options']);
        foreach ($json['options'] as $opcion) {
            // La columna SÍ viaja: el cliente pinta tres columnas. La pareja no.
            $this->assertSame(['key', 'col', 'text'], array_keys($opcion));
        }

        $cuerpo = json_encode($json);
        $this->assertStringNotContainsString('parejas', $cuerpo);
        $this->assertStringNotContainsString('solucion', $cuerpo);
    }

    /** El control positivo de los cuatro: tras responder, la solución SÍ llega. */
    public function test_responder_revela_lo_esperado_en_los_cuatro_tipos(): void
    {
        $hueco = $this->hueco();
        $v = $this->responder($hueco, ['texto' => 'no-es'], $this->billete($hueco->id))
            ->assertOk()->json();
        $this->assertSame('où', $v['esperado']);

        $dictado = $this->dictado();
        $v = $this->responder($dictado, ['texto' => 'no'], $this->billete($dictado->id))
            ->assertOk()->json();
        $this->assertSame('CENTINELA-DICTADO-TRANSCRIPCION', $v['transcripcion']);

        $orden = $this->orden();
        $v = $this->responder($orden, ['ids' => ['w4', 'w3', 'w2', 'w1']], $this->billete($orden->id))
            ->assertOk()->json();
        $this->assertSame(['w3', 'w2', 'w1', 'w4'], $v['secuencia_correcta']);

        $pares = $this->pares();
        $v = $this->responder($pares, ['parejas' => [['c1', 'p2', 's1'], ['c2', 'p1', 's2']]],
            $this->billete($pares->id))->assertOk()->json();
        $this->assertSame([['c1', 'p1', 's1'], ['c2', 'p2', 's2']], $v['parejas_esperadas']);
    }

    /** Y las listas blancas CERRADAS de los cuatro payloads. */
    public function test_los_payloads_de_next_son_listas_cerradas(): void
    {
        $base = ['item_id', 'kind', 'objective_id', 'objective_code',
            'objective_statement', 'attempt_no', 'billete', 'statement'];
        $cola = ['reason', 'se_guarda'];

        foreach ([
            'hueco' => [$this->hueco(), []],
            'orden' => [$this->orden(), ['options']],
            'pares' => [$this->pares(), ['options']],
            'dictado' => [$this->dictado(), ['audio_src']],
        ] as $kind => [$item, $propios]) {
            $json = $this->next();
            $this->assertSame($kind, $json['kind']);
            $this->assertSame([...$base, ...$propios, ...$cola], array_keys($json),
                "El payload de {$kind} cambió: cada campo nuevo es una vía de fuga.");
            $item->delete();
        }
    }

    /** El cinturón de $hidden: ni un toArray() suelto filtra la solución. */
    public function test_ni_un_toarray_del_modelo_filtra_la_solucion(): void
    {
        $volcado = json_encode($this->hueco()->fresh()->toArray());

        $this->assertStringNotContainsString('CENTINELA-HUECO-SOLUCION', $volcado);
        $this->assertStringNotContainsString('solucion', $volcado);
    }

    // ========== ORÁCULO 2 — la posición pintada no corrige ==========

    /**
     * Se responde por ID INMUTABLE: da igual cómo se barajó al pintar. El test
     * baraja A PROPÓSITO pidiendo `next` (que baraja con la semilla del
     * intento) y responde con ids en un orden que no es el pintado: el
     * veredicto depende SOLO de los ids.
     */
    public function test_la_correccion_de_orden_no_depende_del_barajado(): void
    {
        $orden = $this->orden();

        // Dos intentos con barajados distintos (semilla distinta por intento):
        // la respuesta correcta POR IDS es la misma y acierta en los dos.
        foreach ([1, 2] as $intento) {
            $this->responder($orden, ['ids' => ['w3', 'w2', 'w1', 'w4']],
                $this->billete($orden->id, intento: $intento))
                ->assertOk()
                ->assertJsonPath('is_correct', true);
        }
    }

    public function test_la_correccion_de_pares_no_depende_del_barajado(): void
    {
        $pares = $this->pares();

        foreach ([1, 2] as $intento) {
            // El ORDEN de las parejas dentro de la lista tampoco importa: es
            // un conjunto.
            $this->responder($pares, ['parejas' => [['c2', 'p2', 's2'], ['c1', 'p1', 's1']]],
                $this->billete($pares->id, intento: $intento))
                ->assertOk()
                ->assertJsonPath('is_correct', true);
        }
    }

    // ========== ORÁCULO 3 — un id inventado es 422 ==========

    public function test_un_id_que_no_estaba_entre_los_servidos_es_422(): void
    {
        $orden = $this->orden();
        $this->responder($orden, ['ids' => ['w3', 'w2', 'w1', 'INVENTADO']],
            $this->billete($orden->id))->assertStatus(422);

        // Un id repetido tampoco es un «incorrecto»: es un cliente roto.
        $this->responder($orden, ['ids' => ['w3', 'w3', 'w1', 'w4']],
            $this->billete($orden->id))->assertStatus(422);

        // Y una secuencia incompleta, lo mismo.
        $this->responder($orden, ['ids' => ['w3', 'w2']],
            $this->billete($orden->id))->assertStatus(422);

        $pares = $this->pares();
        $this->responder($pares, ['parejas' => [['c1', 'p1', 'INVENTADO'], ['c2', 'p2', 's2']]],
            $this->billete($pares->id))->assertStatus(422);

        // Una pareja con dos claves de la MISMA columna es estructura rota.
        $this->responder($pares, ['parejas' => [['c1', 'c2', 's1'], ['p1', 'p2', 's2']]],
            $this->billete($pares->id))->assertStatus(422);
    }

    // ========== ORÁCULO 4 — cada tipo rechaza la vía de los otros ==========

    public function test_cada_tipo_rechaza_la_forma_de_respuesta_de_los_otros(): void
    {
        $hueco = $this->hueco();
        // Un hueco no se responde con número ni con clave.
        $this->postJson("/api/v1/practice/items/{$hueco->id}/attempts", [
            'answer' => 3, 'billete' => $this->billete($hueco->id),
        ])->assertStatus(422)->assertJsonValidationErrors('answer');
        $this->postJson("/api/v1/practice/items/{$hueco->id}/attempts", [
            'answer_key' => 'a', 'billete' => $this->billete($hueco->id),
        ])->assertStatus(422)->assertJsonValidationErrors('answer_key');

        // Y un choice no se responde con `respuesta`.
        $choice = PracticeItem::create([
            'objective_id' => $this->objective->id, 'kind' => 'choice',
            'statement' => ['es' => 'Elige.'], 'params' => [],
            'options' => [['key' => 'a', 'text' => ['es' => 'x']], ['key' => 'b', 'text' => ['es' => 'y']]],
            'answer_key' => 'a', 'seq' => 9, 'reviewed_at' => now(),
        ]);
        $this->postJson("/api/v1/practice/items/{$choice->id}/attempts", [
            'respuesta' => ['texto' => 'x'], 'answer_key' => 'a',
            'billete' => $this->billete($choice->id),
        ])->assertStatus(422)->assertJsonValidationErrors('respuesta');
    }

    // ========== ORÁCULO 5 — el guardián saving ==========

    public function test_una_solucion_mal_formada_revienta_al_guardar(): void
    {
        foreach ([
            // hueco sin textos
            ['kind' => 'hueco', 'solucion' => ['lengua' => 'fr', 'textos' => []]],
            // hueco sin lengua (la normalización es POR LENGUA, no global)
            ['kind' => 'hueco', 'solucion' => ['textos' => ['où']]],
            // hueco sin solucion ninguna
            ['kind' => 'hueco', 'solucion' => null],
            // orden cuya secuencia no es permutación de las palabras
            ['kind' => 'orden',
                'options' => [['key' => 'a', 'text' => ['de' => 'x']], ['key' => 'b', 'text' => ['de' => 'y']]],
                'solucion' => ['secuencias' => [['a']]]],
            // orden con una clave fantasma en la secuencia
            ['kind' => 'orden',
                'options' => [['key' => 'a', 'text' => ['de' => 'x']], ['key' => 'b', 'text' => ['de' => 'y']]],
                'solucion' => ['secuencias' => [['a', 'z']]]],
            // pares cuya pareja no cubre todas las columnas
            ['kind' => 'pares',
                'options' => [
                    ['key' => 'a1', 'col' => 'a', 'text' => ['zh' => 'x']],
                    ['key' => 'b1', 'col' => 'b', 'text' => ['es' => 'y']],
                ],
                'solucion' => ['parejas' => [['a1']]]],
            // dictado sin audio
            ['kind' => 'dictado', 'solucion' => ['lengua' => 'it', 'textos' => ['ciao']]],
        ] as $malo) {
            try {
                PracticeItem::create([
                    'objective_id' => $this->objective->id,
                    'statement' => ['es' => 'x'], 'params' => [], 'seq' => 50,
                    ...$malo,
                ]);
                $this->fail('Se guardó un ítem mal formado: '.json_encode($malo));
            } catch (InvalidArgumentException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }

    /** Y cubre el UPDATE, no solo el create: el guardián vive en saving. */
    public function test_el_guardian_cubre_el_update(): void
    {
        $hueco = $this->hueco();

        $this->expectException(InvalidArgumentException::class);
        $hueco->update(['solucion' => ['lengua' => 'fr', 'textos' => []]]);
    }

    /** Un kind que no existe tampoco se guarda: el vocabulario es cerrado. */
    public function test_un_kind_desconocido_revienta_al_guardar(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // El mensaje NOMBRA el kind: sin esto, el test pasaba con un registro
        // abierto porque «karaoke» sin solution_expr reventaba por OTRA razón
        // (el guardián de numeric) — verde por accidente, no por la propiedad.
        // Lo cazó una mutación que devolvía TipoNumerico para lo desconocido.
        $this->expectExceptionMessageMatches('/karaoke/');

        PracticeItem::create([
            'objective_id' => $this->objective->id, 'kind' => 'karaoke',
            'statement' => ['es' => 'x'], 'params' => [], 'seq' => 60,
        ]);
    }

    // ========== ORÁCULO 6 — orden con varias soluciones, las dos mitades ==========

    public function test_orden_acepta_todas_las_secuencias_validas_y_solo_esas(): void
    {
        $orden = $this->orden();

        // Las dos válidas se aceptan…
        $this->responder($orden, ['ids' => ['w3', 'w2', 'w1', 'w4']], $this->billete($orden->id))
            ->assertOk()->assertJsonPath('is_correct', true);
        $this->responder($orden, ['ids' => ['w1', 'w2', 'w3', 'w4']],
            $this->billete($orden->id, intento: 2))
            ->assertOk()->assertJsonPath('is_correct', true);

        // …y una permutación PARECIDA que no está en el conjunto es error.
        // Sin esta mitad, un corrector que dice que sí a todo pasa en verde.
        $this->responder($orden, ['ids' => ['w2', 'w3', 'w1', 'w4']],
            $this->billete($orden->id, intento: 3))
            ->assertOk()->assertJsonPath('is_correct', false);
    }

    // ========== ORÁCULO 7 — la normalización de hueco, con sus dos mensajes ==========

    public function test_hueco_perdona_mayusculas_y_espacios_pero_no_acentos(): void
    {
        $hueco = $this->hueco();

        // Mayúsculas y espacios sobrantes: correcto sin más.
        $this->responder($hueco, ['texto' => '  OÙ  '], $this->billete($hueco->id))
            ->assertOk()
            ->assertJsonPath('is_correct', true)
            ->assertJsonPath('detalle', null);

        // Sin el acento: INCORRECTO, y el mensaje dice que es de acento.
        // En francés `ou` (o) y `où` (dónde) son dos palabras distintas.
        $v = $this->responder($hueco, ['texto' => 'ou'], $this->billete($hueco->id, intento: 2))
            ->assertOk()->json();
        $this->assertFalse($v['is_correct']);
        $this->assertSame('acento', $v['detalle']);

        // Una palabra sencillamente equivocada: error de palabra, no de acento.
        // El oráculo que impide que la tolerancia se pase de generosa.
        $v = $this->responder($hueco, ['texto' => 'quand'], $this->billete($hueco->id, intento: 3))
            ->assertOk()->json();
        $this->assertFalse($v['is_correct']);
        $this->assertSame('palabra', $v['detalle']);
    }

    public function test_la_normalizacion_es_por_lengua(): void
    {
        // El apóstrofo tipográfico se perdona en todas las lenguas.
        $fr = PracticeItem::create([
            'objective_id' => $this->objective->id, 'kind' => 'hueco',
            'statement' => ['es' => 'Completa: « Elle ___ Marie. »'],
            'params' => [],
            'solucion' => ['lengua' => 'fr', 'textos' => ["s'appelle"]],
            'seq' => 70, 'reviewed_at' => now(),
        ]);
        $this->responder($fr, ['texto' => 's’appelle'], $this->billete($fr->id))
            ->assertOk()->assertJsonPath('is_correct', true);

        // La ß alemana y su ss son la MISMA palabra en la ortografía alemana
        // (Suiza escribe ss siempre): decisión de la lengua, no del motor.
        $de = PracticeItem::create([
            'objective_id' => $this->objective->id, 'kind' => 'hueco',
            'statement' => ['es' => 'Completa: « Die ___ » (la calle)'],
            'params' => [],
            'solucion' => ['lengua' => 'de', 'textos' => ['Straße']],
            'seq' => 71, 'reviewed_at' => now(),
        ]);
        $this->responder($de, ['texto' => 'Strasse'], $this->billete($de->id))
            ->assertOk()->assertJsonPath('is_correct', true);

        // Y en francés una s doblada NO se convierte en ß ni se perdona: la
        // regla de la ß es del alemán.
        $this->responder($fr, ['texto' => "s'apelle"], $this->billete($fr->id, intento: 2))
            ->assertOk()->assertJsonPath('is_correct', false);
    }

    // ========== ORÁCULO 8 — pares: la regla de crédito, decidida y fijada ==========

    /**
     * DECISIÓN: el CRÉDITO es todo o nada; el VEREDICTO enseña el parcial.
     *
     * `is_correct` alimenta el dominio (EMA, racha, `mastered_at`) y la nota
     * AGS del aula: un «casi» que cuenta como acierto inflaría el dominio sin
     * que nadie lo decidiera. Lo que el alumno VE tras responder sí es parcial
     * —cuántas parejas clavó de cuántas— porque en A1 eso es lo que enseña.
     */
    public function test_pares_ensena_el_parcial_pero_el_credito_es_todo_o_nada(): void
    {
        $pares = $this->pares();

        // 1 pareja bien de 2: NO es acierto, y el veredicto lo cuenta.
        $v = $this->responder($pares, ['parejas' => [['c1', 'p1', 's1'], ['c2', 'p1', 's2']]],
            $this->billete($pares->id));
        // (c2,p1,s2) reutiliza p1 → estructura rota → 422, así que probamos
        // con una equivocación legal: cruzar p y s.
        $v = $this->responder($pares, ['parejas' => [['c1', 'p1', 's2'], ['c2', 'p2', 's1']]],
            $this->billete($pares->id))
            ->assertOk()->json();

        $this->assertFalse($v['is_correct']);
        $this->assertSame(0, $v['parejas_correctas']);
        $this->assertSame(2, $v['total']);

        // Las dos bien: acierto, 2 de 2.
        $v = $this->responder($pares, ['parejas' => [['c1', 'p1', 's1'], ['c2', 'p2', 's2']]],
            $this->billete($pares->id, intento: 2))
            ->assertOk()->json();
        $this->assertTrue($v['is_correct']);
        $this->assertSame(2, $v['parejas_correctas']);
    }

    /**
     * Y el PARCIAL DE VERDAD. Con dos filas y tres columnas, «1 de 2» es
     * imposible sin reutilizar claves (422), así que el test de arriba nunca
     * producía un parcial: solo 0 o todo — y una mutación `is_correct =
     * aciertos > 0` le pasaba por encima. Este fixture de tres filas produce
     * exactamente 1 de 3, que es lo que mata esa mutación.
     */
    public function test_un_parcial_real_no_cuenta_como_acierto(): void
    {
        $tres = PracticeItem::create([
            'objective_id' => $this->objective->id, 'kind' => 'pares',
            'statement' => ['es' => 'Empareja cada saludo con su momento del día.'],
            'params' => [],
            'options' => [
                ['key' => 'f1', 'col' => 'a', 'text' => ['fr' => 'bonjour']],
                ['key' => 'f2', 'col' => 'a', 'text' => ['fr' => 'bonsoir']],
                ['key' => 'f3', 'col' => 'a', 'text' => ['fr' => 'salut']],
                ['key' => 'm1', 'col' => 'b', 'text' => ['es' => 'por el día']],
                ['key' => 'm2', 'col' => 'b', 'text' => ['es' => 'por la noche']],
                ['key' => 'm3', 'col' => 'b', 'text' => ['es' => 'entre amigos']],
            ],
            'solucion' => ['parejas' => [['f1', 'm1'], ['f2', 'm2'], ['f3', 'm3']]],
            'seq' => 80, 'reviewed_at' => now(),
        ]);

        // La primera bien, las otras dos cruzadas: 1 de 3.
        $v = $this->responder($tres, ['parejas' => [['f1', 'm1'], ['f2', 'm3'], ['f3', 'm2']]],
            $this->billete($tres->id))
            ->assertOk()->json();

        $this->assertSame(1, $v['parejas_correctas']);
        $this->assertSame(3, $v['total']);
        $this->assertFalse($v['is_correct'],
            'Un parcial contó como acierto: inflaría el dominio y la nota del aula.');
    }

    // ========== ORÁCULO 9 — la regla de oro, en los cuatro ==========

    public function test_el_invitado_responde_los_cuatro_tipos_sin_dejar_rastro(): void
    {
        Queue::fake();

        $items = [
            [$this->hueco(), ['texto' => 'où']],
            [$this->orden(), ['ids' => ['w3', 'w2', 'w1', 'w4']]],
            [$this->pares(), ['parejas' => [['c1', 'p1', 's1'], ['c2', 'p2', 's2']]]],
            [$this->dictado(), ['texto' => 'perché no?']],
        ];

        $antes = [PracticeAttempt::count(), ObjectiveMastery::count(), User::count()];

        foreach ($items as [$item, $respuesta]) {
            $this->responder($item, $respuesta, $this->billete($item->id))
                ->assertOk()
                ->assertJsonPath('is_correct', true)
                ->assertJsonPath('se_guarda', false);
        }

        $this->assertSame($antes, [PracticeAttempt::count(), ObjectiveMastery::count(), User::count()],
            'Un invitado escribió una fila respondiendo un tipo de lengua.');
        Queue::assertNotPushed(PushLtiScore::class);
    }

    // ========== la persistencia del alumno: una vía por kind ==========

    public function test_el_alumno_persiste_por_la_via_respuesta_y_solo_esa(): void
    {
        $orden = $this->orden();

        $this->actingAs($this->ana)
            ->responder($orden, ['ids' => ['w3', 'w2', 'w1', 'w4']],
                $this->billeteComoNext($orden->id, $this->ana->id))
            ->assertCreated()
            ->assertJsonPath('is_correct', true)
            ->assertJsonPath('se_guarda', true);

        $fila = PracticeAttempt::where('user_id', $this->ana->id)->firstOrFail();

        // La vía de los tipos de lengua es `respuesta`; las otras dos, NULL.
        // Rellenarlas con '' o 0.0 escondería un bug de bifurcación.
        $this->assertSame(['ids' => ['w3', 'w2', 'w1', 'w4']], $fila->respuesta);
        $this->assertNull($fila->answer);
        $this->assertNull($fila->answer_key);
        $this->assertNull($fila->expected);
    }
}
