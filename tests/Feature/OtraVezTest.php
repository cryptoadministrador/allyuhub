<?php

namespace Tests\Feature;

use App\Jobs\PushLtiScore;
use App\Models\ObjectiveMastery;
use App\Models\PracticeAttempt;
use App\Models\PracticeItem;
use App\Models\User;
use App\Services\Practice\AttemptTicket;
use App\Services\Practice\RegistroDeIntento;
use App\Services\Practice\Tipos\Registro;
use Database\Seeders\CefrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\UnoDeCadaKind;
use Tests\TestCase;

/**
 * PR 13 · EL BUCLE DE «OTRA VEZ»: fallas, te dan la pista y lo intentas otra
 * vez (hasta tres). Cada reintento se GUARDA como su propia fila y NO CUENTA:
 * solo el primer intento alimenta el dominio y la nota. El flag va FIRMADO en
 * el billete. La prueba de unidad NO reintenta.
 */
class OtraVezTest extends TestCase
{
    use RefreshDatabase;
    use UnoDeCadaKind;

    private User $ana;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CefrSeeder::class);
        $this->ana = User::factory()->create();
    }

    private function responder(PracticeItem $item, array $cuerpo, string $billete, ?User $quien = null)
    {
        $peticion = $quien ? $this->actingAs($quien) : $this;

        return $peticion->postJson("/api/v1/practice/items/{$item->id}/attempts", [...$cuerpo, 'billete' => $billete]);
    }

    // ================= ORÁCULO 12 · el reintento no infla el dominio, por kind =================

    public function test_acertar_a_la_tercera_deja_tres_filas_y_mueve_el_dominio_una_vez_por_kind(): void
    {
        Queue::fake();
        $vueltas = 0;
        foreach ($this->unoDeCadaKind('it') as $kind => $f) {
            $item = $f['item'];
            $vueltas++;
            $billete = $this->billeteComoNext($item->id, $this->ana->id);

            // 1.º falla → pista sin solución y billete de «otra vez».
            $v1 = $this->responder($item, $f['mala'], $billete, $this->ana)->assertCreated()
                ->assertJsonPath('is_correct', false)->assertJsonPath('reintento', 0)
                ->assertJsonPath('otra_vez.reintento', 1)->assertJsonPath('otra_vez.attempt_no', 2)
                ->json();
            foreach (Registro::de($kind)->revelan() as $clave) {
                $this->assertArrayNotHasKey($clave, $v1, "{$kind}: «{$clave}» viajó al primer fallo.");
            }
            $this->assertArrayNotHasKey('andamiaje', $v1, "{$kind}: el andamiaje viajó al primer fallo.");

            // 2.º falla → andamiaje, y le queda una.
            $v2 = $this->responder($item, $f['mala'], $v1['otra_vez']['billete'], $this->ana)->assertCreated()
                ->assertJsonPath('reintento', 1)->assertJsonPath('otra_vez.reintento', 2)->json();
            $this->assertArrayHasKey('andamiaje', $v2, "{$kind}: sin andamiaje al segundo fallo.");
            foreach (Registro::de($kind)->revelan() as $clave) {
                $this->assertArrayNotHasKey($clave, $v2, "{$kind}: «{$clave}» viajó al segundo fallo.");
            }

            // 3.º acierta → veredicto entero, sin más vueltas.
            $v3 = $this->responder($item, $f['buena'], $v2['otra_vez']['billete'], $this->ana)->assertCreated()
                ->assertJsonPath('is_correct', true)->assertJsonPath('reintento', 2)->json();
            $this->assertArrayNotHasKey('otra_vez', $v3);
            foreach (Registro::de($kind)->revelan() as $clave) {
                $this->assertArrayHasKey($clave, $v3, "{$kind}: al acertar no llegó «{$clave}».");
            }

            // TRES filas: la primera sin reintento, las otras con su vuelta.
            $filas = PracticeAttempt::where('item_id', $item->id)->orderBy('attempt_no')->get();
            $this->assertSame([1, 2, 3], $filas->pluck('attempt_no')->all(), "{$kind}: no quedaron tres filas.");
            $this->assertSame([null, 1, 2], $filas->pluck('reintento')->all());
            $this->assertSame([false, false, true], $filas->pluck('is_correct')->all());

            // Y el dominio se movió UNA vez por ítem, la del fallo (todos los
            // kinds cuelgan del mismo descriptor: un intento contado por kind),
            // ningún ítem acertado, nada sellado, nada al aula.
            $dominio = ObjectiveMastery::where('user_id', $this->ana->id)->where('objective_id', $item->objective_id)->firstOrFail();
            $this->assertSame($vueltas, $dominio->attempts_count, "{$kind}: el dominio contó más de un intento por ítem.");
            $this->assertLessThanOrEqual(0, $dominio->streak, "{$kind}: el acierto del reintento subió la racha del dominio.");
            $this->assertNull($dominio->mastered_at);
            $this->assertSame(0, app(\App\Services\Practice\MasteryTracker::class)->itemsAcertados($this->ana->id, $item->objective_id),
                "{$kind}: el acierto del reintento cuenta como ítem acertado.");

            // El ítem siguiente parte del intento 4: las filas están.
            $this->assertSame(4, PracticeAttempt::where('item_id', $item->id)->where('user_id', $this->ana->id)->count() + 1);
        }
        Queue::assertNotPushed(PushLtiScore::class);
    }

    /** Acertar al reintento no completa los DOS ítems que exige el dominio (no sella). */
    public function test_dos_aciertos_en_reintento_no_sellan_el_dominio(): void
    {
        $kinds = $this->unoDeCadaKind('it');
        foreach ([$kinds['choice'], $kinds['hueco']] as $f) {
            $item = $f['item'];
            $v1 = $this->responder($item, $f['mala'], $this->billeteComoNext($item->id, $this->ana->id), $this->ana)->json();
            $this->responder($item, $f['buena'], $v1['otra_vez']['billete'], $this->ana)->assertCreated()->assertJsonPath('is_correct', true);
        }
        $dominio = ObjectiveMastery::where('user_id', $this->ana->id)->firstOrFail();
        $this->assertNull($dominio->mastered_at);
        $this->assertSame(2, $dominio->attempts_count);
    }

    // ================= ORÁCULO 13 · `reintento` viaja firmado =================

    public function test_un_reintento_en_el_cuerpo_es_422_y_el_billete_manda(): void
    {
        $f = $this->unoDeCadaKind('it')['choice'];
        $item = $f['item'];

        $this->responder($item, [...$f['mala'], 'reintento' => false], $this->billeteComoNext($item->id, $this->ana->id), $this->ana)
            ->assertStatus(422)->assertJsonValidationErrors('reintento');
        $this->assertSame(0, PracticeAttempt::count());

        // Un billete de reintento con el flag manipulado ya no está firmado.
        $v1 = $this->responder($item, $f['mala'], $this->billeteComoNext($item->id, $this->ana->id), $this->ana)->json();
        [$cuerpo, $firma] = explode('.', $v1['otra_vez']['billete']);
        $datos = json_decode(base64_decode(strtr($cuerpo, '-_', '+/')), true);
        $this->assertSame(1, $datos['reintento']);
        $datos['reintento'] = 0;
        $forjado = rtrim(strtr(base64_encode(json_encode($datos)), '+/', '-_'), '=').'.'.$firma;
        $this->responder($item, $f['buena'], $forjado, $this->ana)->assertStatus(422)->assertJsonValidationErrors('billete');

        // Y el de verdad, sí: guarda y NO cuenta.
        $this->responder($item, $f['buena'], $v1['otra_vez']['billete'], $this->ana)->assertCreated()->assertJsonPath('reintento', 1);
        $this->assertSame([null, 1], PracticeAttempt::orderBy('attempt_no')->pluck('reintento')->all());
    }

    /** El billete de «otra vez» es del MISMO ítem, la MISMA semilla y el mismo alumno; con otro, 422. */
    public function test_el_billete_de_otra_vez_es_del_mismo_item_y_alumno(): void
    {
        $kinds = $this->unoDeCadaKind('it');
        $item = $kinds['numeric']['item'];
        $otro = $kinds['choice']['item'];
        $v1 = $this->responder($item, $kinds['numeric']['mala'], $this->billeteComoNext($item->id, $this->ana->id), $this->ana)->json();

        $abierto = AttemptTicket::abrir($v1['otra_vez']['billete'], $item->id, $this->ana->id);
        $this->assertSame(['attempt_no' => 2, 'repaso' => false, 'reintento' => 1], [
            'attempt_no' => $abierto['attempt_no'], 'repaso' => $abierto['repaso'], 'reintento' => $abierto['reintento'],
        ]);
        $this->assertSame(PracticeAttempt::firstOrFail()->seed, $abierto['seed'], 'El reintento cambió la semilla: otros números.');

        $this->responder($otro, $kinds['choice']['mala'], $v1['otra_vez']['billete'], $this->ana)->assertStatus(422);
        $this->responder($item, $kinds['numeric']['buena'], $v1['otra_vez']['billete'], User::factory()->create())->assertStatus(422);
    }

    /** El flag de repaso se conserva en las vueltas: un reintento en repaso sigue sin AGS. */
    public function test_el_reintento_conserva_el_flag_de_repaso(): void
    {
        $item = $this->unoDeCadaKind('it')['hueco']['item'];
        $billete = AttemptTicket::emitir($item->id, $this->ana->id, 1, str_repeat('a', 64), repaso: true);
        $v1 = $this->responder($item, ['respuesta' => ['texto' => 'zzz']], $billete, $this->ana)->json();
        $this->assertTrue(AttemptTicket::abrir($v1['otra_vez']['billete'], $item->id, $this->ana->id)['repaso']);
    }

    /**
     * El reintento tampoco toca el REPASO ESPACIADO: tras un fallo (intervalo a
     * 1, cita mañana) un acierto en la vuelta no dobla el intervalo — el
     * descriptor sigue citado para mañana. Y sigue siendo «fallo reciente» del
     * repaso diario: necesitó ayuda, vuelve mañana.
     */
    public function test_el_reintento_no_reprograma_el_repaso_ni_borra_el_fallo_reciente(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(10));
        $kinds = $this->unoDeCadaKind('it');
        $item = $kinds['hueco']['item'];
        $this->unoDeCadaKind('it', 'A1.CE.1');   // algo que ver para que haya repaso

        $v1 = $this->responder($item, $kinds['hueco']['mala'], $this->billeteComoNext($item->id, $this->ana->id), $this->ana)->json();
        $antes = ObjectiveMastery::where('user_id', $this->ana->id)->where('objective_id', $item->objective_id)->firstOrFail();
        $this->assertSame(1, $antes->repaso_intervalo);

        $this->responder($item, $kinds['hueco']['buena'], $v1['otra_vez']['billete'], $this->ana)->assertCreated()->assertJsonPath('is_correct', true);

        $despues = $antes->fresh();
        $this->assertSame(1, $despues->repaso_intervalo, 'El acierto del reintento dobló el intervalo de repaso.');
        $this->assertSame($antes->repaso_en->toDateTimeString(), $despues->repaso_en->toDateTimeString());

        $diario = $this->actingAs($this->ana)->getJson('/api/v1/practice/repaso-diario?lengua=it')->assertOk()->json();
        $fallado = collect($diario['items'])->firstWhere('item_id', $item->id);
        $this->assertNotNull($fallado, 'El ítem salvado a la segunda desapareció de los fallos recientes.');
        $this->assertSame(2, $fallado['prioridad']);
    }

    // ================= el andamiaje, por tipo =================

    public function test_el_andamiaje_del_hueco_son_tres_opciones_con_la_buena_y_sin_la_solucion_antes(): void
    {
        $f = $this->unoDeCadaKind('it')['hueco'];
        $item = $f['item'];
        // Vecinas de la unidad, firmadas, de las que salen los distractores.
        foreach (['DISTRACTOR-UNO', 'DISTRACTOR-DOS', 'DISTRACTOR-TRES'] as $i => $t) {
            PracticeItem::create([
                'objective_id' => $item->objective_id, 'kind' => 'hueco', 'lengua' => 'it',
                'statement' => ['es' => "Vecina {$i}"], 'params' => [], 'seq' => 40 + $i,
                'solucion' => ['lengua' => 'it', 'textos' => [$t]], 'reviewed_at' => now(),
            ]);
        }

        $v1 = $this->responder($item, $f['mala'], $this->billeteComoNext($item->id, $this->ana->id), $this->ana)->json();
        $this->assertStringNotContainsString('CENTINELA-HUECO-SOL', json_encode($v1), 'La solución viajó al primer fallo.');

        $v2 = $this->responder($item, $f['mala'], $v1['otra_vez']['billete'], $this->ana)->json();
        $opciones = $v2['andamiaje']['opciones'];
        $this->assertCount(3, $opciones);
        $this->assertContains('CENTINELA-HUECO-SOL', $opciones);
        $this->assertCount(3, array_unique($opciones));
        // Los distractores son soluciones de OTROS huecos/dictados firmados de
        // la lengua (el dictado del fixture también cuenta), nunca inventos.
        foreach ($opciones as $o) {
            $this->assertTrue(
                $o === 'CENTINELA-HUECO-SOL' || $o === 'CENTINELA-DICTADO-SOL' || str_starts_with($o, 'DISTRACTOR-'),
                "Distractor raro: {$o}",
            );
        }
        // Fuera del andamiaje, la solución sigue sin viajar (solo dentro de las opciones).
        $this->assertArrayNotHasKey('esperado', $v2);

        // Determinista: la misma semilla, las mismas opciones en el mismo orden.
        $this->assertSame($opciones, Registro::de('hueco')->andamiaje($item, $v2, app(\App\Services\Practice\PracticeEngine::class), PracticeAttempt::firstOrFail()->seed)['opciones']);
    }

    public function test_el_andamiaje_del_hueco_sin_vecinas_inventa_variantes_de_la_palabra(): void
    {
        $item = PracticeItem::create([
            'objective_id' => $this->objetivoDeKind('A1.CO.2')->id, 'kind' => 'hueco', 'lengua' => 'fr',
            'statement' => ['es' => 'Completa.'], 'params' => [], 'seq' => 1,
            'solucion' => ['lengua' => 'fr', 'textos' => ['où']], 'reviewed_at' => now(),
        ]);
        $v1 = $this->responder($item, ['respuesta' => ['texto' => 'zzz']], $this->billeteComoNext($item->id, $this->ana->id), $this->ana)->json();
        $v2 = $this->responder($item, ['respuesta' => ['texto' => 'zzz']], $v1['otra_vez']['billete'], $this->ana)->json();
        $opciones = $v2['andamiaje']['opciones'];
        $this->assertCount(3, $opciones);
        $this->assertContains('où', $opciones);
        $this->assertContains('ou', $opciones);   // la misma sin acento: el error clásico
    }

    /** Un distractor no puede ser una forma ACEPTADA del ítem, ni salir de un ítem sin firmar. */
    public function test_los_distractores_no_son_formas_aceptadas_ni_vienen_de_items_sin_firmar(): void
    {
        $obj = $this->objetivoDeKind('A1.CO.2')->id;
        $item = PracticeItem::create([
            'objective_id' => $obj, 'kind' => 'hueco', 'lengua' => 'fr',
            'statement' => ['es' => 'Completa.'], 'params' => [], 'seq' => 1,
            'solucion' => ['lengua' => 'fr', 'textos' => ['où', 'ALT-ACEPTADA']], 'reviewed_at' => now(),
        ]);
        foreach ([['ALT-ACEPTADA', true], ['SIN-FIRMAR', false], ['VECINA-UNO', true], ['VECINA-DOS', true], ['VECINA-TRES', true]] as $i => [$t, $firmada]) {
            PracticeItem::create([
                'objective_id' => $obj, 'kind' => 'hueco', 'lengua' => 'fr',
                'statement' => ['es' => "V{$i}"], 'params' => [], 'seq' => 10 + $i,
                'solucion' => ['lengua' => 'fr', 'textos' => [$t]], 'reviewed_at' => $firmada ? now() : null,
            ]);
        }

        // Varias semillas: los distractores rotan, y ninguno es aceptado ni sin firmar.
        foreach (range(1, 6) as $intento) {
            $v1 = $this->responder($item, ['respuesta' => ['texto' => 'zzz']], $this->billete($item->id, intento: $intento))->json();
            $v2 = $this->responder($item, ['respuesta' => ['texto' => 'zzz']], $v1['otra_vez']['billete'])->json();
            $opciones = $v2['andamiaje']['opciones'];
            $this->assertNotContains('ALT-ACEPTADA', $opciones, 'Una forma aceptada salió como distractor.');
            $this->assertNotContains('SIN-FIRMAR', $opciones, 'Un ítem sin firmar prestó su solución.');
            $this->assertContains('où', $opciones);
            $this->assertCount(3, $opciones);
        }
    }

    public function test_el_andamiaje_de_orden_pares_y_clave(): void
    {
        $kinds = $this->unoDeCadaKind('it');

        $orden = $kinds['orden']['item'];
        $v1 = $this->responder($orden, $kinds['orden']['mala'], $this->billeteComoNext($orden->id, $this->ana->id), $this->ana)->json();
        $v2 = $this->responder($orden, $kinds['orden']['mala'], $v1['otra_vez']['billete'], $this->ana)->json();
        $this->assertSame(['primero' => 'o2'], $v2['andamiaje']);
        $this->assertArrayNotHasKey('secuencia_correcta', $v2);

        $pares = $kinds['pares']['item'];
        $v1 = $this->responder($pares, ['respuesta' => ['parejas' => [['x1', 'y1'], ['x2', 'y1']]]], $this->billeteComoNext($pares->id, $this->ana->id), $this->ana)
            ->assertStatus(422);   // una clave repetida sigue siendo 422, reintento o no
        $v1 = $this->responder($pares, $kinds['pares']['mala'], $this->billeteComoNext($pares->id, $this->ana->id), $this->ana)->json();
        $this->assertSame(0, $v1['parejas_correctas']);   // la pista viaja
        $this->assertArrayNotHasKey('parejas_esperadas', $v1);
        // Segundo fallo con UNA bien: esa queda fija, la otra vuelve al tablero.
        $v2 = $this->responder($pares, ['respuesta' => ['parejas' => [['x1', 'y1'], ['x2', 'y1']]]], $v1['otra_vez']['billete'], $this->ana)->assertStatus(422);
        $v2 = $this->responder($pares, ['respuesta' => ['parejas' => [['x1', 'y2'], ['x2', 'y1']]]], $v1['otra_vez']['billete'], $this->ana)->json();
        $this->assertSame(['correctas' => []], $v2['andamiaje']);

        $choice = $kinds['choice']['item'];
        $choice->update(['options' => [
            ['key' => 'a', 'text' => ['es' => 'una']], ['key' => 'b', 'text' => ['es' => 'otra']], ['key' => 'c', 'text' => ['es' => 'tercera']],
        ]]);
        $v1 = $this->responder($choice, ['answer_key' => 'b'], $this->billeteComoNext($choice->id, $this->ana->id), $this->ana)->json();
        $v2 = $this->responder($choice, ['answer_key' => 'b'], $v1['otra_vez']['billete'], $this->ana)->json();
        // Se descarta una que no es la buena (a) ni la elegida (b): solo queda c.
        $this->assertSame(['descartar' => ['c']], $v2['andamiaje']);
        $this->assertArrayNotHasKey('expected_key', $v2);
    }

    // ================= ORÁCULO 14 · la prueba de unidad NO reintenta =================

    public function test_la_prueba_de_unidad_no_reintenta_y_revela_al_final(): void
    {
        $fixtures = $this->unoDeCadaKind('it');
        $servida = $this->actingAs($this->ana)->getJson('/api/v1/pruebas/it/u1?intento=1')->assertOk()->json();

        foreach ($servida['items'] as $it) {
            $abierto = AttemptTicket::abrir($it['billete'], $it['item_id'], $this->ana->id);
            $this->assertSame(0, $abierto['reintento'], 'La prueba emitió un billete de reintento.');
        }

        $respuestas = [];
        foreach ($servida['items'] as $it) {
            $kind = PracticeItem::findOrFail($it['item_id'])->kind;
            $respuestas[] = ['item_id' => $it['item_id'], ...$fixtures[$kind]['mala'], 'billete' => $it['billete']];
        }
        $r = $this->actingAs($this->ana)->postJson('/api/v1/pruebas/it/u1', ['intento' => 1, 'respuestas' => $respuestas])
            ->assertCreated()->json();

        foreach ($r['veredictos'] as $v) {
            $this->assertArrayNotHasKey('otra_vez', $v, 'La prueba ofreció «otra vez».');
            $this->assertArrayNotHasKey('andamiaje', $v);
            $kind = PracticeItem::findOrFail($v['item_id'])->kind;
            foreach (Registro::de($kind)->revelan() as $clave) {
                $this->assertArrayHasKey($clave, $v, "Prueba/{$kind}: al final no reveló «{$clave}».");
            }
        }
        $this->assertSame(0, PracticeAttempt::whereNotNull('reintento')->count());
    }

    // ================= regla de oro =================

    public function test_el_invitado_reintenta_entero_y_no_escribe_nada(): void
    {
        Queue::fake();
        $f = $this->unoDeCadaKind('it')['hueco'];
        $item = $f['item'];
        $antes = [PracticeAttempt::count(), ObjectiveMastery::count(), User::count()];

        $v1 = $this->responder($item, $f['mala'], $this->billete($item->id))->assertOk()
            ->assertJsonPath('se_guarda', false)->assertJsonPath('otra_vez.reintento', 1)->json();
        $v2 = $this->responder($item, $f['mala'], $v1['otra_vez']['billete'])->assertOk()->json();
        $this->assertArrayHasKey('andamiaje', $v2);
        $this->responder($item, $f['buena'], $v2['otra_vez']['billete'])->assertOk()
            ->assertJsonPath('is_correct', true)->assertJsonPath('reintento', 2)->assertJsonPath('se_guarda', false);

        $this->assertSame($antes, [PracticeAttempt::count(), ObjectiveMastery::count(), User::count()]);
        Queue::assertNothingPushed();
    }

    // ================= ORÁCULO 1 · ninguna vía nueva filtra, por kind y en las cuatro lenguas =================

    /**
     * Mientras quede vuelta, ni el veredicto ni el andamiaje delatan la
     * solución: se recorre `Registro::kinds()` en las CUATRO lenguas, como
     * invitado (la corrección es la misma y no hay filas que limpiar), y se
     * busca cada centinela en el CUERPO entero, no en un nombre de campo.
     */
    public function test_ni_el_veredicto_con_vuelta_ni_el_andamiaje_filtran_la_solucion_en_las_cuatro_lenguas(): void
    {
        foreach (['it', 'fr', 'de', 'zh'] as $lengua) {
            foreach ($this->unoDeCadaKind($lengua) as $kind => $f) {
                $item = $f['item'];
                $r1 = $this->responder($item, $f['mala'], $this->billete($item->id))->assertOk();
                $v1 = $r1->json();
                $this->assertArrayHasKey('otra_vez', $v1, "{$lengua}/{$kind}: sin vuelta al primer fallo.");
                $this->assertArrayNotHasKey('andamiaje', $v1, "{$lengua}/{$kind}: andamiaje al primer fallo.");

                $r2 = $this->responder($item, $f['mala'], $v1['otra_vez']['billete'])->assertOk();
                $v2 = $r2->json();
                $this->assertArrayHasKey('andamiaje', $v2, "{$lengua}/{$kind}: sin andamiaje al segundo fallo.");

                foreach ([$r1, $r2] as $i => $r) {
                    $cuerpo = $r->getContent();
                    foreach (Registro::de($kind)->revelan() as $clave) {
                        $this->assertStringNotContainsString("\"{$clave}\"", $cuerpo, "{$lengua}/{$kind}: «{$clave}» viajó en el fallo ".($i + 1).'.');
                    }
                    foreach ($f['secretos'] as $centinela) {
                        // `answer_key` y `parejas` son el ECO de lo que el alumno
                        // mandó (su propia respuesta), no la solución: los
                        // centinelas de nombre de campo son para `next`.
                        if (in_array($centinela, ['answer_key', 'parejas'], true)) {
                            continue;
                        }
                        // El hueco/dictado enseñan la buena DENTRO de las tres
                        // opciones del andamiaje (segundo fallo): eso es el
                        // andamiaje, no una fuga. El resto, jamás.
                        $enAndamiaje = $i === 1 && in_array($kind, [PracticeItem::HUECO, PracticeItem::DICTADO], true)
                            && in_array($centinela, ['CENTINELA-HUECO-SOL', 'CENTINELA-DICTADO-SOL'], true);
                        if ($enAndamiaje) {
                            continue;
                        }
                        $this->assertStringNotContainsString($centinela, $cuerpo, "{$lengua}/{$kind}: «{$centinela}» viajó en el fallo ".($i + 1).'.');
                    }
                }
            }
            // Cada lengua limpia: los ítems de una no son vecinos de la otra.
            PracticeItem::query()->delete();
        }
    }

    // ================= la revisión docente vive el mismo bucle =================

    public function test_la_constante_del_bucle_es_dos_vueltas(): void
    {
        $this->assertSame(2, RegistroDeIntento::MAX_REINTENTOS);
    }
}
