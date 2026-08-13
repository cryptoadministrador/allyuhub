<?php

namespace Tests\Feature;

use App\Models\Alignment;
use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\ObjectiveMastery;
use App\Models\PracticeItem;
use App\Models\User;
use App\Services\Practice\PracticeEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Selección adaptativa sobre el grafo de prerrequisitos (fase B del motor v2):
 *  - RETROCESO: ≥2 fallos seguidos y mastery < 0.4 → ítems de prerrequisitos
 *    (aristas `prerequisite` manual/revisadas) aún no dominados.
 *  - AVANCE: destreza dominada → destrezas que la tienen como prerrequisito.
 *  - Empates por menor mastery; mismo marco preferido; ítem con la semilla v1.
 *
 * Semántica de la arista (CrosswalkSeeder): alignment(source=A, target=B,
 * relation=prerequisite) se lee «para intentar A hay que dominar antes B».
 */
class AdaptivePracticeTest extends TestCase
{
    use RefreshDatabase;

    private FrameworkVersion $version;

    private CurNode $node;

    private LearningObjective $base;      // la destreza que se pide practicar

    private LearningObjective $prereq;    // su prerrequisito

    private LearningObjective $advance;   // la que se desbloquea al dominar base

    private User $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $fw = Framework::create([
            'code' => 'EC-MINEDEC', 'authority' => 'MINEDEC', 'kind' => 'national',
            'country' => 'EC', 'label' => ['es' => 'Currículo Nacional'],
        ]);
        $this->version = FrameworkVersion::create(['framework_id' => $fw->id, 'label' => '2016+2023']);
        $this->node = CurNode::create([
            'version_id' => $this->version->id, 'node_type' => 'grado',
            'native_code' => 'g11', 'title' => ['es' => '1.º BGU'], 'path' => 'bgu.g11',
        ]);

        $this->base = $this->objective('CN.F.5.1.9');
        $this->prereq = $this->objective('CN.4.3.5');
        $this->advance = $this->objective('CN.F.5.1.12');

        // base ← prerequisite → prereq ; advance ← prerequisite → base.
        $this->prerequisiteEdge($this->base, $this->prereq);
        $this->prerequisiteEdge($this->advance, $this->base);

        $this->item($this->base, '0198e2c0-0000-7000-8000-0000000000b1');
        $this->item($this->prereq, '0198e2c0-0000-7000-8000-0000000000b2');
        $this->item($this->advance, '0198e2c0-0000-7000-8000-0000000000b3');

