<?php

namespace Tests\Feature;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\LtiContext;
use App\Models\PracticeItem;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Packback\Lti1p3\Claims\Claim;
use Packback\Lti1p3\LtiOidcLogin;
use Tests\Support\FakeLtiPlatform;
use Tests\TestCase;

/**
 * FRENTE 1 — la MATRIZ DE ATERRIZAJE del launch, completa y en un solo sitio.
 * Con /inicio, el alumno sin deep link ya no cae en /progreso (una tabla de
 * números) sino en su casa. El deep link sigue mandando sobre todo lo demás,
 * también para el docente (regresión de la auditoría del PR #17).
 */
class LtiLandingTest extends TestCase
{
    use RefreshDatabase;

    private const INSTRUCTOR = 'http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor';

    private const CONTEXTO = ['id' => 'curso-101', 'title' => 'Física 1.º BGU', 'label' => 'FIS1'];

    private FakeLtiPlatform $moodle;

    private LearningObjective $destreza;

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
        $this->destreza = LearningObjective::create([
            'node_id' => $node->id, 'version_id' => $version->id,
            'native_code' => 'CN.F.5.1.9', 'statement' => ['es' => 'Plano inclinado'],
        ]);
        PracticeItem::create([
            'objective_id' => $this->destreza->id,
            'statement' => ['es' => 'm={m}'], 'params' => ['m' => ['min' => 1, 'max' => 5, 'step' => 1]],
            'solution_expr' => 'm', 'tolerance' => 0.02, 'tolerance_kind' => 'rel',
            // Firmado: este fixture prueba el MOTOR, y un ítem sin
            // revisar no llega al motor (ver DominioYFirmaTest).
            'reviewed_at' => now(),
        ]);
        $this->sim = Resource::create([
            'slug' => 'plano-inclinado', 'kind' => 'lab',
            'title' => ['es' => 'Laboratorio'], 'status' => 'published',
        ]);
    }

    private function lanzar(array $overrides = [])
    {
        $r = $this->get('/lti/login?'.http_build_query([
            'iss' => FakeLtiPlatform::ISSUER,
            'login_hint' => '7',
            'client_id' => FakeLtiPlatform::CLIENT_ID,
            'target_link_uri' => url('/lti/launch'),
        ]));
        parse_str(parse_url($r->headers->get('Location'), PHP_URL_QUERY), $q);

        return $this->withCookie(LtiOidcLogin::COOKIE_PREFIX.$q['state'], $q['state'])
            ->post('/lti/launch', [
                'state' => $q['state'],
                'id_token' => $this->moodle->idToken(array_merge(['nonce' => $q['nonce']], $overrides)),
            ]);
    }

    public function test_learner_sin_deep_link_aterriza_en_inicio(): void
    {
        $this->lanzar([Claim::CONTEXT => self::CONTEXTO])->assertRedirect('/inicio');
    }

    public function test_learner_sin_contexto_tambien_aterriza_en_inicio(): void
    {
        $this->lanzar()->assertRedirect('/inicio');
    }

    public function test_learner_con_deep_link_de_destreza_aterriza_en_la_destreza(): void
    {
        $this->lanzar([
            Claim::CONTEXT => self::CONTEXTO,
            Claim::CUSTOM => ['allyu_type' => 'objective', 'allyu_id' => $this->destreza->id],
        ])->assertRedirect('/practicar/'.$this->destreza->id);
    }

    public function test_learner_con_deep_link_de_recurso_aterriza_en_el_recurso(): void
    {
        $this->lanzar([
            Claim::CONTEXT => self::CONTEXTO,
            Claim::CUSTOM => ['allyu_type' => 'resource', 'allyu_id' => $this->sim->id],
        ])->assertRedirect('/recurso/'.$this->sim->id);
    }

    public function test_learner_con_deep_link_roto_cae_en_inicio_no_en_un_404(): void
    {
        // allyu_id que no es un uuid (lo teclea el admin de Moodle).
        $this->lanzar([
            Claim::CONTEXT => self::CONTEXTO,
            Claim::CUSTOM => ['allyu_type' => 'objective', 'allyu_id' => 'no-es-uuid'],
        ])->assertRedirect('/inicio');
    }

    public function test_instructor_sin_deep_link_aterriza_en_su_panel(): void
    {
        $this->lanzar([
            Claim::CONTEXT => self::CONTEXTO,
            Claim::ROLES => [self::INSTRUCTOR],
            'sub' => 'moodle-teacher-1', 'name' => 'Docente Pérez', 'email' => null,
        ])->assertRedirect('/docente/'.LtiContext::firstOrFail()->id);
    }

    /** REGRESIÓN (auditoría PR #17): el panel no secuestra el deep link del docente. */
    public function test_instructor_con_deep_link_aterriza_en_lo_que_el_mismo_asigno(): void
    {
        $this->lanzar([
            Claim::CONTEXT => self::CONTEXTO,
            Claim::ROLES => [self::INSTRUCTOR],
            'sub' => 'moodle-teacher-1', 'name' => 'Docente Pérez', 'email' => null,
            Claim::CUSTOM => ['allyu_type' => 'objective', 'allyu_id' => $this->destreza->id],
        ])->assertRedirect('/practicar/'.$this->destreza->id);
    }

    /** El instructor SIN curso (launch sin claim context) no puede ir a un panel inexistente. */
    public function test_instructor_sin_contexto_aterriza_en_inicio(): void
    {
        $this->lanzar([
            Claim::ROLES => [self::INSTRUCTOR],
            'sub' => 'moodle-teacher-1', 'name' => 'Docente Pérez', 'email' => null,
        ])->assertRedirect('/inicio');
    }
}
