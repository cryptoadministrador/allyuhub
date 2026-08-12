<?php

namespace Tests\Feature;

use App\Models\Alignment;
use App\Models\Concept;
use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use Database\Seeders\CrosswalkSeeder;
use Database\Seeders\InternationalFrameworksSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Marcos Cambridge/IB + primeras aristas del crosswalk (roadmap CLAUDE.md §3).
 *
 * No se corre el CurriculumSeeder completo (1010 destrezas): se siembran a mano
 * las 8 destrezas MINEDEC verificadas que el crosswalk necesita como anclas.
 */
class InternationalFrameworksTest extends TestCase
{
    use RefreshDatabase;

    /** Las destrezas MINEDEC verificadas de 8.º EGB–3.º BGU sobre las que ancla el crosswalk. */
    private const ANCHORS = [
        'CN.4.3.5', 'CN.4.3.7', 'CN.4.3.10',
        'CN.F.5.1.4', 'CN.F.5.1.9', 'CN.F.5.1.12',
        'CN.F.5.3.7', 'CN.F.5.3.8',
    ];

    private function seedMineducAnchors(): void
    {
        $fw = Framework::create([
            'code' => 'EC-MINEDEC', 'authority' => 'MINEDEC', 'kind' => 'national',
            'country' => 'EC', 'label' => ['es' => 'Currículo Nacional del Ecuador'],
        ]);
        $ver = FrameworkVersion::create(['framework_id' => $fw->id, 'label' => '2016+2023']);
        $node = CurNode::create([
            'version_id' => $ver->id, 'node_type' => 'bloque',
            'title' => ['es' => 'Anclas STEM'], 'path' => 'anclas',
        ]);
        foreach (self::ANCHORS as $code) {
            LearningObjective::create([
                'node_id' => $node->id, 'version_id' => $ver->id,
                'native_code' => $code, 'statement' => ['es' => 'destreza ancla'],
                'is_verified' => true,
            ]);
        }
    }

    public function test_siembra_los_cinco_marcos_internacionales(): void
    {
        $this->seed(InternationalFrameworksSeeder::class);

        $codes = Framework::pluck('code')->all();
        foreach (['CAIE-LSEC', 'CAIE-IGCSE', 'CAIE-ASA', 'IB-MYP', 'IB-DP'] as $code) {
            $this->assertContains($code, $codes);
        }

        // Todos son internacionales y sin país: no pertenecen a ninguna jurisdicción.
        $this->assertSame(5, Framework::where('kind', 'international')->whereNull('country')->count());
        $this->assertSame(5, FrameworkVersion::count(), 'una versión vigente por marco');
    }

    public function test_el_arbol_conserva_padres_y_paths_unicos_por_version(): void
    {
        $this->seed(InternationalFrameworksSeeder::class);

        // Cada marco tiene exactamente una raíz (el programme) y ningún huérfano.
        foreach (FrameworkVersion::all() as $ver) {
            $this->assertSame(1, $ver->roots()->count());
        }

        $stage7 = CurNode::where('path', 'lsec.math.s7')->firstOrFail();
        $this->assertSame('stage', $stage7->node_type);
        $this->assertSame('lsec.math', $stage7->parent->path);
        $this->assertSame('lsec', $stage7->parent->parent->path);
        $this->assertSame(11.0, $stage7->age_min);

        // Descendientes vía ltree/LIKE: los 4 strands de Stage 7 de Matemática.
        $this->assertSame(4, CurNode::descendantsOf($stage7)->count());

        // path único dentro de la versión (constraint del esquema).
        $paths = CurNode::get(['version_id', 'path'])
            ->map(fn ($n) => $n->version_id.'|'.$n->path);
        $this->assertSame($paths->count(), $paths->unique()->count());
    }

    public function test_el_mismo_codigo_puede_repetirse_entre_marcos(): void
    {
        $this->seed(InternationalFrameworksSeeder::class);

        // Stage 7 de Matemática y de Ciencias comparten native_code '7' en nodos distintos:
        // la clave siempre es (framework, versión, path/código), nunca el código solo.
        $sietes = CurNode::where('native_code', '7')->get();
        $this->assertGreaterThan(1, $sietes->count());
        $this->assertSame($sietes->count(), $sietes->pluck('path')->unique()->count());
    }

    public function test_los_enunciados_internacionales_entran_sin_verificar(): void
    {
        $this->seed(InternationalFrameworksSeeder::class);

        $this->assertSame(
            0,
            LearningObjective::where('is_verified', true)->count(),
            'son paráfrasis de trabajo: nadie las ha cotejado contra el syllabus oficial'
        );

        $lente = LearningObjective::where('native_code', '0625.3.2.3')->firstOrFail();
        $this->assertArrayHasKey('es', $lente->statement);
        $this->assertArrayHasKey('en', $lente->statement);
        $this->assertSame('parafrasis-semilla', $lente->attrs['fuente']);

        // Los atributos propios del marco (tier Core/Extended) viajan en attrs, no en columnas.
        $extended = LearningObjective::where('native_code', '0580.E2.13')->firstOrFail();
        $this->assertSame('Extended', $extended->attrs['tier']);
    }

