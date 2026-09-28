<?php

namespace Tests\Feature;

use App\Models\LearningObjective;
use App\Models\LtiContext;
use App\Models\LtiContextMembership;
use App\Models\LtiPlatform;
use App\Models\Produccion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * PR 19 · EL INGLÉS PRODUCE. La tarea de producción (escribir / grabar) era del
 * MCER por tres reglas escritas a mano: el endpoint solo aceptaba destrezas
 * `.EE.`/`.PO.`, la rúbrica era la de A1 para todo, y la página pedía «tres o
 * cuatro frases» y 30 s. Ahora las tres las declara el CURSO. Los oráculos
 * fijan las dos mitades: el inglés 0861 produce con SU forma, y el MCER no
 * cambia ni una coma.
 */
class ProduccionInglesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarMarcosDeLenguas();
    }

    /** @return array{0: User, 1: User} [docente, alumno] del mismo curso LTI */
    private function curso(): array
    {
        $platform = LtiPlatform::create([
            'issuer' => 'https://moodle.test', 'client_id' => 'c1',
            'deployment_ids' => ['d1'], 'auth_login_url' => 'x', 'auth_token_url' => 'x', 'jwks_url' => 'x',
        ]);
        $context = LtiContext::create(['platform_id' => $platform->id, 'context_id' => 'c-1', 'title' => 'Inglés 8A']);
        $docente = User::factory()->create();
        $alumno = User::factory()->create();
        LtiContextMembership::create(['lti_context_id' => $context->id, 'user_id' => $docente->id, 'role' => 'instructor']);
        LtiContextMembership::create(['lti_context_id' => $context->id, 'user_id' => $alumno->id, 'role' => 'learner']);

        return [$docente, $alumno];
    }

    private function obj(string $code): string
    {
        return LearningObjective::where('native_code', $code)->firstOrFail()->id;
    }

    private function escritura(User $alumno, string $code = 'EN7.W.1', int $unidad = 7, string $tipo = 'escritura')
    {
        return $this->actingAs($alumno)->postJson('/api/v1/producciones', [
            'objective_id' => $this->obj($code), 'unidad' => $unidad, 'lengua' => 'en',
            'tipo' => $tipo, 'texto' => 'On a stormy night, the old lighthouse keeper heard a knock at the door.',
        ]);
    }

    public function test_la_unidad_inglesa_ofrece_sus_tareas_con_su_formato(): void
    {
        $this->get('/corso/en/u7/producir')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('producir')
                // 4 destrezas W → escritura, 3 SL → voz; ninguna R.
                ->has('productivos', 7)
                ->where('productivos.0.code', 'EN7.W.1')->where('productivos.0.tipo', 'escritura')
                ->where('productivos.4.code', 'EN7.SL.1')->where('productivos.4.tipo', 'voz')
                ->where('formato.voz_max_s', 90)
                ->where('formato.escritura', fn ($t) => str_contains($t, 'párrafo')));
    }

    public function test_el_mcer_conserva_su_tarea_y_su_formato_de_a1(): void
    {
        $this->get('/corso/it/u2/producir')->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('formato', ['escritura' => 'Escribe tres o cuatro frases', 'voz_min_s' => 20, 'voz_max_s' => 30]));
    }

    public function test_un_alumno_entrega_escritura_contra_una_destreza_w(): void
    {
        [, $alumno] = $this->curso();

        $this->escritura($alumno)->assertCreated();
        $p = Produccion::sole();
        $this->assertSame('en', $p->lengua);
        $this->assertSame(7, $p->unidad);
    }

    public function test_la_destreza_tiene_que_ser_la_productiva_que_declara_el_curso(): void
    {
        [, $alumno] = $this->curso();

        // Lectura no es productiva; escribir contra SL tampoco (SL es de voz).
        $this->escritura($alumno, 'EN7.R.1')->assertStatus(422)->assertJsonValidationErrors('objective_id');
        $this->escritura($alumno, 'EN7.SL.1')->assertStatus(422)->assertJsonValidationErrors('objective_id');
        // Y la marca del MCER ya no vale en inglés: el curso declara W/SL.
        $this->actingAs($alumno)->postJson('/api/v1/producciones', [
            'objective_id' => $this->obj('A1.EE.2'), 'unidad' => 7, 'lengua' => 'en',
            'tipo' => 'escritura', 'texto' => 'A text long enough to pass the length rule.',
        ])->assertStatus(422);

        $this->assertSame(0, Produccion::count());
    }

    public function test_la_unidad_tiene_que_existir_en_el_curso(): void
    {
        [, $alumno] = $this->curso();

        // El inglés tiene u7-u9: la u1 es del MCER.
        $this->escritura($alumno, 'EN7.W.1', 1)->assertStatus(422)->assertJsonValidationErrors('unidad');
        $this->assertSame(0, Produccion::count());
    }

    public function test_el_docente_corrige_el_ingles_con_la_rubrica_de_0861(): void
    {
        [$docente, $alumno] = $this->curso();
        $this->escritura($alumno)->assertCreated();
        $p = Produccion::sole();

        $this->actingAs($docente)->get('/docente/producciones')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('producciones.0.rubrica.titulo', 'Escritura · Inglés 0861')
                ->where('producciones.0.rubrica.criterios.0.clave', 'contenido'));

        // Con las claves de A1 no se puede: la rúbrica es la del curso.
        $this->actingAs($docente)->post("/docente/producciones/{$p->id}", [
            'rubrica' => ['tarea' => 2, 'vocabulario' => 1, 'gramatica' => 1, 'ortografia' => 2],
            'comentario' => 'Buen arranque, cuida los conectores entre párrafos.',
        ])->assertSessionHasErrors();
        $this->assertSame('pendiente', $p->refresh()->estado);

        $this->actingAs($docente)->post("/docente/producciones/{$p->id}", [
            'rubrica' => ['contenido' => 2, 'organizacion' => 1, 'lengua' => 1, 'correccion' => 2],
            'comentario' => 'Buen arranque, cuida los conectores entre párrafos.',
        ])->assertRedirect();
        $this->assertSame('corregida', $p->refresh()->estado);
        $this->assertSame(1, $p->rubrica['organizacion']);
    }
}
