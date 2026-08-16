<?php

namespace Tests\Feature;

use App\Models\LtiContext;
use App\Models\LtiContextMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Packback\Lti1p3\Claims\Claim;
use Packback\Lti1p3\LtiOidcLogin;
use Tests\Support\FakeLtiPlatform;
use Tests\TestCase;

/**
 * Frente 1 (misión vista-docente): el launch persiste los claims `context` y
 * `roles` que antes se tiraban — lti_contexts (curso de Moodle, con su mapeo
 * a track NULABLE) y lti_context_memberships (rol POR CONTEXTO, jamás en
 * users). El rol solo se infla con membership#Instructor/#ContentDeveloper.
 */
class LtiContextTest extends TestCase
{
    use RefreshDatabase;

    private const INSTRUCTOR = 'http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor';

    private const LEARNER = 'http://purl.imsglobal.org/vocab/lis/v2/membership#Learner';

    private const CONTEXT_101 = ['id' => 'curso-101', 'title' => 'Física 1.º BGU', 'label' => 'FIS1'];

    private FakeLtiPlatform $moodle;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->artisan('lti:keys')->assertSuccessful();
        $this->moodle = new FakeLtiPlatform;
        $this->moodle->fakeJwks();
    }

    private function launch(array $overrides = [])
    {
        $response = $this->get('/lti/login?'.http_build_query([
            'iss' => FakeLtiPlatform::ISSUER,
            'login_hint' => '7',
            'client_id' => FakeLtiPlatform::CLIENT_ID,
            'target_link_uri' => url('/lti/launch'),
        ]));
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $q);

        return $this->withCookie(LtiOidcLogin::COOKIE_PREFIX.$q['state'], $q['state'])
            ->post('/lti/launch', [
                'state' => $q['state'],
                'id_token' => $this->moodle->idToken(array_merge(['nonce' => $q['nonce']], $overrides)),
            ]);
    }

    public function test_el_launch_persiste_contexto_y_membership_de_learner(): void
    {
        $this->launch([Claim::CONTEXT => self::CONTEXT_101])->assertRedirect('/progreso');

        $this->assertDatabaseHas('lti_contexts', [
            'platform_id' => $this->moodle->platform->id,
            'context_id' => 'curso-101',
            'title' => 'Física 1.º BGU',
            'label' => 'FIS1',
            'track_id' => null,   // el mapeo curso→track nace sin asignar
        ]);

        $user = User::where('lti_sub', 'moodle-user-7')->firstOrFail();
        $context = LtiContext::firstOrFail();
        $this->assertDatabaseHas('lti_context_memberships', [
            'lti_context_id' => $context->id,
            'user_id' => $user->id,
            'role' => 'learner',
        ]);
        $this->assertNotNull(LtiContextMembership::first()->last_launched_at);
    }

    public function test_un_instructor_aterriza_en_su_panel_y_un_learner_donde_iba(): void
    {
        $this->launch([
            Claim::CONTEXT => self::CONTEXT_101,
            Claim::ROLES => [self::INSTRUCTOR],
            'sub' => 'moodle-teacher-1', 'name' => 'Docente Pérez', 'email' => null,
        ])->assertRedirect('/docente/'.LtiContext::firstOrFail()->id);

        $this->assertDatabaseHas('lti_context_memberships', ['role' => 'instructor']);
    }

    /** ORÁCULO 3a: roles vacíos o basura JAMÁS inflan a instructor. */
    public function test_roles_vacios_o_basura_son_learner(): void
    {
        $this->launch([
            Claim::CONTEXT => self::CONTEXT_101,
            Claim::ROLES => [],
        ])->assertRedirect('/progreso');
        $this->assertDatabaseHas('lti_context_memberships', ['role' => 'learner']);

        LtiContextMembership::query()->delete();

        // Roles reales que NO son de instructor de curso (la forma corta
        // legítima «Instructor» se cubre en su propio test).
        $this->launch([
            Claim::CONTEXT => self::CONTEXT_101,
            Claim::ROLES => ['basura-total', 'TeachingAssistant'],
        ])->assertRedirect('/progreso');
        $this->assertDatabaseHas('lti_context_memberships', ['role' => 'learner']);
    }

    /** ORÁCULO 3b: un administrador de SISTEMA no es instructor de un curso. */
    public function test_el_admin_de_sistema_no_es_instructor(): void
    {
        $this->launch([
            Claim::CONTEXT => self::CONTEXT_101,
            Claim::ROLES => ['http://purl.imsglobal.org/vocab/lis/v2/system/person#Administrator'],
        ])->assertRedirect('/progreso');

        $this->assertDatabaseHas('lti_context_memberships', ['role' => 'learner']);
    }

    /** ORÁCULO 3c: instructor en el curso A no da instructor en el curso B. */
    public function test_el_rol_es_por_contexto_no_por_usuario(): void
    {
        // Mismo sub: instructor del curso 101…
        $this->launch([
            Claim::CONTEXT => self::CONTEXT_101,
            Claim::ROLES => [self::INSTRUCTOR],
        ]);
        // …y learner del curso 202.
        $this->launch([
            Claim::CONTEXT => ['id' => 'curso-202', 'title' => 'Química', 'label' => 'QUI1'],
            Claim::ROLES => [self::LEARNER],
        ]);

        $user = User::where('lti_sub', 'moodle-user-7')->firstOrFail();
        $c101 = LtiContext::where('context_id', 'curso-101')->firstOrFail();
        $c202 = LtiContext::where('context_id', 'curso-202')->firstOrFail();

        $this->assertSame('instructor', LtiContextMembership::where('lti_context_id', $c101->id)
            ->where('user_id', $user->id)->value('role'));
        $this->assertSame('learner', LtiContextMembership::where('lti_context_id', $c202->id)
            ->where('user_id', $user->id)->value('role'));
        // Y nada de rol global: users no tiene columna role.
        $this->assertFalse(Schema::hasColumn('users', 'role'));
    }

    public function test_relanzar_actualiza_titulo_rol_y_last_launched_sin_duplicar(): void
    {
        $this->launch([Claim::CONTEXT => self::CONTEXT_101]);

        // El curso se renombró en Moodle y ahora quien entra es instructor.
        $this->launch([
            Claim::CONTEXT => ['id' => 'curso-101', 'title' => 'Física I (renombrado)', 'label' => 'FIS1'],
            Claim::ROLES => [self::INSTRUCTOR],
        ]);

        $this->assertSame(1, LtiContext::count());
        $this->assertSame(1, LtiContextMembership::count());
        $this->assertDatabaseHas('lti_contexts', ['title' => 'Física I (renombrado)']);
        $this->assertDatabaseHas('lti_context_memberships', ['role' => 'instructor']);
    }

    public function test_un_launch_sin_contexto_no_rompe_ni_persiste(): void
    {
        $this->launch()->assertRedirect('/progreso');   // el idToken base no lleva context

        $this->assertSame(0, LtiContext::count());
        $this->assertSame(0, LtiContextMembership::count());
    }

    /**
     * Auditoría (carrera de concurrencia): dos launches simultáneos del mismo
     * curso nuevo (dos alumnos entrando a la vez, o dos pestañas) no pueden dar
     * un 500. Se simula el rival insertando la fila en el hueco SELECT→INSERT
     * del updateOrCreate — el mismo blindaje que ya tiene provisionUser.
     */
    public function test_launch_concurrente_del_mismo_contexto_no_revienta(): void
    {
        $platformId = $this->moodle->platform->id;

        // El "otro request" gana el INSERT del contexto justo antes que el nuestro.
        LtiContext::creating(function (LtiContext $ctx) use ($platformId) {
            static $yaCorrio = false;
            if ($yaCorrio) {
                return;
            }
            $yaCorrio = true;
            DB::table('lti_contexts')->insert([
                'id' => (string) Str::uuid(),
                'platform_id' => $platformId,
                'context_id' => $ctx->context_id,
                'title' => 'Insertado por el rival',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        $this->launch([Claim::CONTEXT => self::CONTEXT_101])->assertRedirect('/progreso');

        LtiContext::flushEventListeners();

        // Un solo contexto y la membership quedó registrada pese a la carrera.
        $this->assertSame(1, LtiContext::count());
        $this->assertDatabaseHas('lti_context_memberships', ['role' => 'learner']);
    }

    /** Auditoría: interoperabilidad — la forma corta del rol también cuenta. */
    public function test_forma_corta_del_rol_instructor_cuenta(): void
    {
        $this->launch([
            Claim::CONTEXT => self::CONTEXT_101,
            Claim::ROLES => ['Instructor'],   // vocabulario LTI en forma corta
        ])->assertRedirect('/docente/'.LtiContext::firstOrFail()->id);

        $this->assertDatabaseHas('lti_context_memberships', ['role' => 'instructor']);
    }

    /** Auditoría: un URI que solo CONTIENE «membership#Instructor» no infla. */
    public function test_un_rol_que_imita_el_uri_no_infla(): void
    {
        $this->launch([
            Claim::CONTEXT => self::CONTEXT_101,
            Claim::ROLES => ['http://evil.example/x/membership#Instructor-falsificado'],
        ])->assertRedirect('/progreso');

        $this->assertDatabaseHas('lti_context_memberships', ['role' => 'learner']);
    }

    /**
     * ORÁCULO 2 (la lección del reviewed_by uuid-contra-bigint): las FKs nuevas
     * se comparan CONTRA SU DESTINO, nunca contra un tipo concreto — así el
     * test vale en SQLite y en el PostgreSQL del CI a la vez.
     */
    public function test_tipos_duales_de_las_fks_nuevas(): void
    {
        $this->assertSame(
            Schema::getColumnType('users', 'id'),
            Schema::getColumnType('lti_context_memberships', 'user_id'),
            'lti_context_memberships.user_id debe casar con users.id (¿foreignId?)',
        );
        $this->assertSame(
            Schema::getColumnType('lti_platforms', 'id'),
            Schema::getColumnType('lti_contexts', 'platform_id'),
        );
        $this->assertSame(
            Schema::getColumnType('tracks', 'id'),
            Schema::getColumnType('lti_contexts', 'track_id'),
        );
        $this->assertSame(
            Schema::getColumnType('lti_contexts', 'id'),
            Schema::getColumnType('lti_context_memberships', 'lti_context_id'),
        );
    }
}
