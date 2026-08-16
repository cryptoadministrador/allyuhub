<?php

namespace Tests\Feature;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\LtiContext;
use App\Models\LtiContextMembership;
use App\Models\LtiPlatform;
use App\Models\ObjectiveMastery;
use App\Models\PracticeItem;
use App\Models\Track;
use App\Models\TrackPhase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * El panel del docente (frentes 2 y 3): ORÁCULO 1 — IDOR es el enemigo n.º 1.
 * Matriz completa de accesos, privacidad de props (id y name, punto) y el
 * mapeo curso→track con la misma autorización dura.
 */
class DocentePanelTest extends TestCase
{
    use RefreshDatabase;

    private LtiContext $cursoA;

    private LtiContext $cursoB;

    private User $profe;      // instructor de A

    private User $profeB;     // instructor de B (ajeno a A)

    private User $alumno1;    // learner de A, con avance

    private User $alumno2;    // learner de A, sin empezar (el rezagado)

    private User $alumnoB;    // learner de B — JAMÁS visible en A

    private User $intruso;    // sin membership alguna

    private Track $track;

    private LearningObjective $conItems;

    private LearningObjective $sinItems;

    protected function setUp(): void
    {
        parent::setUp();

        $platform = LtiPlatform::create([
            'issuer' => 'https://moodle.colegio.test', 'client_id' => 'client-abc',
            'auth_login_url' => 'x', 'auth_token_url' => 'x', 'jwks_url' => 'x',
        ]);
        $this->cursoA = LtiContext::create([
            'platform_id' => $platform->id, 'context_id' => 'curso-101', 'title' => 'Física 1.º BGU',
        ]);
        $this->cursoB = LtiContext::create([
            'platform_id' => $platform->id, 'context_id' => 'curso-202', 'title' => 'Química',
        ]);

        [$this->profe, $this->profeB, $this->alumno1, $this->alumno2, $this->alumnoB, $this->intruso] =
            User::factory()->count(6)->create()->all();

        $this->membership($this->cursoA, $this->profe, 'instructor');
        $this->membership($this->cursoA, $this->alumno1, 'learner', '2026-08-14T10:00:00Z');
        $this->membership($this->cursoA, $this->alumno2, 'learner', '2026-08-01T10:00:00Z');
        $this->membership($this->cursoB, $this->profeB, 'instructor');
        $this->membership($this->cursoB, $this->alumnoB, 'learner');

        // Un track con 3 destrezas (2 con ítems) para el mapeo del frente 3.
        $fw = Framework::create([
            'code' => 'EC-MINEDEC', 'authority' => 'MINEDEC', 'kind' => 'national',
            'country' => 'EC', 'label' => ['es' => 'Currículo Nacional'],
        ]);
        $version = FrameworkVersion::create(['framework_id' => $fw->id, 'label' => '2016+2023']);
        $node = CurNode::create([
            'version_id' => $version->id, 'node_type' => 'grado',
            'native_code' => 'g11', 'title' => ['es' => '1.º BGU'], 'path' => 'bgu.g11',
        ]);
        $objetivos = collect(['CN.F.5.1.9', 'CN.F.5.1.12', 'CN.F.5.1.4'])->map(
            fn ($code) => LearningObjective::create([
                'node_id' => $node->id, 'version_id' => $version->id,
                'native_code' => $code, 'statement' => ['es' => $code],
            ]),
        );
        [$this->conItems, $segunda, $this->sinItems] = $objetivos->all();
        foreach ([$this->conItems, $segunda] as $objetivo) {
            PracticeItem::create([
                'objective_id' => $objetivo->id,
                'statement' => ['es' => 'm={m}'], 'params' => ['m' => ['min' => 1, 'max' => 5, 'step' => 1]],
                'solution_expr' => 'm', 'tolerance' => 0.02, 'tolerance_kind' => 'rel',
            ]);
        }

        $this->track = Track::create(['code' => 'ORD', 'label' => ['es' => 'Ordinaria']]);
        $fase = TrackPhase::create([
            'track_id' => $this->track->id, 'seq' => 1, 'label' => ['es' => 'Fase 1'],
        ]);
        $fase->objectives()->attach(
            $objetivos->pluck('id')->mapWithKeys(fn ($id) => [$id => ['source' => 'mapeo-interno']])->all(),
        );

        // alumno1: una dominada y una en progreso; alumno2: nada de nada.
        ObjectiveMastery::create([
            'user_id' => $this->alumno1->id, 'objective_id' => $this->conItems->id,
            'mastery' => 0.85, 'streak' => 4, 'attempts_count' => 5, 'mastered_at' => now(),
        ]);
        ObjectiveMastery::create([
            'user_id' => $this->alumno1->id, 'objective_id' => $segunda->id,
            'mastery' => 0.35, 'streak' => 1, 'attempts_count' => 1,
        ]);
    }

