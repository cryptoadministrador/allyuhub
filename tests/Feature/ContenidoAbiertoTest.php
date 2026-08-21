<?php

namespace Tests\Feature;

use App\Jobs\PushLtiScore;
use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\LtiPlatform;
use App\Models\LtiResourceLink;
use App\Models\ObjectiveMastery;
use App\Models\PracticeAttempt;
use App\Models\PracticeItem;
use App\Models\Resource;
use App\Models\Track;
use App\Models\TrackPhase;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * ORÁCULOS de la misión «contenido abierto» (modelo Khan).
 *
 * La frontera, en una línea: se NAVEGA y se PRACTICA sin sesión; se GUARDA y se
 * CALIFICA solo con sesión LTI. Y la regla de oro: un invitado que practica no
 * escribe NI UNA FILA atribuida a un usuario — ni intento, ni dominio, ni AGS.
 *
 * Estos tests se escribieron en ROJO antes que el código.
 */
class ContenidoAbiertoTest extends TestCase
{
    use RefreshDatabase;

    private const ITEM_ID = '0198e2c0-0000-7000-8000-00000000ab01';

    private LearningObjective $objective;

    private CurNode $grado;

    private User $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $fw = Framework::create([
            'code' => 'EC-MINEDEC', 'authority' => 'MINEDEC', 'kind' => 'national',
            'country' => 'EC', 'label' => ['es' => 'Currículo Nacional'],
        ]);
        $version = FrameworkVersion::create(['framework_id' => $fw->id, 'label' => '2016+2023']);
        $this->grado = CurNode::create([
            'version_id' => $version->id, 'node_type' => 'grado',
            'native_code' => 'g11', 'title' => ['es' => '1.º BGU'], 'path' => 'bgu.g11',
        ]);
        $this->objective = LearningObjective::create([
            'node_id' => $this->grado->id, 'version_id' => $version->id,
            'native_code' => 'CN.F.5.1.9', 'statement' => ['es' => 'Plano inclinado…'],
            'is_verified' => true,
        ]);
        // Ítem con solución CERRADA (sin parámetros aleatorios): así el test
        // puede calcular la respuesta correcta sin depender de la semilla.
        PracticeItem::create([
            'id' => self::ITEM_ID,
            'objective_id' => $this->objective->id,
            'statement' => ['es' => 'Suma {a} + {b}'],
            'params' => [
                'a' => ['const' => 2],
                'b' => ['const' => 3],
            ],
            'solution_expr' => 'a + b',
            'tolerance' => 0.01, 'tolerance_kind' => 'abs',
            // Firmado: este fixture prueba el MOTOR, y un ítem sin
            // revisar no llega al motor (ver DominioYFirmaTest).
            'reviewed_at' => now(),
        ]);

