<?php

namespace Tests\Feature;

use App\Jobs\PushLtiScore;
use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\LtiResourceLink;
use App\Models\ObjectiveMastery;
use App\Models\PracticeItem;
use App\Models\User;
use App\Services\Lti\LtiDatabase;
use App\Services\Lti\LtiHttpConnector;
use App\Services\Lti\ToolKeys;
use App\Services\Practice\PracticeEngine;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Packback\Lti1p3\Claims\Claim;
use Packback\Lti1p3\LtiConstants;
use Packback\Lti1p3\LtiOidcLogin;
use Tests\Support\FakeLtiPlatform;
use Tests\TestCase;

/**
 * AGS (fase D LTI): tras cada intento de práctica de un usuario LTI, el job
 * PushLtiScore publica el MASTERY de la destreza (×100) en el lineitem del
 * launch, vía client-credentials. Todo el tráfico va por Http::fake: se
 * verifica el grant firmado y el payload real del score.
 *
 * CRITERIO DEL SCORE (documentado): se envía mastery×100 (dominio EMA de la
 * destreza), no el correcto/incorrecto del intento suelto — el libro de
 * calificaciones debe reflejar dominio acumulado, y cada intento lo
 * re-publica actualizado.
 */
class LtiAgsTest extends TestCase
{
    use RefreshDatabase;

    private const LINEITEM_URL = 'https://moodle.colegio.test/mod/lti/lineitem/42';

    private FakeLtiPlatform $moodle;

    private LearningObjective $objective;