    private function membership(LtiContext $context, User $user, string $role, ?string $launchedAt = null): void
    {
        LtiContextMembership::create([
            'lti_context_id' => $context->id, 'user_id' => $user->id,
            'role' => $role, 'last_launched_at' => $launchedAt ?? now(),
        ]);
    }

    /** ORÁCULO 1: la matriz completa del panel. */
    public function test_matriz_de_acceso_al_panel(): void
    {
        // Instructor propio: 200.
        $this->actingAs($this->profe)->get("/docente/{$this->cursoA->id}")->assertOk();
        // Learner del propio contexto: 403.
        $this->actingAs($this->alumno1)->get("/docente/{$this->cursoA->id}")->assertForbidden();
        // Instructor de OTRO contexto: 403.
        $this->actingAs($this->profeB)->get("/docente/{$this->cursoA->id}")->assertForbidden();
        // Sin membership: 403.
        $this->actingAs($this->intruso)->get("/docente/{$this->cursoA->id}")->assertForbidden();
        // Anónimo: a /entrar.
        auth()->logout();
        $this->flushSession();
        $this->get("/docente/{$this->cursoA->id}")->assertRedirect('/entrar');
    }

    /** ORÁCULO 1 (bis): la misma matriz para el POST del track. */
    public function test_matriz_de_acceso_al_post_del_track(): void
    {
        $payload = ['track_id' => $this->track->id];
        $url = "/docente/{$this->cursoA->id}/track";

        $this->actingAs($this->alumno1)->post($url, $payload)->assertForbidden();
        $this->actingAs($this->profeB)->post($url, $payload)->assertForbidden();
        $this->actingAs($this->intruso)->post($url, $payload)->assertForbidden();
        $this->assertNull($this->cursoA->fresh()->track_id);   // nadie lo tocó

        $this->actingAs($this->profe)->post($url, $payload)->assertRedirect("/docente/{$this->cursoA->id}");
        $this->assertSame($this->track->id, $this->cursoA->fresh()->track_id);

        // Cambiarlo después está permitido (es una corrección).
        $otro = Track::create(['code' => 'PCEI-BI', 'label' => ['es' => 'Bachillerato Intensivo']]);
        $this->actingAs($this->profe)->post($url, ['track_id' => $otro->id])->assertRedirect();
        $this->assertSame($otro->id, $this->cursoA->fresh()->track_id);

        // Un track inexistente: 422, no un 500 ni un null silencioso.
        $this->actingAs($this->profe)->from("/docente/{$this->cursoA->id}")
            ->post($url, ['track_id' => '00000000-0000-0000-0000-000000000000'])
            ->assertSessionHasErrors('track_id');
    }

    public function test_panel_sin_track_avisa_y_no_calcula_progreso(): void
    {
        $this->actingAs($this->profe)->get("/docente/{$this->cursoA->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('docente')
                ->where('context.title', 'Física 1.º BGU')
                ->where('track', null)
                ->where('objectives_summary', null)
                ->has('tracks', 1)
                ->has('students', 2)
            );
    }

    public function test_panel_con_track_cuenta_y_ordena_rezagados_primero(): void
    {
        $this->cursoA->update(['track_id' => $this->track->id]);

        $this->actingAs($this->profe)->get("/docente/{$this->cursoA->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('docente')
                ->where('track.code', 'ORD')
                ->where('objectives_summary.total', 3)
                ->where('objectives_summary.con_items', 2)
                ->has('students', 2)
                // El rezagado (alumno2: 0 dominadas) va PRIMERO.
                ->where('students.0.id', $this->alumno2->id)
                ->where('students.0.dominadas', 0)
                ->where('students.0.en_progreso', 0)
                ->where('students.0.sin_empezar', 3)
                ->where('students.1.id', $this->alumno1->id)
                ->where('students.1.dominadas', 1)
                ->where('students.1.en_progreso', 1)
                ->where('students.1.sin_empezar', 1)
                // PRIVACIDAD: id y name, punto.
                ->missing('students.0.email')
                ->missing('students.0.lti_sub')
                ->missing('students.0.password')
                ->missing('students.1.email')
            );
    }