    public function test_cada_syllabus_declara_su_vigencia_y_su_fuente(): void
    {
        $this->seed(InternationalFrameworksSeeder::class);

        // La etiqueta de framework_version es una foto: la vigencia real es por syllabus,
        // porque Cambridge los renueva en ciclos distintos.
        foreach (CurNode::whereIn('node_type', ['syllabus', 'subject'])->get() as $node) {
            $this->assertArrayHasKey('source_url', $node->attrs, "{$node->path} sin fuente");
        }

        $this->assertSame('2025-2027', CurNode::where('path', 'igcse.m0580')->firstOrFail()->attrs['vigencia']);
        $this->assertSame('2026-2028', CurNode::where('path', 'igcse.p0625')->firstOrFail()->attrs['vigencia']);

        // Trazabilidad: la versión guarda el sha256 del JSON sembrado.
        $this->assertSame(
            hash_file('sha256', database_path('data/marcos-internacionales.json')),
            FrameworkVersion::first()->source_sha256,
        );
    }

    public function test_los_seeders_son_idempotentes(): void
    {
        $this->seedMineducAnchors();
        $this->seed(InternationalFrameworksSeeder::class);
        $this->seed(CrosswalkSeeder::class);

        $nodos = CurNode::count();
        $objetivos = LearningObjective::count();
        $aristas = Alignment::count();

        // Volver a sembrar no debe duplicar nada ni reventar por unique.
        $this->seed(InternationalFrameworksSeeder::class);
        $this->seed(CrosswalkSeeder::class);

        $this->assertSame($nodos, CurNode::count());
        $this->assertSame($objetivos, LearningObjective::count());
        $this->assertSame($aristas, Alignment::count());
    }

    public function test_el_crosswalk_conecta_minedec_con_cambridge_e_ib(): void
    {
        $this->seedMineducAnchors();
        $this->seed(InternationalFrameworksSeeder::class);
        $this->seed(CrosswalkSeeder::class);

        $this->assertGreaterThan(30, Alignment::count());
        $this->assertSame(count(Concept::pluck('slug')->all()), Concept::pluck('slug')->unique()->count());

        $planoInclinado = LearningObjective::where('native_code', 'CN.F.5.1.9')->firstOrFail();
        $targets = $planoInclinado->alignments()->with('target')->get();

        $this->assertGreaterThanOrEqual(4, $targets->count());
        $this->assertContains('9709.4.1', $targets->pluck('target.native_code')->all());
        $this->assertContains('DP.PHY.A.2', $targets->pluck('target.native_code')->all());

        // Los criterios MYP evalúan procesos, no contenidos: nunca 'exact'.
        $myp = Alignment::query()
            ->whereIn('target_id', LearningObjective::where('native_code', 'like', 'MYP.%')->pluck('id'))
            ->pluck('relation')->unique();
        $this->assertNotContains('exact', $myp->all());
    }

    public function test_ninguna_arista_del_seeder_entra_a_produccion(): void
    {
        $this->seedMineducAnchors();
        $this->seed(InternationalFrameworksSeeder::class);
        $this->seed(CrosswalkSeeder::class);

        // Regla 5 de CLAUDE.md: la IA propone, el docente dispone.
        $this->assertSame(0, Alignment::production()->count());
        $this->assertSame(0, Alignment::whereNotNull('reviewed_at')->count());
    }

    public function test_los_prerrequisitos_dibujan_la_progresion_entre_marcos(): void
    {
        $this->seedMineducAnchors();
        $this->seed(InternationalFrameworksSeeder::class);
        $this->seed(CrosswalkSeeder::class);

        $igcse = LearningObjective::where('native_code', '0625.1.5.1')->firstOrFail();
        $previos = $igcse->prerequisites()->pluck('native_code')->all();
        $this->assertContains('8Pf.03', $previos, 'antes de F = ma en IGCSE van las fuerzas equilibradas de Stage 8');

        $alevel = LearningObjective::where('native_code', '9702.3.1')->firstOrFail();
        $this->assertContains('0625.1.5.1', $alevel->prerequisites()->pluck('native_code')->all());

        $this->assertSame(
            'manual',
            Alignment::where('relation', 'prerequisite')->first()->method,
            'la progresión entre marcos no la propone la IA'
        );
    }

    public function test_ningun_prerrequisito_cruza_entre_dp_y_a_level(): void
    {
        $this->seedMineducAnchors();
        $this->seed(InternationalFrameworksSeeder::class);
        $this->seed(CrosswalkSeeder::class);

        // Son titulaciones paralelas de 16-19: un alumno cursa una u otra, así que
        // un prerrequisito que cruce nunca se podría satisfacer. Ambas cuelgan de IGCSE.
        $dp = LearningObjective::where('native_code', 'like', 'DP.%')->pluck('id');
        $asa = LearningObjective::where('native_code', 'like', '97%')->pluck('id');

        $this->assertSame(0, Alignment::where('relation', 'prerequisite')
            ->whereIn('source_id', $dp)->whereIn('target_id', $asa)->count());
        $this->assertSame(0, Alignment::where('relation', 'prerequisite')
            ->whereIn('source_id', $asa)->whereIn('target_id', $dp)->count());
    }

    public function test_el_crosswalk_falla_ruidosamente_si_faltan_los_marcos(): void
    {
        $this->seedMineducAnchors();

        $this->expectException(RuntimeException::class);
        $this->seed(CrosswalkSeeder::class);
    }
}
