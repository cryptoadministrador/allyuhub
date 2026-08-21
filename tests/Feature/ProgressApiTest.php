<?php

namespace Tests\Feature;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\ObjectiveMastery;
use App\Models\Track;
use App\Models\TrackPhase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/v1/practice/progress?track=… — resumen por fase del track para el
 * alumno de la SESIÓN: destrezas dominadas / en progreso / no iniciadas.
 */
class ProgressApiTest extends TestCase
{
    use RefreshDatabase;

    private Track $track;

    /** @var LearningObjective[] */
    private array $objectives = [];

    private User $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $fw = Framework::create([
            'code' => 'EC-MINEDEC', 'authority' => 'MINEDEC', 'kind' => 'national',
            'country' => 'EC', 'label' => ['es' => 'Currículo Nacional'],
        ]);
        $version = FrameworkVersion::create(['framework_id' => $fw->id, 'label' => '2016+2023']);
        $node = CurNode::create([
            'version_id' => $version->id, 'node_type' => 'grado',
            'native_code' => 'g11', 'title' => ['es' => '1.º BGU'], 'path' => 'bgu.g11',
        ]);
        foreach (['CN.F.5.1.4', 'CN.F.5.1.9', 'CN.F.5.1.12'] as $code) {
            $this->objectives[$code] = LearningObjective::create([
                'node_id' => $node->id, 'version_id' => $version->id,
                'native_code' => $code, 'statement' => ['es' => $code],
            ]);
        }

        $this->track = Track::create([
            'code' => 'PCEI-BI', 'label' => ['es' => 'Bachillerato Intensivo'],
            'min_age' => 18,
        ]);
        $prope = TrackPhase::create([
            'track_id' => $this->track->id, 'seq' => 0,
            'label' => ['es' => 'Propedéutica'], 'is_propedeutic' => true,
        ]);
        $m1 = TrackPhase::create([
            'track_id' => $this->track->id, 'seq' => 1,
            'label' => ['es' => 'Módulo 1'],
        ]);
        // Fase 0: 2 destrezas; fase 1: 1 destreza.
        $prope->objectives()->attach([
            $this->objectives['CN.F.5.1.4']->id => ['source' => 'mapeo-interno'],
            $this->objectives['CN.F.5.1.9']->id => ['source' => 'mapeo-interno'],
        ]);
        $m1->objectives()->attach([
            $this->objectives['CN.F.5.1.12']->id => ['source' => 'mapeo-interno'],
        ]);

        $this->ana = User::factory()->create();
    }

    public function test_resumen_por_fase_del_track(): void
    {
        // Ana domina CN.F.5.1.4, tiene en progreso CN.F.5.1.9 y no tocó CN.F.5.1.12.
        ObjectiveMastery::create([
            'user_id' => $this->ana->id, 'objective_id' => $this->objectives['CN.F.5.1.4']->id,
            'mastery' => 0.83, 'streak' => 4, 'attempts_count' => 5, 'mastered_at' => now(),
        ]);
        ObjectiveMastery::create([
            'user_id' => $this->ana->id, 'objective_id' => $this->objectives['CN.F.5.1.9']->id,
            'mastery' => 0.35, 'streak' => 1, 'attempts_count' => 1,
        ]);

        $this->actingAs($this->ana)->getJson('/api/v1/practice/progress?track=PCEI-BI')
            ->assertOk()
            ->assertJsonPath('track', 'PCEI-BI')
            ->assertJsonCount(2, 'phases')
            // Las fases llegan ordenadas por seq y con su bandera propedéutica.
            ->assertJsonPath('phases.0.seq', 0)
            ->assertJsonPath('phases.0.is_propedeutic', true)
            ->assertJsonPath('phases.0.objectives_total', 2)
            ->assertJsonPath('phases.0.mastered', 1)
            ->assertJsonPath('phases.0.in_progress', 1)
            ->assertJsonPath('phases.0.not_started', 0)
            ->assertJsonPath('phases.1.seq', 1)
            ->assertJsonPath('phases.1.objectives_total', 1)
            ->assertJsonPath('phases.1.mastered', 0)
            ->assertJsonPath('phases.1.in_progress', 0)
            ->assertJsonPath('phases.1.not_started', 1);
    }

    public function test_otro_alumno_parte_de_cero(): void
    {
        $luis = User::factory()->create();

        $this->actingAs($luis)->getJson('/api/v1/practice/progress?track=PCEI-BI')
            ->assertOk()
            ->assertJsonPath('phases.0.not_started', 2)
            ->assertJsonPath('phases.1.not_started', 1)
            ->assertJsonPath('phases.0.mastered', 0);
    }

    public function test_validaciones(): void
    {
        // Sin sesión → 200 con el trayecto y el avance a CERO: el invitado ve
        // la forma del recorrido, nunca el expediente de otro. La identidad
        // sigue sin viajar en la petición.
        $this->getJson('/api/v1/practice/progress?track=PCEI-BI')
            ->assertOk()
            ->assertJsonPath('se_guarda', false)
            ->assertJsonPath('phases.0.mastered', 0)
            ->assertJsonPath('phases.0.in_progress', 0);
        // Y el track sigue siendo obligatorio también sin sesión.
        $this->getJson('/api/v1/practice/progress')->assertStatus(422);

        $this->actingAs($this->ana);
        $this->getJson('/api/v1/practice/progress')->assertStatus(422);   // falta track
        $this->getJson('/api/v1/practice/progress?track=NO-EXISTE')->assertStatus(422);
    }
}
