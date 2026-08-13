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
 * Banco de ítems de práctica (fase C del motor v2): 17 ítems de física
 * parametrizados sobre las 8 destrezas MINEDEC con is_verified=true.
 * El seeder es idempotente (updateOrCreate por objetivo+seq) y falla ruidoso
 * si falta una destreza o no está verificada.
 */
class PracticeItemSeederTest extends TestCase
{
    use RefreshDatabase;

    /** Los 8 códigos verificados que hoy admiten ítems (mecánica y óptica). */
    private const CODES = [
        'CN.4.3.5', 'CN.4.3.7', 'CN.4.3.10', 'CN.F.5.1.4',
        'CN.F.5.1.9', 'CN.F.5.1.12', 'CN.F.5.3.7', 'CN.F.5.3.8',
    ];

    private function makeObjectives(bool $verified = true): void
    {
        $fw = Framework::create([
            'code' => 'EC-MINEDEC', 'authority' => 'MINEDEC', 'kind' => 'national',
            'country' => 'EC', 'label' => ['es' => 'Currículo Nacional'],
        ]);
        $version = FrameworkVersion::create(['framework_id' => $fw->id, 'label' => '2016+2023']);
        $node = CurNode::create([
            'version_id' => $version->id, 'node_type' => 'bloque',
            'title' => ['es' => 'Física — movimiento, fuerza y óptica'], 'path' => 'bgu.g11.cn_f.b2',
        ]);
        foreach (self::CODES as $code) {
            LearningObjective::create([
                'node_id' => $node->id, 'version_id' => $version->id,
                'native_code' => $code, 'statement' => ['es' => '…'],
                'is_verified' => $verified,
            ]);
        }
    }

    public function test_siembra_17_items_sobre_las_8_destrezas_verificadas(): void
    {
        $this->makeObjectives();

        $this->seed(PracticeItemSeeder::class);

        $this->assertSame(17, PracticeItem::count());

        $byCode = fn (string $code) => PracticeItem::whereHas(
            'objective', fn ($q) => $q->where('native_code', $code),
        )->count();

        // Toda destreza verificada tiene al menos 2 ítems practicables.
        foreach (self::CODES as $code) {
            $this->assertGreaterThanOrEqual(2, $byCode($code), "Faltan ítems para {$code}");
        }
        $this->assertSame(3, $byCode('CN.F.5.1.12'));
    }

    public function test_es_idempotente(): void
    {
        $this->makeObjectives();

        $this->seed(PracticeItemSeeder::class);
        $ids = PracticeItem::orderBy('id')->pluck('id');

        $this->seed(PracticeItemSeeder::class);

        // Ni duplica ni recrea: mismos 17 ítems, mismos ids.
        $this->assertSame(17, PracticeItem::count());
        $this->assertEquals($ids, PracticeItem::orderBy('id')->pluck('id'));
    }

    public function test_todo_item_sembrado_instancia_y_evalua_a_un_numero_finito(): void
    {
        $this->makeObjectives();
        $this->seed(PracticeItemSeeder::class);

        $engine = new PracticeEngine;

        // Varios alumnos e intentos: ninguna combinación de rangos puede producir
        // división por cero, NaN ni llaves sin resolver.
        foreach (PracticeItem::all() as $item) {
            foreach ([[1, 1], [2, 1], [3, 2], [4, 3]] as [$userId, $attemptNo]) {
                $seed = $engine->seedFor($item->id, $userId, $attemptNo);
                $params = $engine->sampleParams($item->params, $seed);

                $expected = MathExpression::evaluate($item->solution_expr, $params);
                $this->assertTrue(
                    is_finite($expected),
                    "solution_expr no finita en {$item->solution_expr} con ".json_encode($params),
                );

                $rendered = $engine->renderStatement($item->statement, $params);
                $this->assertStringNotContainsString('{', $rendered['es']);
            }
        }
    }

    public function test_rechaza_destrezas_sin_verificar(): void
    {
        $this->makeObjectives(verified: false);

        $this->expectException(\RuntimeException::class);

        $this->seed(PracticeItemSeeder::class);
    }

    public function test_falla_ruidosamente_si_faltan_las_destrezas(): void
    {
        // Sin grafo sembrado: mejor una excepción clara que un seeder silencioso.
        $this->expectException(\RuntimeException::class);

        $this->seed(PracticeItemSeeder::class);
    }
}
