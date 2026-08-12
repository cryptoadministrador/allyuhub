<?php

namespace Tests\Feature;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\PracticeItem;
use App\Services\Practice\MathExpression;
use App\Services\Practice\PracticeEngine;
use Database\Seeders\PracticeItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El seeder de ítems de práctica: 5 ítems reales de plano inclinado para
 * CN.F.5.1.9 (descomposición del peso) y CN.F.5.1.12 (rozamiento y ángulo
 * crítico, μ = tan θc). Datos mínimos, sin el seeder curricular completo.
 */
class PracticeItemSeederTest extends TestCase
{
    use RefreshDatabase;

    private function makeObjectives(): void
    {
        $fw = Framework::create([
            'code' => 'EC-MINEDEC', 'authority' => 'MINEDEC', 'kind' => 'national',
            'country' => 'EC', 'label' => ['es' => 'Currículo Nacional'],
        ]);
        $version = FrameworkVersion::create(['framework_id' => $fw->id, 'label' => '2016+2023']);
        $node = CurNode::create([
            'version_id' => $version->id, 'node_type' => 'bloque',
            'title' => ['es' => 'Física — movimiento y fuerza'], 'path' => 'bgu.g11.cn_f.b2',
        ]);
        foreach (['CN.F.5.1.9', 'CN.F.5.1.12'] as $code) {
            LearningObjective::create([
                'node_id' => $node->id, 'version_id' => $version->id,
                'native_code' => $code, 'statement' => ['es' => '…'],
            ]);
        }
    }

    public function test_siembra_cinco_items_repartidos_entre_las_dos_destrezas(): void
    {
        $this->makeObjectives();

        $this->seed(PracticeItemSeeder::class);

        $this->assertSame(5, PracticeItem::count());

        $byCode = fn (string $code) => PracticeItem::whereHas(
            'objective', fn ($q) => $q->where('native_code', $code),
        )->count();
        $this->assertGreaterThanOrEqual(2, $byCode('CN.F.5.1.9'));
        $this->assertGreaterThanOrEqual(2, $byCode('CN.F.5.1.12'));
    }

    public function test_todo_item_sembrado_instancia_y_evalua_a_un_numero_finito(): void
    {
        $this->makeObjectives();
        $this->seed(PracticeItemSeeder::class);

        $engine = new PracticeEngine;

        foreach (PracticeItem::all() as $item) {
            $seed = $engine->seedFor($item->id, 1, 1);
            $params = $engine->sampleParams($item->params, $seed);

            // La expresión de solución evalúa con los parámetros del propio ítem.
            $expected = MathExpression::evaluate($item->solution_expr, $params);
            $this->assertTrue(is_finite($expected), "solution_expr no finita en el ítem {$item->id}");

            // El enunciado no deja variables sin resolver.
            $rendered = $engine->renderStatement($item->statement, $params);
            $this->assertStringNotContainsString('{', $rendered['es']);
        }
    }

    public function test_falla_ruidosamente_si_faltan_las_destrezas(): void
    {
        // Sin grafo sembrado: mejor una excepción clara que un seeder silencioso.
        $this->expectException(\RuntimeException::class);

        $this->seed(PracticeItemSeeder::class);
    }
}
