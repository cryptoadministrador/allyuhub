<?php

namespace Tests\Feature;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\PracticeItem;
use App\Models\Resource;
use App\Services\Lti\ToolKeys;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Packback\Lti1p3\Claims\Claim;
use Packback\Lti1p3\LtiOidcLogin;
use Tests\Support\FakeLtiPlatform;
use Tests\TestCase;

/**
 * Deep Linking (fase C LTI): el docente elige un simulador publicado o una
 * destreza con ítems, y la Tool responde con un DeepLinkingResponse JWT
 * firmado con SU clave — el test lo decodifica contra el JWKS público de la
 * Tool, exactamente como haría Moodle.
 */
class LtiDeepLinkingTest extends TestCase
{
    use RefreshDatabase;

    private FakeLtiPlatform $moodle;

    private LearningObjective $objective;

    private Resource $sim;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->artisan('lti:keys')->assertSuccessful();
        $this->moodle = new FakeLtiPlatform;
        $this->moodle->fakeJwks();

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
        // Destreza SIN ítems: no debe aparecer en la selección.
        LearningObjective::create([
            'node_id' => $node->id, 'version_id' => $version->id,
            'native_code' => 'CN.F.5.1.4', 'statement' => ['es' => 'Sin ítems aún'],
        ]);

        $this->sim = Resource::create([
            'slug' => 'plano-inclinado', 'kind' => 'lab',
            'title' => ['es' => 'Laboratorio: plano inclinado'], 'status' => 'published',
        ]);
        Resource::create([
            'slug' => 'lente-borrador', 'kind' => 'lab',
            'title' => ['es' => 'Borrador de lente'], 'status' => 'draft',
        ]);
    }

    private function handshake(): array
    {
        $response = $this->get('/lti/login?'.http_build_query([
            'iss' => FakeLtiPlatform::ISSUER,
            'login_hint' => '1',
            'client_id' => FakeLtiPlatform::CLIENT_ID,
            'target_link_uri' => url('/lti/launch'),
        ]));
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        return [$query['state'], $query['nonce']];
    }

    private function deepLinkLaunch()
    {
        [$state, $nonce] = $this->handshake();

        return $this->withCookie(LtiOidcLogin::COOKIE_PREFIX.$state, $state)
            ->post('/lti/launch', [
                'state' => $state,
                'id_token' => $this->moodle->deepLinkToken(['nonce' => $nonce]),
            ]);
    }

    public function test_launch_deep_linking_lista_sims_publicados_y_destrezas_con_items(): void
    {
        $this->deepLinkLaunch()
            ->assertOk()
            ->assertSee('Laboratorio: plano inclinado')
            ->assertSee('CN.F.5.1.9')
            ->assertDontSee('lente-borrador')     // borrador: fuera
            ->assertDontSee('CN.F.5.1.4');        // sin ítems: fuera
    }

    public function test_la_respuesta_es_un_jwt_firmado_por_la_tool_con_el_content_item(): void
    {
        $this->deepLinkLaunch()->assertOk();

        $response = $this->post('/lti/deep-link', [
            'type' => 'objective',
            'id' => $this->objective->id,
        ])->assertOk();

        // La vista auto-envía el formulario al return_url de la Platform.
        $response->assertSee(FakeLtiPlatform::ISSUER.'/mod/lti/contentitem_return.php', escape: false);
        $this->assertMatchesRegularExpression('/name="JWT" value="[^"]+"/', $response->getContent());

        preg_match('/name="JWT" value="([^"]+)"/', $response->getContent(), $m);

        // Se decodifica con el JWKS PÚBLICO de la Tool: firma auténtica.
        $keys = JWK::parseKeySet(app(ToolKeys::class)->publicJwks());
        $payload = json_decode(json_encode(JWT::decode($m[1], $keys)), true);

        $this->assertSame(FakeLtiPlatform::CLIENT_ID, $payload['iss']);
        $this->assertSame([FakeLtiPlatform::ISSUER], $payload['aud']);
        $this->assertSame('LtiDeepLinkingResponse', $payload[Claim::MESSAGE_TYPE]);
        $this->assertSame(FakeLtiPlatform::DEPLOYMENT_ID, $payload[Claim::DEPLOYMENT_ID]);
        // El data opaco de la Platform se devuelve tal cual (obligatorio por spec).
        $this->assertSame('dl-opaque-data-1', $payload[Claim::DL_DATA]);

        $item = $payload[Claim::DL_CONTENT_ITEMS][0];
        $this->assertSame('ltiResourceLink', $item['type']);
        $this->assertSame(url('/lti/launch'), $item['url']);
        $this->assertSame('objective', $item['custom']['allyu_type']);
        $this->assertSame($this->objective->id, $item['custom']['allyu_id']);
        $this->assertStringContainsString('CN.F.5.1.9', $item['title']);
        // accept_lineitem=true → la destreza trae lineitem para AGS.
        $this->assertSame(100, $item['lineItem']['scoreMaximum']);
    }

    public function test_elegir_un_simulador_publica_su_content_item(): void
    {
        $this->deepLinkLaunch()->assertOk();

        $response = $this->post('/lti/deep-link', [
            'type' => 'resource',
            'id' => $this->sim->id,
        ])->assertOk();

        preg_match('/name="JWT" value="([^"]+)"/', $response->getContent(), $m);
        $keys = JWK::parseKeySet(app(ToolKeys::class)->publicJwks());
        $payload = json_decode(json_encode(JWT::decode($m[1], $keys)), true);

        $item = $payload[Claim::DL_CONTENT_ITEMS][0];
        $this->assertSame('resource', $item['custom']['allyu_type']);
        $this->assertSame($this->sim->id, $item['custom']['allyu_id']);
        $this->assertStringContainsString('Laboratorio: plano inclinado', $item['title']);
    }

    public function test_responder_sin_sesion_lti_se_rechaza(): void
    {
        $this->post('/lti/deep-link', [
            'type' => 'objective',
            'id' => $this->objective->id,
        ])->assertForbidden();
    }

    public function test_responder_desde_un_launch_normal_se_rechaza(): void
    {
        // Launch de resource link (no de deep linking): redirige a la app.
        [$state, $nonce] = $this->handshake();
        $this->withCookie(LtiOidcLogin::COOKIE_PREFIX.$state, $state)
            ->post('/lti/launch', [
                'state' => $state,
                'id_token' => $this->moodle->idToken(['nonce' => $nonce]),
            ])->assertRedirect();

        $this->post('/lti/deep-link', [
            'type' => 'objective',
            'id' => $this->objective->id,
        ])->assertForbidden();
    }
}
