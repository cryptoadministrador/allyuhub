<?php

namespace Tests\Feature;

use App\Models\LtiContext;
use App\Models\LtiContextMembership;
use App\Models\LtiPlatform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * FRENTE 0 — la prop compartida que enciende «Panel del curso» en la
 * navegación. Regla dura: el rol es POR CONTEXTO, así que aquí solo viajan
 * los contextos donde el usuario es INSTRUCTOR, con id y título y nada más.
 * Un learner puro recibe es_docente=false y CERO contextos (ni sus ids).
 */
class LayoutSharedPropsTest extends TestCase
{
    use RefreshDatabase;

    private LtiPlatform $platform;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = LtiPlatform::create([
            'issuer' => 'https://moodle.colegio.test', 'client_id' => 'client-abc',
            'auth_login_url' => 'x', 'auth_token_url' => 'x', 'jwks_url' => 'x',
        ]);
    }

    private function contexto(string $id, string $title): LtiContext
    {
        return LtiContext::create([
            'platform_id' => $this->platform->id, 'context_id' => $id, 'title' => $title,
        ]);
    }

    private function membership(LtiContext $c, User $u, string $role): void
    {
        LtiContextMembership::create([
            'lti_context_id' => $c->id, 'user_id' => $u->id,
            'role' => $role, 'last_launched_at' => now(),
        ]);
    }

    public function test_un_learner_puro_no_es_docente_y_no_ve_ningun_contexto(): void
    {
        $alumno = User::factory()->create();
        $curso = $this->contexto('curso-101', 'Física 1.º BGU');
        $this->membership($curso, $alumno, 'learner');

        $this->actingAs($alumno)->get('/progreso')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.es_docente', false)
                ->has('auth.contextos', 0)
            );
    }

    public function test_un_instructor_recibe_solo_sus_contextos_de_instructor(): void
    {
        $profe = $this->docenteCon(['curso-101' => 'Física 1.º BGU']);

        // …y además es ALUMNO de otro curso: ese NO puede aparecer.
        $otro = $this->contexto('curso-202', 'Química');
        $this->membership($otro, $profe, 'learner');

        // …y existe un tercer curso donde no pinta nada.
        $ajeno = $this->contexto('curso-303', 'Historia');
        $this->membership($ajeno, User::factory()->create(), 'instructor');

        $respuesta = $this->actingAs($profe)->get('/progreso')->assertOk();

        $respuesta->assertInertia(fn (Assert $page) => $page
            ->where('auth.es_docente', true)
            ->has('auth.contextos', 1)
            ->where('auth.contextos.0.title', 'Física 1.º BGU')
            // Solo id y título: ni context_id de Moodle, ni platform_id, ni track.
            ->missing('auth.contextos.0.context_id')
            ->missing('auth.contextos.0.platform_id')
            ->missing('auth.contextos.0.track_id')
        );

        // Ni el título ni el id de los cursos ajenos viajan al HTML.
        $this->assertStringNotContainsString('Química', $respuesta->getContent());
        $this->assertStringNotContainsString('Historia', $respuesta->getContent());
        $this->assertStringNotContainsString($ajeno->id, $respuesta->getContent());
        $this->assertStringNotContainsString($otro->id, $respuesta->getContent());
    }

    public function test_el_invitado_no_recibe_ni_usuario_ni_contextos(): void
    {
        // /entrar es la única página sin auth; no debe filtrar nada.
        $this->get('/entrar')->assertOk();

        $alumno = User::factory()->create();
        $this->actingAs($alumno)->get('/progreso')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.id', $alumno->id)
                ->where('auth.es_docente', false)
                ->missing('auth.user.email')
                ->missing('auth.user.lti_sub')
            );
    }

    /**
     * La prop se calcula en UNA consulta acotada por usuario: si el nav la pide
     * en cada página, no puede escalar con el número de cursos de la plataforma.
     */
    public function test_la_prop_compartida_cuesta_una_consulta_fija(): void
    {
        $profe = $this->docenteCon(['c1' => 'Uno', 'c2' => 'Dos']);
        $this->actingAs($profe);

        $contar = function (): int {
            $n = 0;
            DB::listen(function () use (&$n) {
                $n++;
            });
            $this->get('/progreso')->assertOk();

            return $n;
        };

        $pocos = $contar();

        // Diez cursos más de otro docente: no son suyos, no deben costarle nada.
        $ajeno = User::factory()->create();
        foreach (range(1, 10) as $i) {
            $c = $this->contexto("ajeno-{$i}", "Ajeno {$i}");
            $this->membership($c, $ajeno, 'instructor');
        }

        $this->assertSame($pocos, $contar(), 'La prop compartida escala con cursos ajenos');
    }

    /**
     * REGRESIÓN: share() se ejecuta en TODO el grupo web, también en los
     * endpoints JSON de práctica, que no llevan props Inertia. Como prop
     * diferida (closure), la consulta solo se paga al pintar una página —
     * antes se cobraba en cada respuesta del alumno (auditoría).
     */
    public function test_la_api_json_no_paga_la_consulta_de_contextos(): void
    {
        $this->actingAs($this->docenteCon(['c1' => 'Uno']));

        $consultas = [];
        DB::listen(function ($q) use (&$consultas) {
            $consultas[] = $q->sql;
        });

        $this->getJson('/api/v1/practice/mastery')->assertOk();

        $this->assertEmpty(
            array_filter($consultas, fn ($sql) => str_contains($sql, 'lti_contexts')),
            'La respuesta JSON consulta los contextos docentes sin necesitarlos',
        );
    }

    /** @param  array<string,string>  $cursos  context_id => título */
    private function docenteCon(array $cursos): User
    {
        $profe = User::factory()->create();
        foreach ($cursos as $id => $titulo) {
            $this->membership($this->contexto($id, $titulo), $profe, 'instructor');
        }

        return $profe;
    }
}
