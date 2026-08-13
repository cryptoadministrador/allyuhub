<?php

namespace Tests\Feature;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\PracticeItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Packback\Lti1p3\Claims\Claim;
use Packback\Lti1p3\LtiOidcLogin;
use Tests\Support\FakeLtiPlatform;
use Tests\TestCase;

/**
 * OIDC login + Resource Link Launch (fase B LTI): la Platform de prueba firma
 * id_tokens con RSA real y sirve su JWKS por Http::fake — la validación de
 * firma/nonce/deployment es la de verdad, sin mocks de la validación.
 */
class LtiLaunchTest extends TestCase
{
    use RefreshDatabase;

    private FakeLtiPlatform $moodle;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->artisan('lti:keys')->assertSuccessful();
        $this->moodle = new FakeLtiPlatform;
        $this->moodle->fakeJwks();
    }

    /** Hace el paso OIDC de login y devuelve [state, nonce] del redirect. */
    private function handshake(array $params = []): array
    {
        $response = $this->get('/lti/login?'.http_build_query(array_merge([
            'iss' => FakeLtiPlatform::ISSUER,
            'login_hint' => '7',
            'client_id' => FakeLtiPlatform::CLIENT_ID,
            'target_link_uri' => url('/lti/launch'),
        ], $params)));

        $response->assertRedirect();
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        return [$query['state'], $query['nonce']];
    }

    private function launch(string $state, string $idToken)
    {
        // withCookie viaja CIFRADA, como en el navegador real: las rutas
        // /lti/* van por el grupo web completo (EncryptCookies incluido).
        return $this->withCookie(LtiOidcLogin::COOKIE_PREFIX.$state, $state)
            ->post('/lti/launch', ['state' => $state, 'id_token' => $idToken]);
    }

    public function test_login_redirige_al_auth_de_la_platform_con_state_y_nonce(): void
    {
        $response = $this->get('/lti/login?'.http_build_query([
            'iss' => FakeLtiPlatform::ISSUER,
            'login_hint' => '7',
            'client_id' => FakeLtiPlatform::CLIENT_ID,
            'target_link_uri' => url('/lti/launch'),
        ]));

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith(FakeLtiPlatform::ISSUER.'/mod/lti/auth.php?', $location);

        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('openid', $query['scope']);
        $this->assertSame('id_token', $query['response_type']);
        $this->assertSame('form_post', $query['response_mode']);
        $this->assertSame(FakeLtiPlatform::CLIENT_ID, $query['client_id']);
        $this->assertSame(url('/lti/launch'), $query['redirect_uri']);
        $this->assertNotEmpty($query['state']);
        $this->assertNotEmpty($query['nonce']);

        // La cookie de state ata el apretón a ESTE navegador (cifrada en el grupo web).
        $cookie = $response->getCookie(LtiOidcLogin::COOKIE_PREFIX.$query['state']);
        $this->assertNotNull($cookie);
        $this->assertSame($query['state'], $cookie->getValue());
    }

    public function test_login_con_issuer_desconocido_se_rechaza(): void
    {
        $this->get('/lti/login?'.http_build_query([
            'iss' => 'https://moodle-malvado.test',
            'login_hint' => '7',
        ]))->assertStatus(400);
    }

    public function test_launch_valido_provisiona_por_iss_sub_e_inicia_sesion(): void
    {
        [$state, $nonce] = $this->handshake();

        // Sin custom: el launch redirige a la app (grupo web, con CSRF).
        $this->launch($state, $this->moodle->idToken(['nonce' => $nonce]))
            ->assertRedirect('/progreso');

        $this->assertDatabaseHas('users', [
            'lti_iss' => FakeLtiPlatform::ISSUER,
            'lti_sub' => 'moodle-user-7',
            'email' => 'ana@colegio.test',   // se guarda porque estaba libre…
        ]);
        $this->assertAuthenticated();

        // Segundo launch del mismo alumno: mismo usuario, no un duplicado.
        [$state2, $nonce2] = $this->handshake();
        $this->launch($state2, $this->moodle->idToken(['nonce' => $nonce2]))->assertRedirect();
        $this->assertSame(1, User::count());
    }

    public function test_mismo_sub_en_otro_issuer_es_otro_usuario(): void
    {
        [$state, $nonce] = $this->handshake();
        $this->launch($state, $this->moodle->idToken(['nonce' => $nonce]))->assertRedirect();

        // Otra Platform con el MISMO sub (los sub de Moodle no son globales).
        $otro = new FakeLtiPlatform([
            'issuer' => 'https://otro-moodle.test',
            'jwks_url' => 'https://otro-moodle.test/mod/lti/certs.php',
            'auth_login_url' => 'https://otro-moodle.test/mod/lti/auth.php',
            'auth_token_url' => 'https://otro-moodle.test/mod/lti/token.php',
        ]);
        $otro->fakeJwks();

        [$state2, $nonce2] = $this->handshake(['iss' => 'https://otro-moodle.test']);
        $this->launch($state2, $otro->idToken([
            'nonce' => $nonce2,
            'iss' => 'https://otro-moodle.test',
            'email' => null,   // sin email: el placeholder debe ser único
        ]))->assertRedirect();

        $this->assertSame(2, User::count());
    }

    public function test_el_email_se_guarda_pero_jamas_identifica(): void
    {
        // Ya existe una cuenta local con el email del claim.
        $local = User::factory()->create(['email' => 'ana@colegio.test']);

        [$state, $nonce] = $this->handshake();
        $this->launch($state, $this->moodle->idToken(['nonce' => $nonce]))->assertRedirect();

        // No se fusionó con la cuenta local: usuario NUEVO con email placeholder.
        $this->assertSame(2, User::count());
        $ltiUser = User::where('lti_sub', 'moodle-user-7')->first();
        $this->assertNotNull($ltiUser);
        $this->assertNotSame($local->id, $ltiUser->id);
        $this->assertNotSame('ana@colegio.test', $ltiUser->email);
        $this->assertNull($local->fresh()->lti_iss);
    }

    // ---------- Casos de rechazo (cada uno con su test, misión fase B) ----------

    public function test_rechaza_firma_de_otra_clave_aunque_el_kid_coincida(): void
    {
        [$state, $nonce] = $this->handshake();

        $forged = $this->moodle->idToken(
            ['nonce' => $nonce],
            signWith: FakeLtiPlatform::attackerKeyPem(),   // clave del atacante
            kid: FakeLtiPlatform::KID,                     // …disfrazada con el kid bueno
        );

        $this->launch($state, $forged)->assertForbidden();
        $this->assertSame(0, User::count());
    }

    public function test_rechaza_nonce_reusado(): void
    {
        [$state, $nonce] = $this->handshake();
        $token = $this->moodle->idToken(['nonce' => $nonce]);

        $this->launch($state, $token)->assertRedirect();

        // Replay exacto del mismo id_token: el nonce ya fue consumido.
        $this->launch($state, $token)->assertForbidden();
    }

    public function test_rechaza_issuer_desconocido_en_el_id_token(): void
    {
        [$state, $nonce] = $this->handshake();

        $this->launch($state, $this->moodle->idToken([
            'nonce' => $nonce,
            'iss' => 'https://moodle-malvado.test',
        ]))->assertForbidden();
    }

    public function test_rechaza_token_caducado(): void
    {
        [$state, $nonce] = $this->handshake();

        $this->launch($state, $this->moodle->idToken([
            'nonce' => $nonce,
            'iat' => time() - 600,
            'exp' => time() - 300,
        ]))->assertForbidden();
    }

    public function test_rechaza_deployment_no_registrado(): void
    {
        [$state, $nonce] = $this->handshake();

        $this->launch($state, $this->moodle->idToken([
            'nonce' => $nonce,
            Claim::DEPLOYMENT_ID => 'dep-999',
        ]))->assertForbidden();
    }

    public function test_rechaza_state_sin_cookie_del_navegador(): void
    {
        [$state, $nonce] = $this->handshake();

        // POST sin la cookie: un launch forjado desde otro navegador (CSRF).
        $this->post('/lti/launch', [
            'state' => $state,
            'id_token' => $this->moodle->idToken(['nonce' => $nonce]),
        ])->assertForbidden();
    }

    // ---------- La vista del launch ----------

    public function test_launch_de_destreza_enlaza_la_practica_con_el_usuario_de_sesion(): void
    {
        $fw = Framework::create([
            'code' => 'EC-MINEDEC', 'authority' => 'MINEDEC', 'kind' => 'national',
            'country' => 'EC', 'label' => ['es' => 'Currículo Nacional'],
        ]);
        $version = FrameworkVersion::create(['framework_id' => $fw->id, 'label' => '2016+2023']);
        $node = CurNode::create([
            'version_id' => $version->id, 'node_type' => 'grado',
            'native_code' => 'g11', 'title' => ['es' => '1.º BGU'], 'path' => 'bgu.g11',
        ]);
        $objective = LearningObjective::create([
            'node_id' => $node->id, 'version_id' => $version->id,
            'native_code' => 'CN.F.5.1.9', 'statement' => ['es' => 'Plano inclinado…'],
        ]);
        PracticeItem::create([
            'objective_id' => $objective->id,
            'statement' => ['es' => 'm={m}'], 'params' => ['m' => ['min' => 1, 'max' => 5, 'step' => 1]],
            'solution_expr' => 'm', 'tolerance' => 0.02, 'tolerance_kind' => 'rel',
        ]);

        [$state, $nonce] = $this->handshake();
        $this->launch($state, $this->moodle->idToken([
            'nonce' => $nonce,
            Claim::CUSTOM => ['allyu_type' => 'objective', 'allyu_id' => $objective->id],
        ]))->assertRedirect("/practicar/{$objective->id}");

        // La MISMA sesión del launch abre la página de práctica (Inertia):
        // la identidad viaja en la sesión, jamás en la URL.
        $this->get("/practicar/{$objective->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Practicar')
                ->where('objective.native_code', 'CN.F.5.1.9'));
    }

    public function test_launch_sin_custom_redirige_al_progreso(): void
    {
        [$state, $nonce] = $this->handshake();

        $this->launch($state, $this->moodle->idToken(['nonce' => $nonce]))
            ->assertRedirect('/progreso');
    }
}
