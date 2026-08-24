<?php

namespace Tests\Feature;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\LtiPlatform;
use App\Models\ObjectiveMastery;
use App\Models\PracticeItem;
use App\Models\Resource;
use App\Models\ResourceVersion;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Páginas Inertia (fase B frontend): exigen sesión, montan el componente
 * correcto con props MÍNIMAS (jamás solution_expr/seed/expected), y toda la
 * app es embebible SOLO desde las Platforms LTI registradas (frame-ancestors).
 */
class AppPagesTest extends TestCase
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
        $node = CurNode::create([
            'version_id' => $version->id, 'node_type' => 'grado',
            'native_code' => 'g11', 'title' => ['es' => '1.º BGU'], 'path' => 'bgu.g11',
        ]);
        $this->objective = LearningObjective::create([
            'node_id' => $node->id, 'version_id' => $version->id,
            'native_code' => 'CN.F.5.1.9', 'statement' => ['es' => 'Plano inclinado…'],
        ]);
        PracticeItem::create([
            'objective_id' => $this->objective->id,
            'statement' => ['es' => 'm={m}'], 'params' => ['m' => ['min' => 1, 'max' => 5, 'step' => 1]],
            'solution_expr' => 'm', 'tolerance' => 0.02, 'tolerance_kind' => 'rel',
            // Firmado: este fixture prueba el MOTOR, y un ítem sin
            // revisar no llega al motor (ver DominioYFirmaTest).
            'reviewed_at' => now(),
        ]);

        $this->ana = User::factory()->create();
    }

    /**
     * Contenido abierto: practicar ya no exige sesión. Lo que el invitado NO
     * recibe es dominio guardado — esa prop es la que distingue una visita de
     * un expediente.
     */
    public function test_practicar_esta_abierto_pero_sin_dominio_guardado(): void
    {
        $this->get("/practicar/{$this->objective->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Practicar')
                ->where('mastery', null)
                ->where('auth.user', null)
            );
    }

    public function test_practicar_monta_el_componente_con_props_minimas(): void
    {
        ObjectiveMastery::create([
            'user_id' => $this->ana->id, 'objective_id' => $this->objective->id,
            'mastery' => 0.35, 'streak' => 1, 'attempts_count' => 1,
        ]);

        $this->actingAs($this->ana)
            ->get("/practicar/{$this->objective->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Practicar')
                ->where('objective.id', $this->objective->id)
                ->where('objective.native_code', 'CN.F.5.1.9')
                ->where('objective.statement', 'Plano inclinado…')
                ->where('objective.has_items', true)
                ->where('mastery', 0.35)
                // ORÁCULO frontend: nada sensible viaja en las props.
                ->missing('objective.solution_expr')
                ->missing('item')
                ->missing('seed')
                // El usuario compartido lleva SOLO id y nombre.
                ->where('auth.user.id', $this->ana->id)
                ->missing('auth.user.email')
                ->missing('auth.user.password')
                ->missing('auth.user.lti_sub')
            );
    }

    public function test_practicar_avisa_cuando_la_destreza_no_tiene_items(): void
    {
        $sinItems = LearningObjective::create([
            'node_id' => $this->objective->node_id, 'version_id' => $this->objective->version_id,
            'native_code' => 'CN.F.5.1.4', 'statement' => ['es' => 'Sin ítems aún'],
        ]);

        // La página carga igual (estado vacío digno): el prop lo dice y el
        // componente muestra el aviso en vez de un fetch roto.
        $this->actingAs($this->ana)
            ->get("/practicar/{$sinItems->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Practicar')
                ->where('objective.has_items', false)
                ->where('mastery', null)
            );
    }

    public function test_recurso_publicado_se_muestra_y_borrador_no_existe(): void
    {
        $sim = Resource::create([
            'slug' => 'plano-inclinado', 'kind' => 'lab',
            'origen' => Resource::CURADO,
            'title' => ['es' => 'Laboratorio: plano inclinado'], 'status' => 'published',
        ]);
        $v = ResourceVersion::create([
            'resource_id' => $sim->id, 'semver' => '1.0.0',
            'bundle_url' => 'https://cdn.allyuhub.test/sims/plano/1.0.0/',
            'published_at' => now(),
        ]);
        $sim->update(['current_version_id' => $v->id]);

        $this->actingAs($this->ana)
            ->get("/recurso/{$sim->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Recurso')
                ->where('recurso.title', 'Laboratorio: plano inclinado')
                ->where('recurso.bundle_url', 'https://cdn.allyuhub.test/sims/plano/1.0.0/')
                // Un simulador no tiene bloques de lectura, y el lector no se
                // los inventa: pinta su enlace al bundle.
                ->where('recurso.bloques', [])
            );

        $draft = Resource::create([
            'slug' => 'borrador', 'kind' => 'lab',
            'origen' => Resource::CURADO,
            'title' => ['es' => 'Borrador'], 'status' => 'draft',
        ]);
        $this->actingAs($this->ana)->get("/recurso/{$draft->id}")->assertNotFound();
    }

    public function test_progreso_monta_el_componente_con_los_tracks(): void
    {
        Track::create(['code' => 'ORD', 'label' => ['es' => 'Ordinaria']]);
        Track::create(['code' => 'PCEI-BI', 'label' => ['es' => 'Bachillerato Intensivo']]);

        $this->actingAs($this->ana)
            ->get('/progreso')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Progreso')
                ->has('tracks', 2)
                ->where('tracks.0.code', 'ORD')
            );
    }

    /**
     * La lista de orígenes se cachea (el middleware corre en CADA respuesta y
     * la portada pública no puede pagar una consulta por visita), pero la
     * caché no puede dejar la CSP atrasada: registrar o desactivar una
     * Platform tiene efecto en la siguiente respuesta, sin esperar al TTL.
     */
    public function test_la_csp_se_actualiza_al_registrar_o_desactivar_una_platform(): void
    {
        $ruta = "/practicar/{$this->objective->id}";
        $csp = fn () => $this->actingAs($this->ana)->get($ruta)
            ->headers->get('Content-Security-Policy');

        $this->assertSame("frame-ancestors 'self'", $csp());   // calienta la caché

        $moodle = LtiPlatform::create([
            'issuer' => 'https://moodle.colegio.test',
            'client_id' => 'client-abc',
            'auth_login_url' => 'x', 'auth_token_url' => 'x', 'jwks_url' => 'x',
        ]);
        $this->assertSame("frame-ancestors 'self' https://moodle.colegio.test", $csp());

        $moodle->update(['is_active' => false]);
        $this->assertSame("frame-ancestors 'self'", $csp());

        $moodle->update(['is_active' => true]);
        $moodle->delete();
        $this->assertSame("frame-ancestors 'self'", $csp());
    }

    public function test_frame_ancestors_solo_las_platforms_registradas(): void
    {
        LtiPlatform::create([
            'issuer' => 'https://moodle.colegio.test',
            'client_id' => 'client-abc',
            'auth_login_url' => 'x', 'auth_token_url' => 'x', 'jwks_url' => 'x',
        ]);
        // Una desactivada NO entra en la lista.
        LtiPlatform::create([
            'issuer' => 'https://moodle-viejo.test',
            'client_id' => 'client-z',
            'auth_login_url' => 'x', 'auth_token_url' => 'x', 'jwks_url' => 'x',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->ana)->get("/practicar/{$this->objective->id}");

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'self' https://moodle.colegio.test", $csp);
        $this->assertStringNotContainsString('moodle-viejo', $csp);
        $this->assertStringNotContainsString('*', $csp);
        // Nada de X-Frame-Options: DENY — la app VIVE en el iframe de Moodle.
        $this->assertNotSame('DENY', $response->headers->get('X-Frame-Options'));
    }

    /**
     * REGRESIÓN DE SEGURIDAD (auditoría frontend): un issuer mal pegado no
     * puede colar un comodín en la CSP. Con la regex anterior,
     * «https://x.test *» pasaba verbatim y abría el iframe a cualquier origen
     * (clickjacking sobre el bucle de práctica). Ahora se normaliza con
     * parse_url y lo que no sea una URL http(s) limpia se DESCARTA entero —
     * fail-closed: no se adivina qué quiso escribir el admin.
     */
    public function test_un_issuer_malformado_no_cuela_un_comodin_en_la_csp(): void
    {
        foreach ([
            'https://moodle.test *',     // typo del admin al registrar
            'urn:moodle:1',              // no es URL
            '*',                         // directamente el comodín
            'moodle-sin-esquema.test',   // sin http(s)
            "https://x.test';",          // intento de cerrar la directiva
        ] as $i => $issuer) {
            LtiPlatform::create([
                'issuer' => $issuer, 'client_id' => "c-{$i}",
                'auth_login_url' => 'x', 'auth_token_url' => 'x', 'jwks_url' => 'x',
            ]);
        }
        // Una bien formada convive con las basura y sí entra, con su puerto.
        LtiPlatform::create([
            'issuer' => 'https://moodle.colegio.test:8443/moodle',
            'client_id' => 'c-ok',
            'auth_login_url' => 'x', 'auth_token_url' => 'x', 'jwks_url' => 'x',
        ]);

        $csp = $this->actingAs($this->ana)
            ->get("/practicar/{$this->objective->id}")
            ->headers->get('Content-Security-Policy');

        $this->assertSame("frame-ancestors 'self' https://moodle.colegio.test:8443", $csp);
    }

    /**
     * El launch de Moodle llega cross-site y necesita ir sin CSRF; el resto de
     * la app NO. La suite no evalúa CSRF (PreventRequestForgery lo salta en
     * tests), así que sin esta comprobación se podría borrar la excepción —o
     * ampliarla a toda la app— sin que ningún test se pusiera rojo.
     */
    public function test_la_excepcion_de_csrf_cubre_solo_las_rutas_lti(): void
    {
        $middleware = app(PreventRequestForgery::class);
        $this->assertSame(['lti/*'], array_values($middleware->getExcludedPaths()));

        $inExcept = function (string $uri) use ($middleware) {
            $method = new \ReflectionMethod($middleware, 'inExceptArray');

            return $method->invoke($middleware, Request::create('/'.ltrim($uri, '/'), 'POST'));
        };

        $this->assertTrue($inExcept('lti/launch'));
        $this->assertTrue($inExcept('lti/deep-link'));
        $this->assertFalse($inExcept('api/v1/practice/items/abc/attempts'));
        $this->assertFalse($inExcept('progreso'));
        $this->assertFalse($inExcept('/'));
    }
}
