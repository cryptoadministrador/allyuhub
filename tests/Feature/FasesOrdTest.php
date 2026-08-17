<?php

namespace Tests\Feature;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\LtiContext;
use App\Models\LtiContextMembership;
use App\Models\LtiPlatform;
use App\Models\Track;
use App\Models\TrackPhase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * FRENTE 2 — ORD deja de estar vacío. El trayecto ordinario tenía fases pero
 * CERO destrezas atadas, así que el panel del docente decía «cubre 0
 * destrezas» de un currículo de 1010. Este comando ata cada subnivel con las
 * destrezas de SUS grados, y es idempotente.
 */
class FasesOrdTest extends TestCase
{
    use RefreshDatabase;

    private FrameworkVersion $version;

    /** @var array<string, CurNode> */
    private array $grados = [];

    protected function setUp(): void
    {
        parent::setUp();

        $fw = Framework::create([
            'code' => 'EC-MINEDEC', 'authority' => 'MINEDEC', 'kind' => 'national',
            'country' => 'EC', 'label' => ['es' => 'Currículo Nacional'],
        ]);
        $this->version = FrameworkVersion::create(['framework_id' => $fw->id, 'label' => '2016+2023']);

        // Un grado de cada subnivel, con una asignatura, un bloque y una destreza.
        foreach (['g1' => 1, 'g3' => 2, 'g6' => 3, 'g9' => 4, 'g12' => 5] as $gid => $subnivel) {
            $grado = CurNode::create([
                'version_id' => $this->version->id, 'node_type' => 'grado',
                'native_code' => $gid, 'title' => ['es' => strtoupper($gid)],
                'path' => "n.{$gid}", 'seq' => (int) substr($gid, 1),
            ]);
            $asignatura = CurNode::create([
                'version_id' => $this->version->id, 'parent_id' => $grado->id,
                'node_type' => 'asignatura', 'native_code' => 'CN',
                'title' => ['es' => 'Ciencias'], 'path' => "n.{$gid}.cn",
            ]);
            LearningObjective::create([
                'node_id' => $asignatura->id, 'version_id' => $this->version->id,
                'native_code' => "CN.{$subnivel}.1.1", 'statement' => ['es' => "Destreza de {$gid}"],
            ]);
            $this->grados[$gid] = $grado;
        }

        Track::create(['code' => 'ORD', 'label' => ['es' => 'Educación ordinaria'], 'modality' => 'presencial']);
    }

    public function test_crea_una_fase_por_subnivel_con_las_destrezas_de_sus_grados(): void
    {
        $this->artisan('curriculo:fases-ord')->assertSuccessful();

        $ord = Track::where('code', 'ORD')->firstOrFail();
        $fases = $ord->phases()->orderBy('seq')->get();

        $this->assertCount(5, $fases);
        $this->assertSame(
            ['Preparatoria', 'Elemental', 'Media', 'Superior', 'Bachillerato'],
            $fases->map(fn (TrackPhase $f) => $f->label['es'])->all(),
        );

        // Cada fase lleva la destreza de SU subnivel y ninguna de otro.
        $esperado = ['CN.1.1.1', 'CN.2.1.1', 'CN.3.1.1', 'CN.4.1.1', 'CN.5.1.1'];
        foreach ($fases as $i => $fase) {
            $codigos = $fase->objectives()->pluck('native_code')->all();
            $this->assertSame([$esperado[$i]], $codigos,
                "La fase «{$fase->label['es']}» no lleva exactamente las destrezas de su subnivel");
        }

        // Y con la fuente declarada.
        $this->assertSame(
            ['mapeo-interno'],
            DB::table('track_phase_objectives')->distinct()->pluck('source')->all(),
        );
    }

    public function test_es_idempotente(): void
    {
        $this->artisan('curriculo:fases-ord')->assertSuccessful();
        $fases = TrackPhase::count();
        $enlaces = DB::table('track_phase_objectives')->count();

        $this->artisan('curriculo:fases-ord')->assertSuccessful();

        $this->assertSame($fases, TrackPhase::count());
        $this->assertSame($enlaces, DB::table('track_phase_objectives')->count());
    }

    /** Funciona igual tras el importador real: las destrezas nuevas se atan. */
    public function test_recoge_las_destrezas_que_aparezcan_despues(): void
    {
        $this->artisan('curriculo:fases-ord')->assertSuccessful();

        // Llega el importador oficial y añade una destreza a 9.º (subnivel 4).
        $asignatura = CurNode::where('path', 'n.g9.cn')->firstOrFail();
        LearningObjective::create([
            'node_id' => $asignatura->id, 'version_id' => $this->version->id,
            'native_code' => 'CN.4.3.5', 'statement' => ['es' => 'Fuerzas'],
        ]);

        $this->artisan('curriculo:fases-ord')->assertSuccessful();

        $superior = TrackPhase::whereJsonContains('label->es', 'Superior')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['CN.4.1.1', 'CN.4.3.5'],
            $superior->objectives()->pluck('native_code')->all(),
        );
    }

    /** Las fases viejas por GRADO no pueden quedar sueltas al lado de las nuevas. */
    public function test_sustituye_las_fases_por_grado_que_dejo_el_seeder(): void
    {
        $ord = Track::where('code', 'ORD')->firstOrFail();
        foreach ([1, 3, 6, 9, 12] as $seq) {
            TrackPhase::create([
                'track_id' => $ord->id, 'seq' => $seq,
                'label' => ['es' => "Grado {$seq}"], 'grade_node_id' => null,
            ]);
        }

        $this->artisan('curriculo:fases-ord')->assertSuccessful();

        $this->assertSame(5, $ord->phases()->count());
        $this->assertSame(0, $ord->phases()->where('label->es', 'Grado 6')->count());
    }

    /** INTEGRACIÓN: el panel del docente deja de decir «cubre 0 destrezas». */
    public function test_el_panel_del_docente_muestra_el_total_correcto(): void
    {
        $this->artisan('curriculo:fases-ord')->assertSuccessful();

        $platform = LtiPlatform::create([
            'issuer' => 'https://moodle.test', 'client_id' => 'c1',
            'auth_login_url' => 'x', 'auth_token_url' => 'x', 'jwks_url' => 'x',
        ]);
        $contexto = LtiContext::create([
            'platform_id' => $platform->id, 'context_id' => 'curso-1',
            'title' => 'Ciencias', 'track_id' => Track::where('code', 'ORD')->value('id'),
        ]);
        $profe = User::factory()->create();
        LtiContextMembership::create([
            'lti_context_id' => $contexto->id, 'user_id' => $profe->id,
            'role' => 'instructor', 'last_launched_at' => now(),
        ]);

        $this->actingAs($profe)->get("/docente/{$contexto->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('track.code', 'ORD')
                ->where('objectives_summary.total', 5)   // las 5 destrezas del grafo
            );
    }
}
