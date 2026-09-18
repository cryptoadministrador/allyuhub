<?php

namespace Tests\Feature;

use App\Jobs\PushLtiScore;
use App\Models\ObjectiveMastery;
use App\Models\PracticeAttempt;
use App\Models\Tarjeta;
use App\Models\User;
use App\Models\VocabEstado;
use App\Services\Practice\RachaDeAlumno;
use Database\Seeders\CefrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * PR 15 · JUEGOS sobre lo que ya está sembrado: `/corso/{lengua}/u{n}/jugar`
 * sirve las tarjetas FIRMADAS de la unidad, las intrusas de OTRAS unidades y
 * la semilla del día. Los juegos no dan dominio ni AGS; el invitado juega
 * entero y no escribe nada; la lengua es cerrada.
 */
class JuegosTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CefrSeeder::class);
        $this->ana = User::factory()->create();
    }

    private function tarjeta(string $clave, string $lengua = 'it', int $unidad = 1, bool $firmada = true, int $orden = 1): Tarjeta
    {
        return Tarjeta::create([
            'lengua' => $lengua, 'unidad' => $unidad, 'clave' => $clave, 'orden' => $orden,
            'palabra' => $clave, 'lectura' => $lengua === 'zh' ? "pinyin-{$clave}" : null,
            'significado' => "significado de {$clave}",
            'ejemplo' => [$lengua => "Frase con {$clave}.", 'es' => "Frase con {$clave} (es)."],
            'clip' => null, 'audio' => null, 'reviewed_at' => $firmada ? now() : null,
        ]);
    }

    public function test_la_pagina_sirve_las_firmadas_de_la_unidad_y_las_intrusas_de_otras(): void
    {
        foreach (['ciao', 'grazie', 'prego'] as $i => $c) {
            $this->tarjeta($c, orden: $i + 1);
        }
        $sinFirmar = $this->tarjeta('scusa', firmada: false, orden: 4);
        $otraUnidad = $this->tarjeta('lunedi', unidad: 2);
        $otraSinFirmar = $this->tarjeta('martedi', unidad: 2, firmada: false, orden: 2);
        $otraLengua = $this->tarjeta('bonjour', 'fr', 2);

        $this->get('/corso/it/u1/jugar')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('jugar')
                ->where('lengua', 'it')->where('unidad.n', 1)
                ->has('tarjetas', 3)
                ->has('intrusas', 1)
                ->where('intrusas.0.id', $otraUnidad->id)
                ->where('intrusas.0.unidad', 2)
                ->has('semilla')
                ->where('se_guarda', false));

        $cuerpo = $this->get('/corso/it/u1/jugar')->getContent();
        foreach ([$sinFirmar, $otraSinFirmar, $otraLengua] as $t) {
            $this->assertStringNotContainsString($t->id, $cuerpo, "«{$t->clave}» viajó y no debía.");
        }
    }

    /** La semilla, leída de las props de Inertia con el propio `assertInertia`. */
    private function semilla(?User $quien): string
    {
        $peticion = $quien ? $this->actingAs($quien) : $this;
        $semilla = null;
        $peticion->get('/corso/it/u1/jugar')->assertOk()
            ->assertInertia(function (Assert $p) use (&$semilla) {
                $semilla = $p->toArray()['props']['semilla'];
            });

        return $semilla;
    }

    public function test_la_semilla_es_del_dia_y_del_alumno(): void
    {
        $this->tarjeta('ciao');
        $this->travelTo(Carbon::parse('2026-09-18 10:00', RachaDeAlumno::ZONA));
        $a = $this->semilla($this->ana);
        $b = $this->semilla($this->ana);
        $otro = $this->semilla(User::factory()->create());
        $this->travelTo(Carbon::parse('2026-09-19 10:00', RachaDeAlumno::ZONA));
        $manana = $this->semilla($this->ana);

        $this->assertNotNull($a);
        $this->assertSame($a, $b, 'La misma semilla todo el día.');
        $this->assertNotSame($a, $otro, 'Otro jugador, otra semilla.');
        $this->assertNotSame($a, $manana, 'Mañana, otra semilla.');
    }

    public function test_lengua_y_unidad_cerradas_y_sin_vocabulario_404(): void
    {
        $this->tarjeta('ciao');
        $this->get('/corso/it/u1/jugar')->assertOk();
        $this->get('/corso/klingon/u1/jugar')->assertNotFound();
        $this->get('/corso/it/u99/jugar')->assertNotFound();
        $this->get('/corso/en/u1/jugar')->assertNotFound();
        $this->get('/corso/it/u2/jugar')->assertNotFound();
        $this->get('/corso/fr/u1/jugar')->assertNotFound();
    }

    public function test_la_unidad_enlaza_a_jugar_solo_con_vocabulario_firmado(): void
    {
        $this->get('/corso/it/u1')->assertOk()->assertInertia(fn (Assert $p) => $p->where('vocabulario.total', 0));
        $this->tarjeta('ciao');
        $this->get('/corso/it/u1')->assertOk()->assertInertia(fn (Assert $p) => $p->where('vocabulario.total', 1));
    }

    // ================= ORÁCULO 15 · los juegos no mueven dominio ni AGS; el invitado no escribe =================

    public function test_acertar_en_un_juego_marca_la_se_y_no_toca_dominio_ni_ags(): void
    {
        Queue::fake();
        $ciao = $this->tarjeta('ciao');

        // Lo único que hace un juego al acertar: «la sé» por el endpoint del mazo.
        $this->actingAs($this->ana)->postJson("/api/v1/vocabulario/{$ciao->id}/estado", ['conocida' => true])->assertCreated();

        $this->assertSame(1, VocabEstado::where('user_id', $this->ana->id)->whereNotNull('conocida_at')->count());
        $this->assertSame(0, PracticeAttempt::count());
        $this->assertSame(0, ObjectiveMastery::count());
        Queue::assertNotPushed(PushLtiScore::class);
        $this->actingAs($this->ana)->getJson('/api/v1/practice/racha')->assertJsonPath('dias', 0);
    }

    public function test_el_invitado_juega_entero_y_no_escribe_nada(): void
    {
        Queue::fake();
        $ciao = $this->tarjeta('ciao');
        $this->tarjeta('lunedi', unidad: 2);
        $antes = [VocabEstado::count(), PracticeAttempt::count(), ObjectiveMastery::count(), User::count()];

        $this->get('/corso/it/u1/jugar')->assertOk()->assertInertia(fn (Assert $p) => $p->where('se_guarda', false));
        $this->postJson("/api/v1/vocabulario/{$ciao->id}/estado", ['conocida' => true])->assertOk()->assertJsonPath('se_guarda', false);

        $this->assertSame($antes, [VocabEstado::count(), PracticeAttempt::count(), ObjectiveMastery::count(), User::count()]);
        Queue::assertNothingPushed();
    }

    /** Ninguna pantalla de juego muestra el resultado de OTRO alumno (oráculo 15). */
    public function test_la_pagina_no_lleva_a_otro_alumno(): void
    {
        $ciao = $this->tarjeta('ciao');
        $otro = User::factory()->create(['name' => 'CENTINELA-OTRO']);
        VocabEstado::create(['user_id' => $otro->id, 'tarjeta_id' => $ciao->id, 'conocida_at' => now()]);

        $cuerpo = $this->actingAs($this->ana)->get('/corso/it/u1/jugar')->assertOk()->getContent();
        $this->assertStringNotContainsString('CENTINELA-OTRO', $cuerpo);
        $this->assertStringNotContainsString('"user_id":'.$otro->id, $cuerpo);
        $this->actingAs($this->ana)->get('/corso/it/u1/jugar')->assertInertia(fn (Assert $p) => $p->where('tarjetas.0.conocida', false));
    }
}
