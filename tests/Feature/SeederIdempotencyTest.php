<?php

namespace Tests\Feature;

use App\Models\Alignment;
use App\Models\CurNode;
use App\Models\Framework;
use App\Models\LearningObjective;
use App\Models\PracticeItem;
use App\Models\Track;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `deploy/install.sh` re-ejecuta `migrate --seed` en CADA actualización del
 * droplet. El 2026-08-16 CurriculumSeeder no era idempotente: el segundo seed
 * moría con `duplicate key frameworks_code_unique` y abortaba install.sh ANTES
 * de recachear las rutas — el endpoint nuevo daba 404 con el código ya
 * desplegado. Este test es el oráculo de ese contrato: sembrar dos veces no
 * puede fallar ni duplicar nada.
 */
class SeederIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_sembrar_dos_veces_no_falla_ni_duplica(): void
    {
        $this->seed(DatabaseSeeder::class);

        $counts = $this->counts();
        $this->assertGreaterThan(900, $counts['objectives'], 'la semilla debe traer el árbol completo');

        // La segunda pasada es la que reventaba en producción.
        $this->seed(DatabaseSeeder::class);

        $this->assertSame($counts, $this->counts(), 'el segundo seed no puede crear ni borrar nada');
    }

    /**
     * La semilla NUNCA pisa un import: si EC-MINEDEC ya existe (p. ej. lo trajo
     * `mineduc:import --official`), CurriculumSeeder no toca ni el enunciado de
     * una destreza. updateOrCreate aquí sería un bug, no una mejora.
     */
    public function test_la_semilla_no_pisa_un_marco_existente(): void
    {
        $this->seed(DatabaseSeeder::class);

        $objective = LearningObjective::whereHas('node.version.framework',
            fn ($q) => $q->where('code', 'EC-MINEDEC'))->firstOrFail();
        $objective->update(['statement' => ['es' => 'Texto OFICIAL importado del PDF'], 'is_verified' => true]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame('Texto OFICIAL importado del PDF', $objective->fresh()->statement['es']);
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        return [
            'frameworks' => Framework::count(),
            'nodes' => CurNode::count(),
            'objectives' => LearningObjective::count(),
            'tracks' => Track::count(),
            'alignments' => Alignment::count(),
            'items' => PracticeItem::count(),
        ];
    }
}
