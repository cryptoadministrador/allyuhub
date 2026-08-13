<?php

namespace Tests\Feature;

use App\Models\LearningObjective;
use App\Models\PracticeItem;
use App\Models\Track;
use App\Models\TrackPhase;
use App\Models\User;
use App\Services\Practice\PracticeEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Carga ligera (fase D del motor v2): el coste en CONSULTAS de los endpoints
 * no crece ni con el número de intentos acumulados ni con el número de
 * ítems/fases (sin N+1). Se cuentan consultas reales con el query log —
 * el tiempo de pared sería flaky; el número de consultas es determinista.
 */
class PracticeLoadTest extends TestCase
{
    use RefreshDatabase;

    private function countQueries(callable $fn): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $fn();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /** Responde correctamente el intento en curso del ítem. */
    private function postCorrectAttempt(PracticeItem $item, User $user): void
    {
        $engine = new PracticeEngine;
        $attemptNo = $item->attempts()->where('user_id', $user->id)->count() + 1;
        $params = $engine->sampleParams($item->params, $engine->seedFor($item->id, $user->id, $attemptNo));
        $expected = $params['m'] * $params['g'] * sin(deg2rad($params['theta']));

        $this->postJson("/api/v1/practice/items/{$item->id}/attempts", [
            'user_id' => $user->id, 'answer' => $expected,
        ])->assertCreated();
    }

    public function test_200_intentos_seguidos_no_degradan_en_consultas(): void
    {
        $item = PracticeItem::factory()->create();
        $user = User::factory()->create();

        $first = null;
        foreach (range(1, 200) as $i) {
            $queries = $this->countQueries(fn () => $this->postCorrectAttempt($item, $user));
            $first ??= $queries;
        }

        // El intento 200 cuesta las mismas consultas que el 1.º: nada acumula.
        $this->assertSame($first, $queries);
        $this->assertSame(200, $item->attempts()->count());
    }

    public function test_next_no_hace_una_consulta_por_item(): void
    {
        $user = User::factory()->create();
        $small = LearningObjective::factory()->create();
        PracticeItem::factory()->count(2)->for($small, 'objective')->create();
        $big = LearningObjective::factory()->create();
        PracticeItem::factory()->count(12)->for($big, 'objective')->create();

        $q2 = $this->countQueries(fn () => $this->getJson(
            "/api/v1/objectives/{$small->id}/practice/next?user_id={$user->id}")->assertOk());
        $q12 = $this->countQueries(fn () => $this->getJson(
            "/api/v1/objectives/{$big->id}/practice/next?user_id={$user->id}")->assertOk());

        $this->assertSame($q2, $q12, 'next() no debe costar más consultas con más ítems');
    }

    public function test_progress_no_hace_una_consulta_por_fase(): void
    {
        $user = User::factory()->create();

        $chico = Track::create(['code' => 'T-CHICO', 'label' => ['es' => 'Chico']]);
        $grande = Track::create(['code' => 'T-GRANDE', 'label' => ['es' => 'Grande']]);

        $this->makePhases($chico, 1);
        $this->makePhases($grande, 6);

        $q1 = $this->countQueries(fn () => $this->getJson(
            "/api/v1/practice/progress?user_id={$user->id}&track=T-CHICO")->assertOk());
        $q6 = $this->countQueries(fn () => $this->getJson(
            "/api/v1/practice/progress?user_id={$user->id}&track=T-GRANDE")->assertOk());

        $this->assertSame($q1, $q6, 'progress no debe costar más consultas con más fases');
    }

    private function makePhases(Track $track, int $count): void
    {
        foreach (range(1, $count) as $seq) {
            $phase = TrackPhase::create([
                'track_id' => $track->id, 'seq' => $seq,
                'label' => ['es' => "Fase {$seq}"],
            ]);
            $objective = LearningObjective::factory()->create();
            $phase->objectives()->attach([$objective->id => ['source' => 'mapeo-interno']]);
        }
    }
}