    private PracticeItem $item;

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
        $this->item = PracticeItem::create([
            'objective_id' => $this->objective->id,
            'statement' => ['es' => 'm={m} kg, θ={theta}°, g={g}'],
            'params' => [
                'm' => ['min' => 1, 'max' => 20, 'step' => 0.5],
                'theta' => ['min' => 10, 'max' => 45, 'step' => 1],
                'g' => ['const' => 9.8],
            ],
            'solution_expr' => 'm * g * sin(deg2rad(theta))',
            'tolerance' => 0.02, 'tolerance_kind' => 'rel',
        ]);
    }

    /** Launch de resource link (opcionalmente con claim AGS) y devuelve el usuario LTI. */
    private function launchWithAgs(bool $withAgs = true): User
    {
        $response = $this->get('/lti/login?'.http_build_query([
            'iss' => FakeLtiPlatform::ISSUER,
            'login_hint' => '7',
            'client_id' => FakeLtiPlatform::CLIENT_ID,
            'target_link_uri' => url('/lti/launch'),
        ]));
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $q);

        $overrides = [
            'nonce' => $q['nonce'],
            Claim::CUSTOM => ['allyu_type' => 'objective', 'allyu_id' => $this->objective->id],
        ];
        if ($withAgs) {
            $overrides[Claim::AGS_ENDPOINT] = [
                'scope' => [LtiConstants::AGS_SCOPE_SCORE, LtiConstants::AGS_SCOPE_LINEITEM],
                'lineitem' => self::LINEITEM_URL,
                'lineitems' => 'https://moodle.colegio.test/mod/lti/lineitems',
            ];
        }

        $this->withUnencryptedCookie(LtiOidcLogin::COOKIE_PREFIX.$q['state'], $q['state'])
            ->post('/lti/launch', ['state' => $q['state'], 'id_token' => $this->moodle->idToken($overrides)])
            ->assertOk();

        return User::where('lti_sub', 'moodle-user-7')->firstOrFail();
    }

    /** Contesta el intento en curso, correcto o no, por la API de práctica. */
    private function attempt(User $user, bool $correct = true): void
    {
        $engine = new PracticeEngine;
        $attemptNo = $this->item->attempts()->where('user_id', $user->id)->count() + 1;
        $params = $engine->sampleParams($this->item->params, $engine->seedFor($this->item->id, $user->id, $attemptNo));
        $expected = $params['m'] * $params['g'] * sin(deg2rad($params['theta']));

        $this->postJson("/api/v1/practice/items/{$this->item->id}/attempts", [
            'user_id' => $user->id,
            'answer' => $correct ? $expected : $expected + 50,
        ])->assertCreated();
    }

    public function test_el_launch_con_ags_persiste_el_resource_link(): void
    {
        $user = $this->launchWithAgs();

        $this->assertDatabaseHas('lti_resource_links', [
            'platform_id' => $this->moodle->platform->id,
            'resource_link_id' => 'rl-1',
            'user_id' => $user->id,
            'objective_id' => $this->objective->id,
        ]);
        $link = LtiResourceLink::first();
        $this->assertSame(self::LINEITEM_URL, $link->ags['lineitem']);
        $this->assertContains(LtiConstants::AGS_SCOPE_SCORE, $link->ags['scope']);
    }

    public function test_tras_un_intento_se_publica_el_mastery_como_score(): void
    {
        Http::fake([
            FakeLtiPlatform::ISSUER.'/mod/lti/token.php' => Http::response([
                'access_token' => 'tok-123', 'expires_in' => 3600,
            ]),
            self::LINEITEM_URL.'/scores' => Http::response([], 200),
        ]);

        $user = $this->launchWithAgs();
        $this->attempt($user, correct: true);   // mastery: 0 → 0.35

        // 1) El grant client-credentials va FIRMADO por la Tool.
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/mod/lti/token.php')) {
                return false;
            }
            $data = $request->data();
            $this->assertSame('client_credentials', $data['grant_type']);
            $this->assertSame('urn:ietf:params:oauth:client-assertion-type:jwt-bearer', $data['client_assertion_type']);
            $this->assertStringContainsString(LtiConstants::AGS_SCOPE_SCORE, $data['scope']);

            // El client_assertion se verifica contra el JWKS PÚBLICO de la Tool.
            $keys = JWK::parseKeySet(app(ToolKeys::class)->publicJwks());
            $assertion = json_decode(json_encode(JWT::decode($data['client_assertion'], $keys)), true);
            $this->assertSame(FakeLtiPlatform::CLIENT_ID, $assertion['iss']);
            $this->assertSame(FakeLtiPlatform::CLIENT_ID, $assertion['sub']);
            $this->assertContains(FakeLtiPlatform::ISSUER.'/mod/lti/token.php', $assertion['aud']);

            return true;
        });

        // 2) El score aterriza en el lineitem del launch con el mastery ×100.
        Http::assertSent(function ($request) {
            if ($request->url() !== self::LINEITEM_URL.'/scores') {
                return false;
            }
            $this->assertSame('POST', $request->method());
            $this->assertSame('Bearer tok-123', $request->header('Authorization')[0]);

            $score = json_decode($request->body(), true);
            $this->assertEqualsWithDelta(35.0, $score['scoreGiven'], 0.01);   // 0.35 × 100
            $this->assertSame(100, $score['scoreMaximum']);
            $this->assertSame('moodle-user-7', $score['userId']);             // el sub LTI, no el id local
            $this->assertSame('Completed', $score['activityProgress']);
            $this->assertSame('FullyGraded', $score['gradingProgress']);

            return true;
        });
    }

    public function test_cada_intento_reenvia_el_mastery_actualizado(): void
    {
        Http::fake([
            FakeLtiPlatform::ISSUER.'/mod/lti/token.php' => Http::response([
                'access_token' => 'tok-123', 'expires_in' => 3600,
            ]),
            self::LINEITEM_URL.'/scores' => Http::response([], 200),
        ]);

        $user = $this->launchWithAgs();
        $this->attempt($user, correct: true);    // 0.35
        $this->attempt($user, correct: false);   // 0.35 × 0.7 = 0.245

        $scores = [];
        Http::recorded(function ($request) use (&$scores) {
            if ($request->url() === self::LINEITEM_URL.'/scores') {
                $scores[] = json_decode($request->body(), true)['scoreGiven'];
            }

            return true;
        });

        $this->assertCount(2, $scores);
        $this->assertEqualsWithDelta(35.0, $scores[0], 0.01);
        $this->assertEqualsWithDelta(24.5, $scores[1], 0.01);
    }

    public function test_sin_ags_el_intento_no_dispara_nada(): void
    {
        Http::fake();   // cualquier request quedaría registrada

        $user = $this->launchWithAgs(withAgs: false);
        $this->attempt($user);

        $this->assertSame(0, LtiResourceLink::count());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'token.php'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/scores'));
    }

    public function test_usuario_no_lti_jamas_empuja_scores(): void
    {
        Http::fake();
        $local = User::factory()->create();

        $this->attempt($local);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'token.php'));
    }

    /**
     * REGRESIÓN DE SEGURIDAD (auditoría LTI): mientras la API de práctica acepte
     * user_id en el payload sin auth, un anónimo NO puede corromper la nota de un
     * alumno LTI en Moodle. La víctima ya lanzó con AGS (tiene resource link); el
     * atacante, sin sesión, responde intentos con el user_id de la víctima: se
     * registra el intento pero NO se publica score. El push solo sale de la sesión
     * LTI del propio dueño.
     */
    public function test_un_anonimo_no_puede_inyectar_notas_con_el_user_id_de_otro(): void
    {
        Http::fake();

        $victima = $this->launchWithAgs();          // deja resource link con AGS + sesión abierta
        $this->flushSession();                       // el atacante NO tiene la sesión de la víctima
        auth()->logout();

        $this->assertSame(1, LtiResourceLink::where('user_id', $victima->id)->count());

        // El atacante conoce (o enumera) el user_id de la víctima y machaca intentos.
        $engine = new PracticeEngine;
        $attemptNo = $this->item->attempts()->where('user_id', $victima->id)->count() + 1;
        $params = $engine->sampleParams($this->item->params, $engine->seedFor($this->item->id, $victima->id, $attemptNo));

        $this->postJson("/api/v1/practice/items/{$this->item->id}/attempts", [
            'user_id' => $victima->id,
            'answer' => $params['m'] * $params['g'] * sin(deg2rad($params['theta'])),
        ])->assertCreated();

        // El intento se guardó (contrato v1 intacto) pero NINGÚN score salió a Moodle.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'token.php'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/scores'));
    }

    public function test_si_la_platform_rechaza_el_score_el_job_reintenta(): void
    {
        Http::fake([
            FakeLtiPlatform::ISSUER.'/mod/lti/token.php' => Http::response([
                'access_token' => 'tok-123', 'expires_in' => 3600,
            ]),
            self::LINEITEM_URL.'/scores' => Http::response('upstream error', 500),
        ]);

        $user = $this->launchWithAgs();
        ObjectiveMastery::create([
            'user_id' => $user->id, 'objective_id' => $this->objective->id,
            'mastery' => 0.5, 'streak' => 1, 'attempts_count' => 1,
        ]);

        $job = new PushLtiScore(LtiResourceLink::first()->id);

        // Reintentos con backoff declarados en el job…
        $this->assertGreaterThanOrEqual(3, $job->tries);
        $this->assertNotEmpty($job->backoff());

        // …y un 5xx de la Platform lanza para que la cola reintente.
        $this->expectException(\RuntimeException::class);
        $job->handle(app(LtiDatabase::class), app(LtiHttpConnector::class));
    }
}
