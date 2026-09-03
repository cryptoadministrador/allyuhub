<?php

namespace Tests\Feature;

use App\Jobs\PushLtiScore;
use App\Models\Dialogo;
use App\Models\DialogoCompletion;
use App\Models\LearningObjective;
use App\Models\ObjectiveMastery;
use App\Models\User;
use App\Services\Dialogo\Nodos;
use Database\Seeders\CefrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * PR 4 · EL INTERLOCUTOR guionizado (no un LLM).
 *
 * Un diálogo es un grafo de nodos escrito a mano y FIRMADO por un docente. No
 * evalúa: registra que se completó, y de ahí sube el dominio de A1.IO.1 UNA
 * vez. Abierto (el invitado lo hace y no escribe nada); lengua cerrada.
 */
class DialogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CefrSeeder::class);
    }

    private function objIO(): string
    {
        return LearningObjective::where('native_code', 'A1.IO.1')->firstOrFail()->id;
    }

    private function crearDialogo(string $lengua, int $unidad, string $slug, bool $firmado): Dialogo
    {
        return Dialogo::create([
            'objective_id' => $this->objIO(), 'unidad' => $unidad, 'lengua' => $lengua, 'slug' => $slug,
            'titulo' => "Diálogo {$lengua} {$slug}",
            'nodos' => [
                ['id' => 'a', 'dice' => 'Ciao!', 'respuestas' => [
                    ['texto' => 'Ciao!', 'va' => 'b'],
                    ['texto' => 'Bene.', 'va' => null, 'pista' => 'Te saludan: saluda tú.'],
                ]],
                ['id' => 'b', 'dice' => 'A presto!', 'fin' => true],
            ],
            'reviewed_at' => $firmado ? now() : null,
        ]);
    }

    // ================= siembra y firma =================

    public function test_sembrar_crea_el_dialogo_sin_firmar(): void
    {
        $this->artisan('dialogos:sembrar')->assertExitCode(0);

        $d = Dialogo::where('lengua', 'it')->where('slug', 'il-primo-giorno')->firstOrFail();
        $this->assertNull($d->reviewed_at, 'El diálogo nació firmado: debe nacer SIN firmar.');
        $this->assertSame(1, $d->unidad);
        $this->assertSame($this->objIO(), $d->objective_id);
        // El grafo entró entero (arranca en «inicio», hay callejón con pista).
        $this->assertSame('inicio', $d->nodos[0]['id']);
    }

    public function test_sembrar_es_idempotente_y_no_pisa_la_firma(): void
    {
        $this->artisan('dialogos:sembrar')->assertExitCode(0);
        $this->artisan('dialogos:firmar', ['--lengua' => 'it'])->assertExitCode(0);
        $this->artisan('dialogos:sembrar')->assertExitCode(0);   // re-sembrar

        $this->assertSame(1, Dialogo::where('lengua', 'it')->count());
        $this->assertNotNull(Dialogo::where('lengua', 'it')->first()->reviewed_at,
            'Re-sembrar borró la firma.');
    }

    // ================= la puerta de la firma =================

    public function test_un_dialogo_sin_firmar_no_se_sirve(): void
    {
        $this->crearDialogo('it', 1, 'x', firmado: false);

        // Sin firmar: la página lo dice, no lo sirve.
        $this->get('/corso/it/u1/hablar')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('dialogo')->where('dialogo', null));

        // La unidad no enlaza a hablar.
        $this->get('/corso/it/u1')->assertInertia(fn (Assert $p) => $p->where('tiene_dialogo', false));
    }

    public function test_un_dialogo_firmado_se_sirve_y_la_unidad_enlaza(): void
    {
        $this->crearDialogo('it', 1, 'x', firmado: true);

        $this->get('/corso/it/u1/hablar')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('dialogo')
                ->where('dialogo.titulo', 'Diálogo it x')
                ->has('dialogo.nodos', 2));

        $this->get('/corso/it/u1')->assertInertia(fn (Assert $p) => $p->where('tiene_dialogo', true));
    }

    // ================= completar: dominio una vez, regla de oro =================

    public function test_completar_sube_el_dominio_una_sola_vez(): void
    {
        Queue::fake();
        $d = $this->crearDialogo('it', 1, 'x', firmado: true);
        $ana = User::factory()->create();

        // Primera vez: se registra y sube el dominio.
        $this->actingAs($ana)->postJson("/api/v1/dialogos/{$d->id}/completado")
            ->assertOk()->assertJsonPath('se_guarda', true)->assertJsonPath('ya_completado', false);

        $m = ObjectiveMastery::where('user_id', $ana->id)->where('objective_id', $this->objIO())->firstOrFail();
        $this->assertSame(1, $m->attempts_count);
        // Un diálogo NO sella el dominio por sí solo (hito exige ≥2 ítems).
        $this->assertNull($m->mastered_at);

        // Segunda vez: ya_completado, y el dominio NO se infla.
        $this->actingAs($ana)->postJson("/api/v1/dialogos/{$d->id}/completado")
            ->assertOk()->assertJsonPath('ya_completado', true);

        $this->assertSame(1, DialogoCompletion::count());
        $this->assertSame(1, $m->refresh()->attempts_count, 'Completar dos veces infló el dominio.');
    }

    public function test_el_invitado_completa_y_no_deja_rastro(): void
    {
        Queue::fake();
        $d = $this->crearDialogo('it', 1, 'x', firmado: true);

        $antes = [DialogoCompletion::count(), ObjectiveMastery::count(), User::count()];
        $this->postJson("/api/v1/dialogos/{$d->id}/completado")
            ->assertOk()->assertJsonPath('se_guarda', false);

        $this->assertSame($antes, [DialogoCompletion::count(), ObjectiveMastery::count(), User::count()]);
        Queue::assertNothingPushed();
    }

    public function test_no_se_completa_un_dialogo_sin_firmar(): void
    {
        $d = $this->crearDialogo('it', 1, 'x', firmado: false);
        $ana = User::factory()->create();

        $this->actingAs($ana)->postJson("/api/v1/dialogos/{$d->id}/completado")->assertStatus(404);
        $this->assertSame(0, DialogoCompletion::count());
    }

    public function test_un_user_id_en_completado_es_422(): void
    {
        $d = $this->crearDialogo('it', 1, 'x', firmado: true);
        $ana = User::factory()->create();

        $this->actingAs($ana)->postJson("/api/v1/dialogos/{$d->id}/completado", ['user_id' => 999])
            ->assertStatus(422);
    }

    // ================= lengua cerrada en las dos direcciones =================

    public function test_hablar_es_cerrado_por_lengua_con_dos_lenguas(): void
    {
        $this->crearDialogo('it', 1, 'it-uno', firmado: true);
        $this->crearDialogo('de', 1, 'de-uno', firmado: true);

        // Italiano sirve el suyo; alemán el suyo. Nunca el del otro.
        $this->get('/corso/it/u1/hablar')
            ->assertInertia(fn (Assert $p) => $p->where('dialogo.titulo', 'Diálogo it it-uno'));
        $this->get('/corso/de/u1/hablar')
            ->assertInertia(fn (Assert $p) => $p->where('dialogo.titulo', 'Diálogo de de-uno'));

        // Una lengua fuera de la lista es 404.
        $this->get('/corso/klingon/u1/hablar')->assertNotFound();
    }

    public function test_hablar_trae_el_dialogo_de_esa_unidad(): void
    {
        // u1 con slug que ordena DESPUÉS, u2 con slug que ordena antes: si
        // «hablar» no filtrara por unidad, el orden por slug traería el de u2.
        $this->crearDialogo('it', 1, 'z-uno', firmado: true);
        $this->crearDialogo('it', 2, 'a-dos', firmado: true);

        $this->get('/corso/it/u1/hablar')
            ->assertInertia(fn (Assert $p) => $p->where('dialogo.titulo', 'Diálogo it z-uno'));
    }

    // ================= el validador del grafo =================

    public function test_el_validador_acepta_el_guion_real_y_rechaza_los_rotos(): void
    {
        // El guion real del banco es válido.
        $banco = require database_path('data/dialogos-lenguas.php');
        Nodos::validar($banco[0]['nodos'], 'real');

        // Un `va` a un nodo que no existe revienta.
        $roto = [
            ['id' => 'a', 'dice' => 'Ciao', 'respuestas' => [['texto' => 'x', 'va' => 'fantasma']]],
            ['id' => 'z', 'dice' => 'Fin', 'fin' => true],
        ];
        try {
            Nodos::validar($roto, 'roto');
            $this->fail('El validador aceptó un `va` a un nodo inexistente.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('fantasma', $e->getMessage());
        }

        // Un grafo SIN nodo final revienta (nunca se llega a completar).
        $sinFinal = [
            ['id' => 'a', 'dice' => 'Ciao', 'respuestas' => [['texto' => 'x', 'va' => 'b']]],
            ['id' => 'b', 'dice' => 'E poi?', 'respuestas' => [['texto' => 'y', 'va' => 'a']]],
        ];
        try {
            Nodos::validar($sinFinal, 'sin-final');
            $this->fail('El validador aceptó un grafo sin final.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('final', $e->getMessage());
        }

        // Un callejón (va:null) sin pista revienta.
        $sinPista = [
            ['id' => 'a', 'dice' => 'Ciao', 'respuestas' => [['texto' => 'x', 'va' => null]]],
            ['id' => 'z', 'dice' => 'Fin', 'fin' => true],
        ];
        $this->expectException(InvalidArgumentException::class);
        Nodos::validar($sinPista, 'sin-pista');
    }
}
