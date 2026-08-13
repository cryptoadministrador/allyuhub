<?php

namespace Tests\Feature;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\Track;
use App\Models\TrackPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Importador del currículo EPJA priorizado (Acuerdo 2025-00034-A).
 * Usa el fixture storage/curriculo/fixture-epja.txt, que reproduce a escala los
 * tres formatos del PDF real: mapas A/P (códigos anclados a inicio de línea),
 * el ruido de OCR («I. A.8.1.») y la tabla CAI de 4 columnas con guiones de corte.
 */
class ImportEpjaTest extends TestCase
{
    use RefreshDatabase;

    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = base_path('storage/curriculo/fixture-epja.txt');
    }

    /** Crea los tracks PCEI mínimos con el mapeo-interno que el importador debe sustituir. */
    private function seedTracks(): TrackPhase
    {
        $fw = Framework::create([
            'code' => 'EC-MINEDEC', 'authority' => 'MINEDEC', 'kind' => 'national',
            'country' => 'EC', 'label' => ['es' => 'Currículo Nacional'],
        ]);
        $ver = FrameworkVersion::create(['framework_id' => $fw->id, 'label' => '2016+2023']);
        $node = CurNode::create([
            'version_id' => $ver->id, 'node_type' => 'bloque',
            'title' => ['es' => 'g2 bloque'], 'path' => 'egb.elemental.g2.b1',
        ]);
        $lo = LearningObjective::create([
            'node_id' => $node->id, 'version_id' => $ver->id,
            'native_code' => 'LL.2.1.1', 'statement' => ['es' => 'marcador del grado equivalente'],
        ]);

        $track = Track::create(['code' => 'PCEI-ALFA', 'label' => ['es' => 'Alfabetización'], 'modality' => 'semipresencial']);
        TrackPhase::create([
            'track_id' => $track->id, 'seq' => 0,
            'label' => ['es' => 'Propedéutica'], 'is_propedeutic' => true,
        ]);
        $phase = TrackPhase::create([
            'track_id' => $track->id, 'seq' => 1, 'label' => ['es' => 'Módulo 1'],
        ]);
        $phase->objectives()->attach([$lo->id => ['source' => 'mapeo-interno']]);

        Track::create(['code' => 'PCEI-POST', 'label' => ['es' => 'Post-alfabetización'], 'modality' => 'semipresencial'])
            ->phases()->create(['seq' => 1, 'label' => ['es' => 'Módulo 3']]);

        return $phase;
    }

    public function test_dry_run_parsea_sin_escribir(): void
    {
        $this->artisan('epja:import', ['file' => $this->fixture, '--dry-run' => true])
            ->expectsOutputToContain('10 destrezas (4 ALFA · 3 POST · 3 CAI)')
            ->assertSuccessful();

        $this->assertSame(0, Framework::count());
        $this->assertSame(0, LearningObjective::count());
    }

    public function test_importa_el_grafo_epja_completo(): void
    {
        $this->artisan('epja:import', ['file' => $this->fixture])->assertSuccessful();

        $fw = Framework::where('code', 'EC-EPJA')->firstOrFail();
        $this->assertSame('national', $fw->kind);
        $ver = $fw->versions()->where('label', '2025-00034-A')->firstOrFail();

        // Árbol: programa → niveles A/P (con bloques RS/ET/CC) + rama CAI.
        $this->assertNotNull(CurNode::where('version_id', $ver->id)->where('path', 'epja')->first());
        $alfa = CurNode::where('version_id', $ver->id)->where('path', 'epja.alfabetizacion')->firstOrFail();
        $this->assertSame(['1', '2'], array_map('strval', $alfa->attrs['modulos']));
        $this->assertSame(3, $alfa->children()->count(), 'bloques RS/ET/CC');

        $post = CurNode::where('version_id', $ver->id)->where('path', 'epja.postalfabetizacion')->firstOrFail();
        $this->assertSame([3, 4, 5, 6], array_map('intval', $post->attrs['modulos']));

        // Destrezas del fixture: 4 ALFA + 3 POST + 3 CAI.
        $this->assertSame(10, LearningObjective::where('version_id', $ver->id)->count());
        $rs1 = LearningObjective::where('version_id', $ver->id)->where('native_code', 'A.RS.1')->firstOrFail();
        $this->assertStringContainsString('hipótesis de lectura', $rs1->statement['es']);
        $this->assertSame('rs', substr($rs1->node->path, -2));

        // Criterios, indicadores y objetivos viajan en attrs del nivel — incluida
        // la normalización del OCR «I. A.8.1.» → I.A.8.1.
        $this->assertCount(1, $alfa->attrs['criterios_evaluacion']);
        $this->assertCount(1, $alfa->attrs['objetivos_generales']);
        $codes = array_column($alfa->attrs['indicadores'], 0);
        $this->assertContains('I.A.8.1', $codes);
    }

    public function test_la_tabla_cai_se_reconstruye_por_columnas(): void
    {
        $this->artisan('epja:import', ['file' => $this->fixture])->assertSuccessful();

        $cai = LearningObjective::where('native_code', 'CAI.JA.1.1')->firstOrFail();
        // Solo la columna de la destreza: nada de «Pensamiento ético» (columna 3)
        // ni de «Investiga y analiza» (columna 4).
        $this->assertStringContainsString('imparcialidad', $cai->statement['es'], 'guion de corte reparado');
        $this->assertStringContainsString('resolución de dilemas éticos', $cai->statement['es']);
        $this->assertStringNotContainsString('Pensamiento', $cai->statement['es']);
        $this->assertStringNotContainsString('Investiga', $cai->statement['es']);

        $this->assertSame(
            3,
            LearningObjective::where('native_code', 'like', 'CAI.%')->count(),
            'una destreza por bloque CAI del fixture'
        );
        $this->assertSame('insercion', $cai->node->parent->node_type);
    }

    public function test_official_verifica_y_registra_sha(): void
    {
        $this->artisan('epja:import', ['file' => $this->fixture, '--official' => true])->assertSuccessful();

        $ver = FrameworkVersion::whereHas('framework', fn ($q) => $q->where('code', 'EC-EPJA'))->firstOrFail();
        $this->assertSame(hash_file('sha256', $this->fixture), $ver->source_sha256);
        $this->assertSame(0, LearningObjective::where('version_id', $ver->id)->where('is_verified', false)->count());

        // Una reimportación NO oficial no degrada lo verificado.
        $this->artisan('epja:import', ['file' => $this->fixture])->assertSuccessful();
        $this->assertSame(0, LearningObjective::where('version_id', $ver->id)->where('is_verified', false)->count());
    }

    public function test_reancla_los_tracks_pcei_al_curriculo_real(): void
    {
        $phase = $this->seedTracks();
        $this->assertSame(1, $phase->objectives()->wherePivot('source', 'mapeo-interno')->count());

        $this->artisan('epja:import', ['file' => $this->fixture])->assertSuccessful();

        $phase->refresh();
        // El mapeo-interno desaparece y entran las destrezas EPJA de su nivel
        // (4 ALFA) más las CAI transversales (3).
        $this->assertSame(0, $phase->objectives()->wherePivot('source', 'mapeo-interno')->count());
        $this->assertSame(7, $phase->objectives()->wherePivot('source', 'acuerdo-2025-00034-A')->count());

        // La fase propedéutica no recibe dosificación.
        $proped = TrackPhase::where('is_propedeutic', true)->firstOrFail();
        $this->assertSame(0, $proped->objectives()->count());

        // POST recibe sus 3 destrezas + 3 CAI.
        $post = Track::where('code', 'PCEI-POST')->firstOrFail()->phases()->first();
        $this->assertSame(6, $post->objectives()->count());
    }

    public function test_es_idempotente(): void
    {
        $this->seedTracks();
        $this->artisan('epja:import', ['file' => $this->fixture])->assertSuccessful();

        $nodos = CurNode::count();
        $los = LearningObjective::count();
        $pivotes = DB::table('track_phase_objectives')->count();

        $this->artisan('epja:import', ['file' => $this->fixture])->assertSuccessful();

        $this->assertSame($nodos, CurNode::count());
        $this->assertSame($los, LearningObjective::count());
        $this->assertSame($pivotes, DB::table('track_phase_objectives')->count());
    }

    public function test_rechaza_un_archivo_sin_codigos_epja(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'epja').'.txt';
        file_put_contents($file, str_repeat('Texto cualquiera sin códigos del currículo. ', 10));

        $this->artisan('epja:import', ['file' => $file])->assertFailed();
        $this->assertSame(0, Framework::count());
        unlink($file);
    }
}