    /** ORÁCULO 1 (ter): los alumnos de OTRO contexto jamás aparecen. */
    public function test_el_panel_no_filtra_alumnos_de_otros_contextos(): void
    {
        $respuesta = $this->actingAs($this->profe)->get("/docente/{$this->cursoA->id}");

        $ids = collect($respuesta->inertiaPage()['props']['students'])->pluck('id');
        $this->assertNotContains($this->alumnoB->id, $ids);
        // Ni siquiera su nombre viaja en el HTML.
        $this->assertStringNotContainsString($this->alumnoB->name, $respuesta->getContent());
        // El instructor tampoco sale listado como alumno.
        $this->assertNotContains($this->profe->id, $ids);
    }

    public function test_detalle_de_alumno_con_su_matriz_de_acceso(): void
    {
        $this->cursoA->update(['track_id' => $this->track->id]);
        $url = "/docente/{$this->cursoA->id}/alumno/{$this->alumno1->id}";

        // La matriz: solo el instructor del contexto.
        $this->actingAs($this->alumno1)->getJson($url)->assertForbidden();
        $this->actingAs($this->profeB)->getJson($url)->assertForbidden();

        // Un alumno de OTRO contexto pedido a través de A: 404 (no existe PARA A).
        $this->actingAs($this->profe)
            ->getJson("/docente/{$this->cursoA->id}/alumno/{$this->alumnoB->id}")
            ->assertNotFound();
        // El propio instructor tampoco es un «alumno» consultable.
        $this->actingAs($this->profe)
            ->getJson("/docente/{$this->cursoA->id}/alumno/{$this->profe->id}")
            ->assertNotFound();

        // El instructor propio ve el mastery POR DESTREZA del track.
        $json = $this->actingAs($this->profe)->getJson($url)->assertOk()->json('destrezas');
        $this->assertCount(3, $json);
        $porCodigo = collect($json)->keyBy('native_code');
        $this->assertEqualsWithDelta(0.85, $porCodigo['CN.F.5.1.9']['mastery'], 1e-6);
        $this->assertTrue($porCodigo['CN.F.5.1.9']['is_mastered']);
        $this->assertEqualsWithDelta(0.35, $porCodigo['CN.F.5.1.12']['mastery'], 1e-6);
        $this->assertSame(0, $porCodigo['CN.F.5.1.4']['mastery']);
    }

    public function test_contexto_inexistente_es_404(): void
    {
        $this->actingAs($this->profe)
            ->get('/docente/00000000-0000-0000-0000-000000000000')
            ->assertNotFound();
    }

    /**
     * Auditoría: un {context} o {user} MALFORMADO en la URL es 404, no 500.
     * En SQLite el binding ya da 404 (no tipa); el whereUuid/whereNumber de la
     * ruta es lo que garantiza el 404 también en el PostgreSQL del CI, donde un
     * id no-uuid revienta el binding con «invalid input syntax».
     */
    public function test_ids_malformados_en_la_url_son_404_no_500(): void
    {
        $this->actingAs($this->profe);
        $this->get('/docente/no-es-uuid')->assertNotFound();
        $this->get("/docente/{$this->cursoA->id}/alumno/no-es-numero")->assertNotFound();
        $this->post('/docente/no-es-uuid/track', ['track_id' => $this->track->id])->assertNotFound();
    }

    /** Auditoría: cerrar los huecos de la matriz — anónimo en POST y en alumno. */
    public function test_anonimo_en_post_track_y_en_alumno(): void
    {
        auth()->logout();
        $this->flushSession();

        $this->post("/docente/{$this->cursoA->id}/track", ['track_id' => $this->track->id])
            ->assertRedirect('/entrar');
        $this->get("/docente/{$this->cursoA->id}/alumno/{$this->alumno1->id}")
            ->assertRedirect('/entrar');
    }

    /** Auditoría: aunque una prop trajera un campo de más, no llega al HTML. */
    public function test_ningun_dato_sensible_de_alumno_en_el_html(): void
    {
        $this->cursoA->update(['track_id' => $this->track->id]);
        $this->alumno1->update(['email' => 'ana.secreta@colegio.test']);

        $html = $this->actingAs($this->profe)->get("/docente/{$this->cursoA->id}")->getContent();

        $this->assertStringNotContainsString('ana.secreta@colegio.test', $html);
        $this->assertStringNotContainsString('lti_sub', $html);
        $this->assertStringNotContainsString('@colegio.test', $html);
    }
}
