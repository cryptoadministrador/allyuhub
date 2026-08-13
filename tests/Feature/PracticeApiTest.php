<?php

namespace Tests\Feature;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\PracticeItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Motor de práctica por API: instanciación determinista por semilla
 * hash(item:user:intento) y verificación SOLO en el servidor.
 *
 * IDs fijos (ítem) + autoincrement de users (1, 2, …) → los números instanciados
 * son deterministas entre corridas: las aserciones de (des)igualdad no flakean.
 */
class PracticeApiTest extends TestCase
{
    use RefreshDatabase;

    private const ITEM_ID = '0198e2c0-0000-7000-8000-000000000001';

    private const ITEM2_ID = '0198e2c0-0000-7000-8000-000000000002';

    private LearningObjective $objective;

    private User $ana;

    private User $luis;

    protected function setUp(): void
    {
        parent::setUp();

        $fw = Framework::create([
            'code' => 'EC-MINEDEC', 'authority' => 'MINEDEC', 'kind' => 'national',
            'country' => 'EC', 'label' => ['es' => 'Currículo Nacional'],
        ]);
        $version = FrameworkVersion::create(['framework_id' => $fw->id, 'label' => '2016+2023']);
        $grade = CurNode::create([
            'version_id' => $version->id, 'node_type' => 'grado',
            'native_code' => 'g11', 'title' => ['es' => '1.º BGU'], 'path' => 'bgu.g11',
        ]);
        $this->objective = LearningObjective::create([
            'node_id' => $grade->id, 'version_id' => $version->id,
            'native_code' => 'CN.F.5.1.12',
            'statement' => ['es' => 'Determinar el coeficiente de rozamiento…'],
        ]);
        PracticeItem::create([
            'id' => self::ITEM_ID,
            'objective_id' => $this->objective->id,
            'statement' => ['es' => 'Un bloque de {m} kg reposa sobre un plano inclinado {theta}°. Con g = {g} m/s², calcula la componente del peso paralela al plano (en N).'],
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

        $this->ana = User::factory()->create();    // id 1
        $this->luis = User::factory()->create();   // id 2
    }

    private function next(User $user)
    {
        // La identidad sale de la SESIÓN (actingAs), jamás de un user_id.
        return $this->actingAs($user)
            ->getJson("/api/v1/objectives/{$this->objective->id}/practice/next");
    }

    /** La física del ítem, calculada con PHP puro: contraste independiente del motor. */
    private function expectedFor(array $params): float
    {
        return $params['m'] * $params['g'] * sin(deg2rad($params['theta']));
    }

    public function test_siguiente_item_es_determinista_y_no_filtra_la_solucion(): void
    {
        $first = $this->next($this->ana)->assertOk()
            ->assertJsonPath('item_id', self::ITEM_ID)
            ->assertJsonPath('attempt_no', 1)
            ->assertJsonMissingPath('solution_expr')
            ->assertJsonMissingPath('expected');

        // Mientras no responda, repetir la petición devuelve LO MISMO (misma semilla).
        $second = $this->next($this->ana)->assertOk();
        $this->assertSame($first->json('params'), $second->json('params'));
        $this->assertSame($first->json('statement'), $second->json('statement'));

        // El enunciado lleva los valores interpolados, sin llaves sin resolver.
        $this->assertStringNotContainsString('{m}', $first->json('statement.es'));
        $this->assertStringNotContainsString('{theta}', $first->json('statement.es'));
    }

    public function test_alumnos_distintos_reciben_numeros_distintos(): void
    {
        $a = $this->next($this->ana)->assertOk()->json('params');
        $b = $this->next($this->luis)->assertOk()->json('params');

        $this->assertNotSame($a, $b);
    }

    public function test_intento_correcto_incorrecto_y_con_tolerancia(): void
    {
        // Intento 1: respuesta exacta → correcto.
        $p1 = $this->next($this->ana)->json('params');
        $this->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', [
            'answer' => $this->expectedFor($p1), 'time_ms' => 42000,
        ])->assertCreated()
            ->assertJsonPath('attempt_no', 1)
            ->assertJsonPath('is_correct', true);

        // Intento 2: números nuevos; muy desviada → incorrecto.
        $p2 = $this->next($this->ana)->json('params');
        $this->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', [
            'answer' => $this->expectedFor($p2) + 10,
        ])->assertCreated()
            ->assertJsonPath('attempt_no', 2)
            ->assertJsonPath('is_correct', false);

        // Intento 3: dentro de la tolerancia relativa del 2 % → correcto.
        $p3 = $this->next($this->ana)->json('params');
        $this->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', [
            'answer' => $this->expectedFor($p3) * 1.01,
        ])->assertCreated()
            ->assertJsonPath('attempt_no', 3)
            ->assertJsonPath('is_correct', true);

        // Lo persistido coincide con lo verificado en servidor.
        $this->assertDatabaseCount('practice_attempts', 3);
        $this->assertDatabaseHas('practice_attempts', [
            'item_id' => self::ITEM_ID, 'user_id' => $this->ana->id,
            'attempt_no' => 2, 'is_correct' => false,
        ]);
    }

    public function test_tras_un_intento_cambian_los_numeros(): void
    {
        $p1 = $this->next($this->ana)->json('params');
        $this->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', [
            'answer' => 0,
        ])->assertCreated();

        $again = $this->next($this->ana)->assertOk();
        $this->assertSame(2, $again->json('attempt_no'));
        $this->assertNotSame($p1, $again->json('params'));
    }

    public function test_el_servidor_ignora_el_is_correct_del_cliente(): void
    {
        $p1 = $this->next($this->ana)->json('params');

        // El cliente miente: respuesta absurda marcada como "correcta".
        $this->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', [
            'answer' => $this->expectedFor($p1) + 1000,
            'is_correct' => true,
            'expected' => 0,
        ])->assertCreated()->assertJsonPath('is_correct', false);

        $this->assertDatabaseHas('practice_attempts', [
            'item_id' => self::ITEM_ID, 'user_id' => $this->ana->id,
            'attempt_no' => 1, 'is_correct' => false,
        ]);
    }

    public function test_rota_al_item_menos_practicado_del_objetivo(): void
    {
        PracticeItem::create([
            'id' => self::ITEM2_ID,
            'objective_id' => $this->objective->id,
            'statement' => ['es' => '¿Cuál es el ángulo crítico si μs = {mu}? (en grados)'],
            'params' => ['mu' => ['min' => 0.2, 'max' => 0.9, 'step' => 0.05]],
            'solution_expr' => 'rad2deg(atan(mu))',
            'tolerance' => 0.5,
            'tolerance_kind' => 'abs',
            'answer_unit' => '°',
            'seq' => 1,
        ]);

        // Sin intentos: sale el primero (seq 0).
        $this->next($this->ana)->assertOk()->assertJsonPath('item_id', self::ITEM_ID);

        $this->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', [
            'answer' => 0,
        ])->assertCreated();

        // Tras practicar el primero, toca el menos practicado.
        $this->next($this->ana)->assertOk()->assertJsonPath('item_id', self::ITEM2_ID);
    }

    public function test_validaciones(): void
    {
        // Sin sesión no se practica (la autorización fina vive en PracticeAuthTest).
        $this->getJson("/api/v1/objectives/{$this->objective->id}/practice/next")
            ->assertUnauthorized();

        $this->actingAs($this->ana);
        $this->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', [])
            ->assertStatus(422);   // falta answer
        $this->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', [
            'answer' => 'no-numérica',
        ])->assertStatus(422);

        // Objetivo sin ítems de práctica → 404.
        $bare = LearningObjective::create([
            'node_id' => $this->objective->node_id, 'version_id' => $this->objective->version_id,
            'native_code' => 'CN.F.5.1.9', 'statement' => ['es' => '…'],
        ]);
        $this->getJson("/api/v1/objectives/{$bare->id}/practice/next")
            ->assertNotFound();
    }
}
