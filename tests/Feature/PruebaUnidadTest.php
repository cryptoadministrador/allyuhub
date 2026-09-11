<?php

namespace Tests\Feature;

use App\Jobs\PushLtiScore;
use App\Models\LearningObjective;
use App\Models\ObjectiveMastery;
use App\Models\PracticeAttempt;
use App\Models\PracticeItem;
use App\Models\PruebaUnidad;
use App\Models\User;
use App\Services\Practice\Tipos\Registro;
use Database\Seeders\CefrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * PR 8 · LA PRUEBA DE UNIDAD — el «unit test» de Khan.
 *
 * Diez ítems de corrido, nota al final, ≥ 8 aprueba y la unidad queda
 * «completada». Lo que estos oráculos fijan, en orden de importancia:
 *
 *  1. LA NOTA ES LA MISMA CORRECCIÓN. Cada tipo, correcto en práctica es
 *     correcto en la prueba, e incorrecto, incorrecto. Si la prueba tuviera su
 *     propio camino de corrección, un alumno sacaría dos notas de una respuesta.
 *  2. La prueba es JUSTA: misma semilla, misma prueba; otra, otra. Sin repetir
 *     ítem. Sin que un descriptor acapare mientras otros tengan ítems.
 *  3. No se filtra la solución: diez ítems sin `solucion`, recorriendo los
 *     kinds del Registro en las cuatro lenguas.
 *  4. Regla de oro: el invitado la hace entera, ve la nota y no escribe nada.
 *  5. Con sesión, cada respuesta ES un intento de práctica (dominio, AGS), y la
 *     prueba deja su fila. Aprobada → «completada» en el mapa. Suspendida → se
 *     repite con otra semilla.
 */
class PruebaUnidadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CefrSeeder::class);
    }

    // ================= fixtures =================

    private function objetivo(string $code): LearningObjective
    {
        return LearningObjective::where('native_code', $code)->firstOrFail();
    }

    /** N ítems `hueco` firmados de una lengua sobre un descriptor. */
    private function huecos(string $lengua, string $code, int $n, int $desde = 1): void
    {
        $obj = $this->objetivo($code)->id;
        foreach (range($desde, $desde + $n - 1) as $seq) {
            PracticeItem::create([
                'objective_id' => $obj, 'kind' => 'hueco', 'lengua' => $lengua,
                'statement' => ['es' => "Completa ({$lengua} {$code} #{$seq})."], 'params' => [],
                'solucion' => ['lengua' => $lengua, 'textos' => ['ciao']],
                'seq' => $seq, 'reviewed_at' => now(),
            ]);
        }
    }

    /**
     * UN ÍTEM DE CADA KIND del Registro, en una lengua y sobre un descriptor de
     * la U1, con su respuesta BUENA y una MALA, y sus secretos (centinelas SIN
     * acentos, por la serialización unicode de Inertia/JSON).
     *
     * @return array<string, array{item: PracticeItem, buena: array, mala: array, secretos: list<string>}>
     */
    private function unoDeCadaKind(string $lengua, string $code = 'A1.CO.2'): array
    {
        $obj = $this->objetivo($code)->id;
        $base = fn (string $kind, int $seq) => [
            'objective_id' => $obj, 'kind' => $kind, 'lengua' => $lengua,
            'params' => [], 'seq' => $seq, 'reviewed_at' => now(),
        ];
        $opciones = [['key' => 'a', 'text' => ['es' => 'una']], ['key' => 'b', 'text' => ['es' => 'otra']]];

        $todos = [
            PracticeItem::NUMERIC => [
                'item' => PracticeItem::create([...$base('numeric', 1),
                    'statement' => ['es' => 'Calcula el triple de {zz}'],
                    'params' => ['zz' => ['const' => 2]], 'solution_expr' => 'zz * 3',
                    'tolerance' => 0.01, 'tolerance_kind' => 'abs',
                ]),
                'buena' => ['answer' => 6], 'mala' => ['answer' => 999],
                'secretos' => ['solution_expr', 'zz * 3'],
            ],
            PracticeItem::CHOICE => [
                'item' => PracticeItem::create([...$base('choice', 2),
                    'statement' => ['es' => 'Elige la buena.'], 'options' => $opciones, 'answer_key' => 'a',
                    'attrs' => ['nota_interna' => 'CENTINELA-CHOICE-ATTRS'],
                ]),
                'buena' => ['answer_key' => 'a'], 'mala' => ['answer_key' => 'b'],
                'secretos' => ['answer_key', 'CENTINELA-CHOICE-ATTRS'],
            ],
            PracticeItem::ESCUCHA => [
                'item' => PracticeItem::create([...$base('escucha', 3),
                    'statement' => ['es' => 'Escucha y elige.'], 'options' => $opciones, 'answer_key' => 'a',
                    'audio_src' => '/audio/aabbccddeeff0011.mp3', 'transcripcion' => 'CENTINELA-ESCUCHA-TRANS',
                ]),
                'buena' => ['answer_key' => 'a'], 'mala' => ['answer_key' => 'b'],
                'secretos' => ['answer_key', 'CENTINELA-ESCUCHA-TRANS', 'transcripcion'],
            ],
            PracticeItem::HUECO => [
                'item' => PracticeItem::create([...$base('hueco', 4),
                    'statement' => ['es' => 'Completa el saludo.'],
                    'solucion' => ['lengua' => $lengua, 'textos' => ['CENTINELA-HUECO-SOL']],
                ]),
                'buena' => ['respuesta' => ['texto' => 'CENTINELA-HUECO-SOL']], 'mala' => ['respuesta' => ['texto' => 'zzz']],
                'secretos' => ['CENTINELA-HUECO-SOL', 'solucion'],
            ],
            PracticeItem::ORDEN => [
                'item' => PracticeItem::create([...$base('orden', 5),
                    'statement' => ['es' => 'Ordena.'],
                    'options' => [
                        ['key' => 'o1', 'text' => ['de' => 'eins']],
                        ['key' => 'o2', 'text' => ['de' => 'zwei']],
                        ['key' => 'o3', 'text' => ['de' => 'drei']],
                    ],
                    'solucion' => ['secuencias' => [['o2', 'o1', 'o3']]],
                ]),
                'buena' => ['respuesta' => ['ids' => ['o2', 'o1', 'o3']]], 'mala' => ['respuesta' => ['ids' => ['o1', 'o2', 'o3']]],
                'secretos' => ['"o2","o1","o3"', 'secuencias', 'solucion'],
            ],
            PracticeItem::PARES => [
                'item' => PracticeItem::create([...$base('pares', 6),
                    'statement' => ['es' => 'Empareja.'],
                    'options' => [
                        ['key' => 'x1', 'col' => 'a', 'text' => ['fr' => 'un']],
                        ['key' => 'x2', 'col' => 'a', 'text' => ['fr' => 'deux']],
                        ['key' => 'y1', 'col' => 'b', 'text' => ['es' => 'uno']],
                        ['key' => 'y2', 'col' => 'b', 'text' => ['es' => 'dos']],
                    ],
                    'solucion' => ['parejas' => [['x1', 'y1'], ['x2', 'y2']]],
                ]),
                'buena' => ['respuesta' => ['parejas' => [['x1', 'y1'], ['x2', 'y2']]]],
                'mala' => ['respuesta' => ['parejas' => [['x1', 'y2'], ['x2', 'y1']]]],
                'secretos' => ['"x1","y1"', 'parejas', 'solucion'],
            ],
            PracticeItem::DICTADO => [
                'item' => PracticeItem::create([...$base('dictado', 7),
                    'statement' => ['es' => 'Escribe lo que oyes.'],
                    'audio_src' => '/audio/aabbccddeeff0011.mp3', 'transcripcion' => 'CENTINELA-DICTADO-TRANS',
                    'solucion' => ['lengua' => $lengua, 'textos' => ['CENTINELA-DICTADO-SOL']],
                ]),
                'buena' => ['respuesta' => ['texto' => 'CENTINELA-DICTADO-SOL']], 'mala' => ['respuesta' => ['texto' => 'zzz']],
                'secretos' => ['CENTINELA-DICTADO-SOL', 'CENTINELA-DICTADO-TRANS', 'transcripcion', 'solucion'],
            ],
        ];

        // El oráculo recorre el REGISTRO: un kind nuevo sin fixture aquí no
        // nace sin prueba de unidad ni sin oráculo de no-filtración.
        foreach (Registro::kinds() as $kind) {
            $this->assertArrayHasKey($kind, $todos, "El kind «{$kind}» está en el Registro y no tiene fixture de prueba.");
        }

        return $todos;
    }

    /** Sirve la prueba y devuelve el JSON. */
    private function servir(string $lengua, int $unidad, int $intento = 1): array
    {
        return $this->getJson("/api/v1/pruebas/{$lengua}/u{$unidad}?intento={$intento}")
            ->assertOk()->json();
    }

    /** Las respuestas para entregar, eligiendo buena o mala por ítem. */
    private function respuestas(array $servida, array $fixtures, bool $buenas): array
    {
        $porId = collect($fixtures)->keyBy(fn ($f) => $f['item']->id);

        return collect($servida['items'])->map(fn ($it) => [
            'item_id' => $it['item_id'],
            'billete' => $it['billete'],
            ...($buenas ? $porId[$it['item_id']]['buena'] : $porId[$it['item_id']]['mala']),
        ])->all();
    }

    // ================= 1. la nota es la MISMA corrección =================

    /**
     * EL ORÁCULO CENTRAL DEL PR. Para cada kind del Registro: la respuesta
     * correcta en práctica es correcta en la prueba y la incorrecta,
     * incorrecta. Recorre los siete tipos en las DOS vías.
     */
    public function test_la_prueba_corrige_exactamente_igual_que_la_practica(): void
    {
        $fixtures = $this->unoDeCadaKind('it');

        // ---- vía práctica: un POST por ítem, con el billete de práctica ----
        foreach ([true, false] as $buena) {
            foreach ($fixtures as $kind => $f) {
                $intento = $buena ? 1 : 2;
                $r = $this->postJson("/api/v1/practice/items/{$f['item']->id}/attempts", [
                    ...($buena ? $f['buena'] : $f['mala']),
                    'billete' => $this->billete($f['item']->id, intento: $intento),
                ])->assertOk();
                $this->assertSame($buena, $r->json('is_correct'), "Práctica/{$kind}: la respuesta ".($buena ? 'buena' : 'mala').' no corrigió como debía.');
            }
        }

        // ---- vía prueba: diez de golpe, con el billete de la prueba ----
        foreach ([true => 3, false => 4] as $buenas => $intento) {
            $servida = $this->servir('it', 1, $intento);
            $this->assertSame(count($fixtures), $servida['total'], 'La prueba no sirvió un ítem de cada kind.');

            $r = $this->postJson('/api/v1/pruebas/it/u1', [
                'intento' => $intento,
                'respuestas' => $this->respuestas($servida, $fixtures, (bool) $buenas),
            ])->assertOk();

            foreach ($r->json('veredictos') as $v) {
                $kind = collect($fixtures)->first(fn ($f) => $f['item']->id === $v['item_id'])['item']->kind;
                $this->assertSame((bool) $buenas, $v['is_correct'], "Prueba/{$kind}: la respuesta ".($buenas ? 'buena' : 'mala').' no corrigió como en práctica.');
            }
            $this->assertSame($buenas ? count($fixtures) : 0, $r->json('nota'));
        }
    }

    // ================= 2. la prueba es JUSTA =================

    public function test_misma_semilla_misma_prueba_y_otra_semilla_otra(): void
    {
        // U1 = A1.CO.2, A1.IO.3, A1.CE.1: 10 ítems en cada descriptor.
        foreach (['A1.CO.2', 'A1.IO.3', 'A1.CE.1'] as $code) {
            $this->huecos('it', $code, 10);
        }

        $a = collect($this->servir('it', 1, 1)['items'])->pluck('item_id')->all();
        $b = collect($this->servir('it', 1, 1)['items'])->pluck('item_id')->all();
        $c = collect($this->servir('it', 1, 2)['items'])->pluck('item_id')->all();

        $this->assertSame($a, $b, 'La misma semilla dio dos pruebas distintas.');
        $this->assertNotSame($a, $c, 'Otra semilla dio la misma prueba en el mismo orden.');
        $this->assertCount(10, $a);
        $this->assertCount(10, array_unique($a), 'La prueba repite un ítem.');
    }

    public function test_ningun_descriptor_acapara_mientras_otros_tengan_items(): void
    {
        // Tres descriptores con 20 ítems cada uno: reparto 4/3/3, nadie pasa de 5.
        foreach (['A1.CO.2', 'A1.IO.3', 'A1.CE.1'] as $code) {
            $this->huecos('it', $code, 20);
        }
        $porDescriptor = collect($this->servir('it', 1)['items'])->countBy('objective_code');
        $this->assertCount(3, $porDescriptor, 'Un descriptor con ítems se quedó fuera.');
        foreach ($porDescriptor as $code => $n) {
            $this->assertLessThanOrEqual(5, $n, "«{$code}» acapara {$n} de 10 con otros descriptores disponibles.");
        }
    }

    public function test_un_descriptor_con_un_solo_item_lo_aporta_y_el_resto_lo_llena_otro(): void
    {
        $this->huecos('it', 'A1.CO.2', 20);
        $this->huecos('it', 'A1.IO.3', 1);

        $porDescriptor = collect($this->servir('it', 1)['items'])->countBy('objective_code');
        // El que solo tiene uno lo pone; el otro llena hasta diez porque ya no
        // hay a quién repartir — «mientras haya otros con ítems».
        $this->assertSame(1, $porDescriptor['A1.IO.3']);
        $this->assertSame(9, $porDescriptor['A1.CO.2']);
    }

    // ================= 3. no se filtra la solución =================

    /**
     * Diez ítems sin `solucion`, recorriendo `Registro::kinds()` en las cuatro
     * lenguas del MCER. Cuerpo serializado ENTERO, centinelas sin acentos, y
     * control positivo (el enunciado sí viaja).
     */
    public function test_la_prueba_no_filtra_la_solucion_en_ningun_kind_ni_lengua(): void
    {
        foreach (['it', 'fr', 'de', 'zh'] as $lengua) {
            $fixtures = $this->unoDeCadaKind($lengua);
            $cuerpo = $this->getJson("/api/v1/pruebas/{$lengua}/u1?intento=1")->assertOk()->getContent();

            // Control positivo: lo que sí debe viajar, viaja.
            $this->assertStringContainsString('Completa el saludo.', $cuerpo);

            foreach ($fixtures as $kind => $f) {
                foreach ($f['secretos'] as $secreto) {
                    $this->assertStringNotContainsString($secreto, $cuerpo,
                        "La prueba de «{$lengua}» filtra «{$secreto}» del kind «{$kind}».");
                }
            }
            // Y NADA en el cuerpo se llama solucion (por si un tipo la pusiera
            // bajo otra clave dentro de su payload).
            $this->assertStringNotContainsString('"solucion"', $cuerpo);

            PracticeItem::query()->delete();
        }
    }

    // ================= 4. regla de oro =================

    public function test_el_invitado_hace_la_prueba_ve_la_nota_y_no_escribe_nada(): void
    {
        Queue::fake();
        $fixtures = $this->unoDeCadaKind('it');
        $antes = [PracticeAttempt::count(), ObjectiveMastery::count(), PruebaUnidad::count(), User::count()];

        $servida = $this->servir('it', 1);
        $this->assertFalse($servida['se_guarda']);

        $this->postJson('/api/v1/pruebas/it/u1', [
            'intento' => 1, 'respuestas' => $this->respuestas($servida, $fixtures, true),
        ])
            ->assertOk()   // 200, no 201: no se creó nada
            ->assertJsonPath('se_guarda', false)
            ->assertJsonPath('nota', count($fixtures))
            ->assertJsonPath('aprobada', true);

        $this->assertSame($antes, [PracticeAttempt::count(), ObjectiveMastery::count(), PruebaUnidad::count(), User::count()]);
        Queue::assertNothingPushed();
    }

    // ================= 5. con sesión: intentos reales, fila de prueba, completada =================

    public function test_con_sesion_cada_respuesta_es_un_intento_y_la_prueba_deja_su_fila(): void
    {
        $fixtures = $this->unoDeCadaKind('it');
        $ana = User::factory()->create();

        $servida = $this->actingAs($ana)->getJson('/api/v1/pruebas/it/u1')->assertOk()->json();
        $this->assertTrue($servida['se_guarda']);
        $this->assertSame(1, $servida['intento']);

        $this->actingAs($ana)->postJson('/api/v1/pruebas/it/u1', [
            'intento' => 1, 'respuestas' => $this->respuestas($servida, $fixtures, true),
        ])->assertCreated()->assertJsonPath('se_guarda', true)->assertJsonPath('aprobada', true);

        // Siete intentos de práctica DE VERDAD, con dominio, y una prueba.
        $this->assertSame(count($fixtures), PracticeAttempt::where('user_id', $ana->id)->count());
        $this->assertGreaterThan(0, ObjectiveMastery::where('user_id', $ana->id)->count());
        $prueba = PruebaUnidad::where('user_id', $ana->id)->firstOrFail();
        $this->assertSame(count($fixtures), $prueba->nota);
        $this->assertTrue($prueba->aprobada);
        $this->assertSame(['A1.CO.2' => ['aciertos' => count($fixtures), 'total' => count($fixtures)]], $prueba->desglose);

        // Y la unidad queda COMPLETADA en el mapa del curso.
        $this->actingAs($ana)->get('/corso/it')
            ->assertInertia(fn (Assert $p) => $p->where('unidades.0.estado', 'completada'));
        $this->actingAs($ana)->get('/corso/it/u1')
            ->assertInertia(fn (Assert $p) => $p->where('prueba_aprobada', true));
    }

    public function test_suspendida_se_repite_con_otra_semilla_y_no_completa(): void
    {
        foreach (['A1.CO.2', 'A1.IO.3', 'A1.CE.1'] as $code) {
            $this->huecos('it', $code, 10);
        }
        $ana = User::factory()->create();

        $primera = $this->actingAs($ana)->getJson('/api/v1/pruebas/it/u1')->json();
        // Todas mal: suspendida.
        $malas = collect($primera['items'])->map(fn ($it) => [
            'item_id' => $it['item_id'], 'billete' => $it['billete'], 'respuesta' => ['texto' => 'zzz'],
        ])->all();
        $this->actingAs($ana)->postJson('/api/v1/pruebas/it/u1', ['intento' => 1, 'respuestas' => $malas])
            ->assertCreated()->assertJsonPath('aprobada', false)->assertJsonPath('nota', 0);

        $this->actingAs($ana)->get('/corso/it')
            ->assertInertia(fn (Assert $p) => $p->where('unidades.0.estado', fn ($e) => $e !== 'completada'));

        // La siguiente es el intento 2, con OTRA prueba.
        $segunda = $this->actingAs($ana)->getJson('/api/v1/pruebas/it/u1')->json();
        $this->assertSame(2, $segunda['intento']);
        $this->assertNotSame(
            collect($primera['items'])->pluck('item_id')->all(),
            collect($segunda['items'])->pluck('item_id')->all(),
        );

        // Y no se puede volver a entregar la primera.
        $this->actingAs($ana)->postJson('/api/v1/pruebas/it/u1', ['intento' => 1, 'respuestas' => $malas])
            ->assertStatus(422);
    }

    /**
     * APROBAR LA PRUEBA completa la unidad AUNQUE el dominio no esté sellado.
     *
     * Con tres descriptores de 10 ítems la prueba reparte 4/3/3; diez aciertos
     * sellan solo el de 4 (racha 4, 0.82) y dejan los de 3 en 0.725 < 0.8. Por
     * la vía del dominio la unidad sería «en-curso»; por la de la prueba,
     * «completada». Sin este caso, la vía de la prueba quedaba tapada por la
     * del dominio en el oráculo de arriba (una mutación que la quitaba pasaba).
     */
    public function test_aprobar_completa_la_unidad_aunque_el_dominio_no_este_sellado(): void
    {
        foreach (['A1.CO.2', 'A1.IO.3', 'A1.CE.1'] as $code) {
            $this->huecos('it', $code, 10);
        }
        $ana = User::factory()->create();

        $servida = $this->actingAs($ana)->getJson('/api/v1/pruebas/it/u1')->json();
        $buenas = collect($servida['items'])->map(fn ($it) => [
            'item_id' => $it['item_id'], 'billete' => $it['billete'], 'respuesta' => ['texto' => 'ciao'],
        ])->all();
        $this->actingAs($ana)->postJson('/api/v1/pruebas/it/u1', ['intento' => 1, 'respuestas' => $buenas])
            ->assertCreated()->assertJsonPath('aprobada', true)->assertJsonPath('nota', 10);

        // No todos los descriptores quedaron dominados…
        $sellados = ObjectiveMastery::where('user_id', $ana->id)->whereNotNull('mastered_at')->count();
        $this->assertLessThan(3, $sellados, 'El fixture sella todo el dominio y no aísla la vía de la prueba.');

        // …y aun así la unidad está completada: es la PRUEBA la que lo dice.
        $this->actingAs($ana)->get('/corso/it')
            ->assertInertia(fn (Assert $p) => $p->where('unidades.0.estado', 'completada'));
    }

    /** Un ítem SIN FIRMAR no entra en la prueba, aunque sea de la unidad y la lengua. */
    public function test_un_item_sin_firmar_no_entra_en_la_prueba(): void
    {
        $this->huecos('it', 'A1.CO.2', 3);
        $sinFirmar = PracticeItem::create([
            'objective_id' => $this->objetivo('A1.CO.2')->id, 'kind' => 'hueco', 'lengua' => 'it',
            'statement' => ['es' => 'SIN-FIRMAR'], 'params' => [],
            'solucion' => ['lengua' => 'it', 'textos' => ['x']], 'seq' => 99, 'reviewed_at' => null,
        ]);

        $servida = $this->servir('it', 1);
        $this->assertSame(3, $servida['total']);
        $this->assertNotContains($sinFirmar->id, collect($servida['items'])->pluck('item_id')->all(),
            'La prueba sirvió un ítem que nadie ha firmado.');
    }

    // ================= 6. lengua cerrada, con las cinco =================

    public function test_la_prueba_de_una_lengua_no_contiene_items_de_otra(): void
    {
        foreach (['it', 'fr', 'de', 'zh'] as $lengua) {
            $this->huecos($lengua, 'A1.CO.2', 6);
        }

        foreach (['it', 'fr', 'de', 'zh'] as $lengua) {
            $lenguas = collect($this->servir($lengua, 1)['items'])
                ->map(fn ($it) => PracticeItem::find($it['item_id'])->lengua)->unique()->values()->all();
            $this->assertSame([$lengua], $lenguas, "La prueba de «{$lengua}» mezcló otra lengua.");
        }

        // El inglés no tiene descriptores todavía: no hay prueba, y lo dice.
        $this->getJson('/api/v1/pruebas/en/u7')->assertNotFound();
        $this->getJson('/api/v1/pruebas/klingon/u1')->assertNotFound();
    }

    // ================= 7. lo que entrega tiene que ser lo que se sirvió =================

    public function test_entregar_rechaza_lo_que_no_se_sirvio_y_lo_que_falta(): void
    {
        $fixtures = $this->unoDeCadaKind('it');
        $servida = $this->servir('it', 1);
        $buenas = $this->respuestas($servida, $fixtures, true);

        // Falta una respuesta.
        $this->postJson('/api/v1/pruebas/it/u1', ['intento' => 1, 'respuestas' => array_slice($buenas, 1)])
            ->assertStatus(422);

        // Un ítem de OTRA prueba (de otra lengua), con billete válido para él.
        $ajeno = PracticeItem::create([
            'objective_id' => $this->objetivo('A1.CO.2')->id, 'kind' => 'hueco', 'lengua' => 'fr',
            'statement' => ['es' => 'x'], 'params' => [], 'solucion' => ['lengua' => 'fr', 'textos' => ['x']],
            'seq' => 99, 'reviewed_at' => now(),
        ]);
        $this->postJson('/api/v1/pruebas/it/u1', ['intento' => 1, 'respuestas' => [...$buenas, [
            'item_id' => $ajeno->id, 'billete' => $this->billete($ajeno->id), 'respuesta' => ['texto' => 'x'],
        ]]])->assertStatus(422);

        // Un billete de PRÁCTICA de otro intento no vale para la prueba: va
        // atado a la semilla con la que se sirvió.
        $manipuladas = $buenas;
        $manipuladas[0]['billete'] = $this->billete($manipuladas[0]['item_id'], intento: 7);
        $this->postJson('/api/v1/pruebas/it/u1', ['intento' => 1, 'respuestas' => $manipuladas])
            ->assertOk();   // un billete válido del mismo ítem y practicante: la corrección es la misma

        // Y NADA de eso escribió una fila.
        $this->assertSame(0, PracticeAttempt::count());
        $this->assertSame(0, PruebaUnidad::count());
    }

    // ================= 8. la página =================

    public function test_la_pagina_de_la_prueba_es_abierta_y_no_existe_sin_items(): void
    {
        $this->get('/corso/it/u1/prueba')->assertNotFound();   // sin ítems firmados

        $this->huecos('it', 'A1.CO.2', 3);
        $this->get('/corso/it/u1/prueba')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('prueba')
                ->where('lengua', 'it')->where('unidad.n', 1)->where('se_guarda', false)
                ->where('aprobado', 8)->where('tamano', 10));

        $this->get('/corso/it/u1')
            ->assertInertia(fn (Assert $p) => $p->where('tiene_prueba', true)->where('prueba_aprobada', false));
    }
}