        $this->ana = User::factory()->create();
    }

    // ================= ORÁCULO 1 — el invitado NAVEGA =================

    public function test_invitado_navega_el_catalogo_la_ficha_y_la_busqueda(): void
    {
        $this->get('/catalogo')->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('catalogo'));

        $this->get("/catalogo/{$this->grado->id}")->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('catalogo-nodo'));

        $this->get("/destreza/{$this->objective->id}")->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('destreza'));

        $this->get('/buscar')->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('buscar'));

        $this->get('/buscar?q=plano')->assertOk();
    }

    public function test_invitado_abre_la_pagina_de_practicar(): void
    {
        $this->get("/practicar/{$this->objective->id}")->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Practicar')
                // Sin sesión no hay dominio guardado que mostrar.
                ->where('mastery', null)
                ->where('auth.user', null),
            );
    }

    public function test_invitado_abre_un_recurso_publicado(): void
    {
        $recurso = Resource::create([
            'slug' => 'sim', 'kind' => 'lab', 'title' => ['es' => 'Sim'], 'status' => 'published',
        ]);

        $this->get("/recurso/{$recurso->id}")->assertOk();
    }

    // ============ ORÁCULO 2 — el invitado PRACTICA y se le CORRIGE ============

    public function test_invitado_pide_item_y_recibe_correccion_en_el_servidor(): void
    {
        $siguiente = $this->getJson("/api/v1/objectives/{$this->objective->id}/practice/next")
            ->assertOk()
            ->assertJsonStructure(['item_id', 'objective_id', 'statement', 'attempt_no', 'reason'])
            ->json();

        // La solución JAMÁS viaja antes de responder.
        $this->assertArrayNotHasKey('expected', $siguiente);
        $this->assertArrayNotHasKey('solution_expr', $siguiente);

        // Respuesta CORRECTA (2 + 3): veredicto correcto.
        $this->postJson("/api/v1/practice/items/{$siguiente['item_id']}/attempts", ['answer' => 5])
            ->assertOk()
            ->assertJsonPath('is_correct', true)
            ->assertJsonPath('expected', 5);

        // Respuesta MALA: veredicto incorrecto, y llega la explicación (expected).
        $this->postJson("/api/v1/practice/items/{$siguiente['item_id']}/attempts", ['answer' => 99])
            ->assertOk()
            ->assertJsonPath('is_correct', false)
            ->assertJsonPath('expected', 5);
    }

    // ========== ORÁCULO 3 — LA REGLA DE ORO: el invitado no escribe nada ==========

    public function test_regla_de_oro_un_ciclo_de_invitado_no_escribe_ni_una_fila(): void
    {
        Queue::fake();

        $antes = [
            'attempts' => PracticeAttempt::count(),
            'masteries' => ObjectiveMastery::count(),
            'users' => User::count(),
        ];

        // Un ciclo COMPLETO de práctica de invitado: pedir y responder varias veces.
        for ($i = 0; $i < 3; $i++) {
            $item = $this->getJson("/api/v1/objectives/{$this->objective->id}/practice/next")
                ->assertOk()->json();
            $this->postJson("/api/v1/practice/items/{$item['item_id']}/attempts", ['answer' => 5])
                ->assertOk();
            $this->postJson("/api/v1/practice/items/{$item['item_id']}/attempts", ['answer' => 1])
                ->assertOk();
        }

        $despues = [
            'attempts' => PracticeAttempt::count(),
            'masteries' => ObjectiveMastery::count(),
            'users' => User::count(),
        ];

        $this->assertSame($antes, $despues, 'Un invitado escribió filas: la regla de oro está rota.');

        // Y ni una nota viajó al aula.
        Queue::assertNothingPushed();
    }

    public function test_invitado_nunca_dispara_ags_aunque_exista_un_resource_link(): void
    {
        Queue::fake();
        $this->crearResourceLink($this->ana);   // el link existe, pero es de Ana

        $item = $this->getJson("/api/v1/objectives/{$this->objective->id}/practice/next")->json();
        $this->postJson("/api/v1/practice/items/{$item['item_id']}/attempts", ['answer' => 5])->assertOk();

        Queue::assertNotPushed(PushLtiScore::class);
    }

    // ======== ORÁCULO 4 — con sesión SÍ persiste (no-regresión) ========

    public function test_con_sesion_el_ciclo_persiste_mueve_el_dominio_y_dispara_ags(): void
    {
        Queue::fake();
        $this->crearResourceLink($this->ana);
        $this->actingAs($this->ana);

        $item = $this->getJson("/api/v1/objectives/{$this->objective->id}/practice/next")
            ->assertOk()->json();

        $this->postJson("/api/v1/practice/items/{$item['item_id']}/attempts", ['answer' => 5])
            ->assertCreated()
            ->assertJsonPath('is_correct', true);

        // Un segundo ítem DISTINTO: la nota no viaja al aula mientras el alumno
        // solo haya acertado uno (ver DominioYFirmaTest).
        $segundo = PracticeItem::create([
            'objective_id' => $this->objective->id, 'seq' => 1,
            'statement' => ['es' => 'Suma {a} + {b}'],
            'params' => ['a' => ['const' => 4], 'b' => ['const' => 4]],
            'solution_expr' => 'a + b', 'tolerance' => 0.01, 'tolerance_kind' => 'abs',
            'reviewed_at' => now(),
        ]);
        $this->postJson("/api/v1/practice/items/{$segundo->id}/attempts", ['answer' => 8])
            ->assertCreated()
            ->assertJsonPath('is_correct', true);

        // Un segundo ítem DISTINTO: la nota no viaja al aula mientras el alumno
        // solo haya acertado uno (ver DominioYFirmaTest).
        $segundo = PracticeItem::create([
            'objective_id' => $this->objective->id, 'seq' => 1,
            'statement' => ['es' => 'Suma {a} + {b}'],
            'params' => ['a' => ['const' => 4], 'b' => ['const' => 4]],
            'solution_expr' => 'a + b', 'tolerance' => 0.01, 'tolerance_kind' => 'abs',
            'reviewed_at' => now(),
        ]);
        $this->postJson("/api/v1/practice/items/{$segundo->id}/attempts", ['answer' => 8])
            ->assertCreated()
            ->assertJsonPath('is_correct', true);

        $this->assertDatabaseHas('practice_attempts', [
            'item_id' => self::ITEM_ID,
            'user_id' => $this->ana->id,
            'is_correct' => true,
        ]);

        $dominio = ObjectiveMastery::where('user_id', $this->ana->id)
            ->where('objective_id', $this->objective->id)->first();
        $this->assertNotNull($dominio, 'El dominio del alumno no se creó.');
        $this->assertGreaterThan(0, (float) $dominio->mastery);

        Queue::assertPushed(PushLtiScore::class);
    }

    // ======== ORÁCULO 5 — lo del alumno sigue cerrado ========

    public function test_la_casa_del_alumno_y_el_panel_docente_siguen_pidiendo_sesion(): void
    {
        foreach ([
            '/inicio',
            '/progreso',
            '/docente/0198e2c0-0000-7000-8000-0000000000aa',
        ] as $url) {
            $this->get($url)->assertRedirect('/entrar', "{$url} quedó abierta al mundo");
        }
    }

    // ======== ORÁCULO 6 — user_id sigue prohibido (con y sin sesión) ========

    public function test_user_id_en_el_payload_es_422_para_invitado_y_para_alumno(): void
    {
        $luis = User::factory()->create();

        // Sin sesión.
        $this->getJson("/api/v1/objectives/{$this->objective->id}/practice/next?user_id={$luis->id}")
            ->assertStatus(422)->assertJsonValidationErrors('user_id');
        $this->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', [
            'user_id' => $luis->id, 'answer' => 5,
        ])->assertStatus(422)->assertJsonValidationErrors('user_id');

        // Con sesión.
        $this->actingAs($this->ana);
        $this->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', [
            'user_id' => $luis->id, 'answer' => 5,
        ])->assertStatus(422)->assertJsonValidationErrors('user_id');
    }

    /**
     * MUTACIÓN SUPERVIVIENTE (bucle B): hacer que el servidor se creyera el
     * `intento` del ALUMNO no ponía nada rojo, y es un agujero de trampa de
     * libro. El alumno ya conoce el `expected` del intento 1 —se le revela al
     * responder—, así que si pudiera fijar el número de intento repetiría
     * eternamente la misma instancia con la respuesta ya sabida: dominio a 1.0
     * y un 100 en el gradebook de Moodle sin resolver nada.
     *
     * Su número de intento sale de la BASE, y punto. El campo existe para el
     * invitado, que no tiene base donde mirar.
     */
    public function test_al_alumno_no_se_le_cree_el_numero_de_intento(): void
    {
        $this->actingAs($this->ana);

        // Aunque pida el ítem 300, se le sirve su intento real: el 1.
        $this->getJson("/api/v1/objectives/{$this->objective->id}/practice/next?intento=300")
            ->assertOk()
            ->assertJsonPath('attempt_no', 1);

        // Y aunque diga responder al 99, se registra el que le toca.
        $this->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', [
            'answer' => 5, 'intento' => 99,
        ])->assertCreated()->assertJsonPath('attempt_no', 1);

        $this->assertDatabaseHas('practice_attempts', [
            'item_id' => self::ITEM_ID, 'user_id' => $this->ana->id, 'attempt_no' => 1,
        ]);
        $this->assertDatabaseMissing('practice_attempts', ['attempt_no' => 99]);

        // El segundo intento es el 2, no el 100: la cuenta la lleva el servidor.
        $this->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', [
            'answer' => 5, 'intento' => 99,
        ])->assertCreated()->assertJsonPath('attempt_no', 2);
    }

    // ======== Dominio/progreso del invitado: 200 vacío, jamás el de otro ========

    public function test_mastery_y_progress_de_invitado_no_filtran_el_avance_de_otro(): void
    {
        // Ana tiene dominio real sobre la destreza.
        ObjectiveMastery::create([
            'user_id' => $this->ana->id,
            'objective_id' => $this->objective->id,
            'mastery' => 0.9, 'attempts_count' => 10, 'streak' => 3,
            'mastered_at' => now(), 'last_attempt_at' => now(),
        ]);
        $track = Track::create(['code' => 'ORD', 'label' => ['es' => 'Ordinaria']]);
        $fase = TrackPhase::create([
            'track_id' => $track->id, 'seq' => 1, 'label' => ['es' => 'Fase 1'],
        ]);
        DB::table('track_phase_objectives')->insert([
            'phase_id' => $fase->id, 'objective_id' => $this->objective->id, 'weight' => 1,
        ]);

        // El invitado recibe 200 con un estado SIN GUARDAR, no el de Ana.
        $this->getJson('/api/v1/practice/mastery')->assertOk()->assertExactJson([]);

        $progreso = $this->getJson('/api/v1/practice/progress?track=ORD')->assertOk()->json();
        $this->assertSame(0, $progreso['phases'][0]['mastered'], 'Se filtró el dominio de otro alumno.');
        $this->assertSame(0, $progreso['phases'][0]['in_progress']);
        $this->assertFalse($progreso['se_guarda'], 'El invitado debe saber que no se guarda.');
    }

    // ======== ORÁCULO 8 — throttle del invitado ========

    public function test_el_invitado_topa_con_el_limite_de_peticiones(): void
    {
        $url = "/api/v1/objectives/{$this->objective->id}/practice/next";

        // 120/min: un aula entera sale por la misma IP y el bucle gasta dos
        // peticiones por ejercicio, así que 60 dejaba al colegio en 30
        // ejercicios por minuto entre todos (auditoría).
        for ($i = 0; $i < 120; $i++) {
            $this->getJson($url)->assertOk();
        }

        $this->getJson($url)->assertStatus(429);
    }

    /**
     * REGRESIÓN DE SEGURIDAD (auditoría): con `trustProxies(at: '*')` Laravel
     * se creía la cadena ENTERA de X-Forwarded-For, incluida la parte que
     * escribe el cliente, así que `$request->ip()` devolvía lo que el atacante
     * quisiera y bastaba una cabecera distinta por petición para saltarse el
     * límite. Confiando solo en rangos privados, los saltos inventados quedan
     * a la izquierda y se descartan: manda la primera IP pública de verdad.
     */
    public function test_una_cabecera_forjada_no_cambia_la_ip_que_cuenta_para_el_limite(): void
    {
        // Una petición real primero: TrustProxies fija los proxies de confianza.
        $this->get('/catalogo')->assertOk();

        $peticion = Request::create('/api/v1/practice/mastery', 'GET', server: [
            'REMOTE_ADDR' => '203.0.113.9',   // lo que ve NUESTRO nginx
        ]);
        $peticion->headers->set('X-Forwarded-For', '1.2.3.4, 203.0.113.9');

        $this->assertSame('203.0.113.9', $peticion->ip(),
            'Un X-Forwarded-For forjado sigue decidiendo la IP: el límite es saltable.');
    }

    /**
     * REGRESIÓN (auditoría): el invitado veía SIEMPRE el mismo ítem. El
     * selector cuenta intentos por usuario y el invitado no tiene ninguno, así
     * que el mínimo era 0 para todos y ganaba siempre el de menor `seq`: más de
     * la mitad del banco quedaba inalcanzable sin sesión. Ahora rota por número
     * de intento, sobre el mismo orden estable que usa la rotación del alumno.
     */
    public function test_el_invitado_recorre_todos_los_items_de_la_destreza(): void
    {
        foreach ([2, 3] as $seq) {
            PracticeItem::create([
                'objective_id' => $this->objective->id, 'seq' => $seq,
                'statement' => ['es' => "Ejercicio {$seq}: {a} + {b}"],
                'params' => ['a' => ['const' => $seq], 'b' => ['const' => 1]],
                'solution_expr' => 'a + b', 'tolerance' => 0.01, 'tolerance_kind' => 'abs',
                // Firmado: este fixture prueba el MOTOR, y un ítem sin
                // revisar no llega al motor (ver DominioYFirmaTest).
                'reviewed_at' => now(),
            ]);
        }

        $vistos = [];
        foreach (range(1, 6) as $intento) {
            $vistos[] = $this->getJson(
                "/api/v1/objectives/{$this->objective->id}/practice/next?intento={$intento}"
            )->assertOk()->json('item_id');
        }

        $this->assertCount(3, array_unique($vistos), 'El invitado no rota de ítem: '.implode(', ', $vistos));
        // Y la rotación es cíclica y estable: el intento 4 repite el del 1.
        $this->assertSame($vistos[0], $vistos[3]);
    }

    /**
     * El `next` declara si lo que venga se guardará. Es lo ÚNICO que delata una
     * sesión caducada a media práctica: los endpoints ya no devuelven 401
     * —atienden al alumno como invitado— y la prop `auth` del cliente se
     * renderizó cuando la sesión aún vivía (auditoría).
     */
    public function test_el_siguiente_item_declara_si_se_va_a_guardar(): void
    {
        $this->getJson("/api/v1/objectives/{$this->objective->id}/practice/next")
            ->assertOk()
            ->assertJsonPath('se_guarda', false);

        $this->actingAs($this->ana)
            ->getJson("/api/v1/objectives/{$this->objective->id}/practice/next")
            ->assertOk()
            ->assertJsonPath('se_guarda', true);
    }

    // ======== CSRF: el invitado tiene sesión web, y la protección sigue puesta ========

    /**
     * El POST de práctica del invitado necesita un token, y para tenerlo hace
     * falta que la página se lo dé. Eso es lo que se comprueba aquí: al abrir
     * /practicar sin sesión, la respuesta trae la cookie XSRF-TOKEN que el
     * cliente lee en `tokenXsrf()` para la cabecera X-XSRF-TOKEN.
     *
     * OJO con el alcance: PreventRequestForgery se autoexcluye cuando
     * `runningUnitTests()`, así que NINGÚN test de esta suite puede evaluar el
     * rechazo de verdad —montarlo daría un oráculo vacuo que pasa siempre—.
     * Lo que sí se fija: que el invitado recibe token (esto) y que la práctica
     * no se coló en la lista de excepciones (el test de abajo).
     */
    public function test_el_invitado_recibe_la_cookie_de_token_para_poder_responder(): void
    {
        $respuesta = $this->get("/practicar/{$this->objective->id}")->assertOk();

        $cookie = collect($respuesta->headers->getCookies())
            ->first(fn ($c) => $c->getName() === 'XSRF-TOKEN');

        $this->assertNotNull($cookie, 'Sin cookie XSRF-TOKEN el invitado no puede responder.');
        $this->assertNotSame('', $cookie->getValue());
    }

    /**
     * Y la excepción de CSRF sigue cubriendo SOLO el launch LTI: abrir la
     * práctica al mundo no es excusa para dejar de exigir token en el POST.
     * Este es el oráculo de verdad, y es por reflexión justamente porque el
     * comportamiento no se puede evaluar en la suite.
     */
    public function test_la_practica_abierta_no_se_colo_en_la_excepcion_de_csrf(): void
    {
        $middleware = app(PreventRequestForgery::class);

        $this->assertSame(['lti/*'], array_values($middleware->getExcludedPaths()));
    }

    private function crearResourceLink(User $user): LtiResourceLink
    {
        $platform = LtiPlatform::create([
            'issuer' => 'https://moodle.example.edu',
            'client_id' => 'abc123',
            'deployment_ids' => ['dep-1'],
            'auth_login_url' => 'https://moodle.example.edu/mod/lti/auth.php',
            'auth_token_url' => 'https://moodle.example.edu/mod/lti/token.php',
            'jwks_url' => 'https://moodle.example.edu/mod/lti/certs.php',
            'is_active' => true,
        ]);

        return LtiResourceLink::create([
            'platform_id' => $platform->id,
            'resource_link_id' => 'rl-1',
            'user_id' => $user->id,
            'objective_id' => $this->objective->id,
            'ags' => ['lineitem' => 'https://moodle.example.edu/lineitem/1'],
            'last_launched_at' => now(),
        ]);
    }
}
