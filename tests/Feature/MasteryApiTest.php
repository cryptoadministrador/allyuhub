<?php

namespace Tests\Feature;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\ObjectiveMastery;
use App\Models\PracticeItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El registro de un intento actualiza el mastery de la destreza (misma
 * transacción) y GET /api/v1/practice/mastery lo expone por alumno.
 */
class MasteryApiTest extends TestCase
{
    use RefreshDatabase;

    private const ITEM_ID = '0198e2c0-0000-7000-8000-00000000000a';

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
        $node = CurNode::create([
            'version_id' => $version->id, 'node_type' => 'grado',
            'native_code' => 'g11', 'title' => ['es' => '1.º BGU'], 'path' => 'bgu.g11',
        ]);
        $this->objective = LearningObjective::create([
            'node_id' => $node->id, 'version_id' => $version->id,
            'native_code' => 'CN.F.5.1.9', 'statement' => ['es' => '…'],
        ]);
        PracticeItem::create([
            'id' => self::ITEM_ID,
            'objective_id' => $this->objective->id,
            'statement' => ['es' => 'Peso paralelo con m={m} kg, θ={theta}°, g={g}.'],
            'params' => [
                'm' => ['min' => 1, 'max' => 20, 'step' => 0.5],
                'theta' => ['min' => 10, 'max' => 45, 'step' => 1],
                'g' => ['const' => 9.8],
            ],
            'solution_expr' => 'm * g * sin(deg2rad(theta))',
            'tolerance' => 0.02,
            'tolerance_kind' => 'rel',
        ]);

        $this->ana = User::factory()->create();
        $this->luis = User::factory()->create();
    }

    /** Responde el intento en curso del ítem, bien o mal según $correct. */
    private function attempt(User $user, bool $correct): void
    {
        $params = $this->actingAs($user)->getJson(
            "/api/v1/objectives/{$this->objective->id}/practice/next"
        )->json('params');

        $expected = $params['m'] * $params['g'] * sin(deg2rad($params['theta']));

        $this->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', [
            'answer' => $correct ? $expected : $expected + 50,
        ])->assertCreated();
    }

    public function test_el_intento_actualiza_el_mastery_de_la_destreza(): void
    {
        $this->attempt($this->ana, true);

        $this->assertDatabaseHas('objective_masteries', [
            'user_id' => $this->ana->id,
            'objective_id' => $this->objective->id,
            'attempts_count' => 1,
            'streak' => 1,
        ]);

        $this->attempt($this->ana, false);

        $this->assertDatabaseHas('objective_masteries', [
            'user_id' => $this->ana->id,
            'attempts_count' => 2,
            'streak' => -1,
        ]);
    }

    public function test_endpoint_de_mastery_por_alumno(): void
    {
        $this->attempt($this->ana, true);
        $this->attempt($this->ana, true);
        $this->attempt($this->luis, false);

        $res = $this->actingAs($this->ana)->getJson('/api/v1/practice/mastery')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.objective_id', $this->objective->id)
            ->assertJsonPath('0.native_code', 'CN.F.5.1.9')
            ->assertJsonPath('0.streak', 2)
            ->assertJsonPath('0.attempts_count', 2)
            ->assertJsonPath('0.is_mastered', false);

        $this->assertEqualsWithDelta(0.5775, $res->json('0.mastery'), 1e-4);

        // Luis solo ve lo suyo (streak -1 por el fallo).
        $this->actingAs($this->luis)->getJson('/api/v1/practice/mastery')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.streak', -1);
    }

    public function test_mastered_llega_al_endpoint_tras_la_racha(): void
    {
        foreach (range(1, 4) as $i) {
            $this->attempt($this->ana, true);
        }

        $this->actingAs($this->ana)->getJson('/api/v1/practice/mastery')
            ->assertOk()
            ->assertJsonPath('0.is_mastered', true);
    }

    /**
     * Contenido abierto: el endpoint ya no responde 401 —rompería el bucle de
     * práctica del invitado, que lo consulta tras cada intento— pero un
     * invitado no tiene dominio, así que recibe una lista VACÍA. Lo que este
     * test vigila es que no reciba el de nadie: Ana tiene dominio real sembrado
     * arriba y no puede asomar por aquí.
     */
    public function test_el_invitado_recibe_una_lista_vacia_jamas_la_de_otro(): void
    {
        foreach (range(1, 4) as $i) {
            $this->attempt($this->ana, true);
        }
        $this->assertGreaterThan(0, ObjectiveMastery::where('user_id', $this->ana->id)->count());

        auth()->logout();
        $this->getJson('/api/v1/practice/mastery')->assertOk()->assertExactJson([]);
    }
}
