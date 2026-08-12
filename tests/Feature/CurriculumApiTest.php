<?php

namespace Tests\Feature;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\Resource;
use App\Models\Track;
use App\Models\TrackPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests de la API v1 sobre SQLite en memoria (las migraciones son duales:
 * ltree/GIN solo se crean en pgsql). Datos mínimos por test, sin el seeder completo.
 */
class CurriculumApiTest extends TestCase
{
    use RefreshDatabase;

    private FrameworkVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        $fw = Framework::create([
            'code' => 'EC-MINEDEC', 'authority' => 'MINEDEC', 'kind' => 'national',
            'country' => 'EC', 'label' => ['es' => 'Currículo Nacional'],
        ]);
        $this->version = FrameworkVersion::create([
            'framework_id' => $fw->id, 'label' => '2016+2023',
        ]);
    }

    private function makeGrade(): CurNode
    {
        return CurNode::create([
            'version_id' => $this->version->id, 'node_type' => 'grado',
            'native_code' => 'g11', 'title' => ['es' => '1.º de Bachillerato'],
            'path' => 'bgu.g11', 'seq' => 11,
        ]);
    }

    public function test_lista_frameworks(): void
    {
        $this->getJson('/api/v1/frameworks')
            ->assertOk()
            ->assertJsonFragment(['code' => 'EC-MINEDEC']);
    }

    public function test_navega_subarbol_y_objetivos(): void
    {
        $grade = $this->makeGrade();
        $subject = CurNode::create([
            'version_id' => $this->version->id, 'parent_id' => $grade->id,
            'node_type' => 'asignatura', 'native_code' => 'CN.F',
            'title' => ['es' => 'Física'], 'path' => 'bgu.g11.cn_f',
        ]);
        LearningObjective::create([
            'node_id' => $subject->id, 'version_id' => $this->version->id,
            'native_code' => 'CN.F.5.1.12',
            'statement' => ['es' => 'Determinar el coeficiente de rozamiento…'],
            'is_verified' => true,
        ]);

        // El subárbol del grado incluye la destreza de la asignatura hija.
        $this->getJson("/api/v1/nodes/{$grade->id}/objectives")
            ->assertOk()
            ->assertJsonFragment(['native_code' => 'CN.F.5.1.12']);
    }

    public function test_busqueda_de_destrezas(): void
    {
        $grade = $this->makeGrade();
        LearningObjective::create([
            'node_id' => $grade->id, 'version_id' => $this->version->id,
            'native_code' => 'CN.F.5.1.12',
            'statement' => ['es' => 'Determinar el coeficiente de rozamiento entre dos superficies'],
        ]);

        $this->getJson('/api/v1/objectives/search?q=rozamiento')
            ->assertOk()
            ->assertJsonFragment(['native_code' => 'CN.F.5.1.12']);

        $this->getJson('/api/v1/objectives/search?q=ab')->assertStatus(422); // mínimo 3 chars
    }

    public function test_track_pcei_con_fase_propedeutica(): void
    {
        $track = Track::create([
            'code' => 'PCEI-BI', 'label' => ['es' => 'Bachillerato Intensivo'],
            'min_age' => 18, 'min_gap_years' => 3, 'module_days' => 100,
        ]);
        TrackPhase::create([
            'track_id' => $track->id, 'seq' => 0,
            'label' => ['es' => 'Propedéutica'], 'is_propedeutic' => true,
        ]);

        $this->getJson('/api/v1/tracks/PCEI-BI')
            ->assertOk()
            ->assertJsonPath('min_age', 18)
            ->assertJsonPath('phases.0.is_propedeutic', true);
    }

    public function test_recurso_filtrado_por_destreza(): void
    {
        $grade = $this->makeGrade();
        $obj = LearningObjective::create([
            'node_id' => $grade->id, 'version_id' => $this->version->id,
            'native_code' => 'CN.F.5.1.12', 'statement' => ['es' => '…'],
        ]);
        $res = Resource::create([
            'slug' => 'plano-inclinado', 'kind' => 'lab',
            'title' => ['es' => 'Plano inclinado'], 'status' => 'published',
        ]);
        $res->objectives()->attach($obj->id, ['role' => 'primary']);

        $this->getJson('/api/v1/resources?objective=CN.F.5.1.12')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'plano-inclinado');

        // Un recurso en borrador no aparece.
        $res->update(['status' => 'draft']);
        $this->getJson('/api/v1/resources?objective=CN.F.5.1.12')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