        $this->ana = User::factory()->create();
    }

    private function objective(string $code, ?FrameworkVersion $version = null, ?CurNode $node = null): LearningObjective
    {
        $version ??= $this->version;

        return LearningObjective::create([
            'node_id' => ($node ?? $this->node)->id, 'version_id' => $version->id,
            'native_code' => $code, 'statement' => ['es' => $code],
        ]);
    }

    private function prerequisiteEdge(
        LearningObjective $source,
        LearningObjective $target,
        string $method = 'manual',
    ): Alignment {
        return Alignment::create([
            'source_id' => $source->id, 'target_id' => $target->id,
            'relation' => 'prerequisite', 'method' => $method, 'confidence' => 0.9,
        ]);
    }

    private function item(LearningObjective $objective, string $id): PracticeItem
    {
        return PracticeItem::create([
            'id' => $id,
            'objective_id' => $objective->id,
            'statement' => ['es' => 'Peso paralelo: m={m} kg, θ={theta}°, g={g} m/s².'],
            'params' => [
                'm' => ['min' => 1, 'max' => 20, 'step' => 0.5],
                'theta' => ['min' => 10, 'max' => 45, 'step' => 1],
                'g' => ['const' => 9.8],
            ],
            'solution_expr' => 'm * g * sin(deg2rad(theta))',
            'tolerance' => 0.02,
            'tolerance_kind' => 'rel',
            'answer_unit' => 'N',
        ]);
    }

    private function next(LearningObjective $objective)
    {
        return $this->actingAs($this->ana)
            ->getJson("/api/v1/objectives/{$objective->id}/practice/next");
    }

    /** Contesta el intento en curso del ítem del objetivo, bien o mal. */
    private function attempt(LearningObjective $objective, bool $correct): void
    {
        $item = $objective->practiceItems()->first();
        $res = $this->next($objective);
        // El intento se hace sobre el ítem del PROPIO objetivo aunque el selector
        // proponga otro: aquí se fuerza la práctica directa.
        $attemptNo = $item->attempts()->where('user_id', $this->ana->id)->count() + 1;
        $engine = new PracticeEngine;
        $params = $engine->sampleParams($item->params, $engine->seedFor($item->id, $this->ana->id, $attemptNo));
        $expected = $params['m'] * $params['g'] * sin(deg2rad($params['theta']));

        $this->actingAs($this->ana)->postJson("/api/v1/practice/items/{$item->id}/attempts", [
            'answer' => $correct ? $expected : $expected + 50,
        ])->assertCreated();
    }

    public function test_practica_normal_para_alumno_nuevo(): void
    {
        $this->next($this->base)->assertOk()
            ->assertJsonPath('objective_id', $this->base->id)
            ->assertJsonPath('item_id', '0198e2c0-0000-7000-8000-0000000000b1')
            ->assertJsonPath('reason', 'práctica normal');
    }

    public function test_retrocede_al_prerrequisito_tras_dos_fallos(): void
    {
        $this->attempt($this->base, false);
        $this->attempt($this->base, false);

        // streak = -2 y mastery = 0 < 0.4 → refuerzo del prerrequisito.
        $this->next($this->base)->assertOk()
            ->assertJsonPath('objective_id', $this->prereq->id)
            ->assertJsonPath('item_id', '0198e2c0-0000-7000-8000-0000000000b2')
            ->assertJsonPath('reason', 'refuerzo de prerrequisito');
    }

    public function test_un_solo_fallo_no_dispara_retroceso(): void
    {
        $this->attempt($this->base, false);

        $this->next($this->base)->assertOk()
            ->assertJsonPath('objective_id', $this->base->id)
            ->assertJsonPath('reason', 'práctica normal');
    }

    public function test_no_retrocede_a_prerrequisito_ya_dominado(): void
    {
        // Domina el prerrequisito (racha de 4 con mastery ≥ 0.8).
        foreach (range(1, 4) as $i) {
            $this->attempt($this->prereq, true);
        }
        // Luego fracasa la destreza base.
        $this->attempt($this->base, false);
        $this->attempt($this->base, false);

        // El único prerrequisito ya está dominado → práctica normal de base.
        $this->next($this->base)->assertOk()
            ->assertJsonPath('objective_id', $this->base->id)
            ->assertJsonPath('reason', 'práctica normal');
    }

    public function test_avanza_al_dominar_la_destreza(): void
    {
        foreach (range(1, 4) as $i) {
            $this->attempt($this->base, true);
        }

        // base dominada → propone la destreza que la tiene de prerrequisito.
        $this->next($this->base)->assertOk()
            ->assertJsonPath('objective_id', $this->advance->id)
            ->assertJsonPath('item_id', '0198e2c0-0000-7000-8000-0000000000b3')
            ->assertJsonPath('reason', 'avance');
    }

    public function test_ignora_aristas_llm_sin_revisar(): void
    {
        // Sustituye la arista manual por una llm-assisted sin revisar.
        Alignment::query()->delete();
        $this->prerequisiteEdge($this->base, $this->prereq, 'llm-assisted');

        $this->attempt($this->base, false);
        $this->attempt($this->base, false);

        // La arista no cuenta (ni manual ni revisada) → práctica normal.
        $this->next($this->base)->assertOk()
            ->assertJsonPath('objective_id', $this->base->id)
            ->assertJsonPath('reason', 'práctica normal');
    }

    public function test_desempata_por_menor_mastery(): void
    {
        // Segundo prerrequisito con ítem propio.
        $prereq2 = $this->objective('CN.4.3.10');
        $this->prerequisiteEdge($this->base, $prereq2);
        $this->item($prereq2, '0198e2c0-0000-7000-8000-0000000000b4');

        // Estado directo: base hundida; prereq con algo de dominio, prereq2 en cero.
        ObjectiveMastery::create([
            'user_id' => $this->ana->id, 'objective_id' => $this->base->id,
            'mastery' => 0.1, 'streak' => -2, 'attempts_count' => 2,
        ]);
        ObjectiveMastery::create([
            'user_id' => $this->ana->id, 'objective_id' => $this->prereq->id,
            'mastery' => 0.35, 'streak' => 1, 'attempts_count' => 1,
        ]);

        $this->next($this->base)->assertOk()
            ->assertJsonPath('objective_id', $prereq2->id)
            ->assertJsonPath('reason', 'refuerzo de prerrequisito');
    }

    public function test_prefiere_el_marco_del_objetivo_de_partida(): void
    {
        // Un "avance" alternativo en Cambridge, también colgado de base.
        $caie = Framework::create([
            'code' => 'CAIE-IGCSE', 'authority' => 'Cambridge International', 'kind' => 'international',
            'label' => ['es' => 'Cambridge IGCSE'],
        ]);
        $caieVer = FrameworkVersion::create(['framework_id' => $caie->id, 'label' => '2023-2025']);
        $caieNode = CurNode::create([
            'version_id' => $caieVer->id, 'node_type' => 'topic',
            'native_code' => '0625', 'title' => ['es' => 'Physics'], 'path' => 'igcse.0625',
        ]);
        $caieObj = $this->objective('0625.1.5.1', $caieVer, $caieNode);
        $this->prerequisiteEdge($caieObj, $this->base);
        $this->item($caieObj, '0198e2c0-0000-7000-8000-0000000000b5');

        // Cambridge parte con mastery 0 (menor que cualquier avance MINEDEC ya tocado),
        // pero el marco de partida manda: se propone el avance MINEDEC.
        ObjectiveMastery::create([
            'user_id' => $this->ana->id, 'objective_id' => $this->base->id,
            'mastery' => 0.83, 'streak' => 4, 'attempts_count' => 5,
            'mastered_at' => now(),
        ]);
        ObjectiveMastery::create([
            'user_id' => $this->ana->id, 'objective_id' => $this->advance->id,
            'mastery' => 0.35, 'streak' => 1, 'attempts_count' => 1,
        ]);

        $this->next($this->base)->assertOk()
            ->assertJsonPath('objective_id', $this->advance->id)
            ->assertJsonPath('reason', 'avance');

        // Pero si el avance del mismo marco desaparece, cruza de marco (fallback).
        $this->advance->practiceItems()->delete();

        $this->next($this->base)->assertOk()
            ->assertJsonPath('objective_id', $caieObj->id)
            ->assertJsonPath('reason', 'avance');
    }

    public function test_contrato_v1_intacto_mas_reason(): void
    {
        $res = $this->next($this->base)->assertOk();

        foreach (['item_id', 'objective_id', 'attempt_no', 'statement', 'params',
            'answer_unit', 'tolerance', 'tolerance_kind', 'reason'] as $key) {
            $this->assertArrayHasKey($key, $res->json());
        }
        $this->assertArrayNotHasKey('solution_expr', $res->json());
    }
}
