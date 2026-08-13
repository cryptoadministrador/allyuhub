<?php

namespace Tests\Feature;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\PracticeAttempt;
use App\Models\PracticeItem;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La deuda cerrada (misión frontend-auth): la API de práctica identifica al
 * alumno por su SESIÓN (`Auth::id()`), jamás por un user_id del request.
 *
 *  - Anónimo → 401 en los cuatro endpoints (ni siquiera puede practicar).
 *  - `user_id` en el request → 422 explícito (regla `prohibited`): mejor
 *    ruidoso que ignorado, para cazar clientes desactualizados.
 *  - IDOR: un alumno autenticado no puede leer ni escribir lo de otro.
 */
class PracticeAuthTest extends TestCase
{
    use RefreshDatabase;

    private const ITEM_ID = '0198e2c0-0000-7000-8000-0000000000f1';

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
            'native_code' => 'CN.F.5.1.9', 'statement' => ['es' => 'Plano inclinado…'],
        ]);
        PracticeItem::create([
            'id' => self::ITEM_ID,
            'objective_id' => $this->objective->id,
            'statement' => ['es' => 'm={m} kg, θ={theta}°, g={g}'],
            'params' => [
                'm' => ['min' => 1, 'max' => 20, 'step' => 0.5],
                'theta' => ['min' => 10, 'max' => 45, 'step' => 1],
                'g' => ['const' => 9.8],
            ],
            'solution_expr' => 'm * g * sin(deg2rad(theta))',
            'tolerance' => 0.02, 'tolerance_kind' => 'rel',
        ]);

        $this->ana = User::factory()->create();
        $this->luis = User::factory()->create();
    }

    public function test_anonimo_401_en_los_cuatro_endpoints(): void
    {
        $this->getJson("/api/v1/objectives/{$this->objective->id}/practice/next")
            ->assertUnauthorized();
        $this->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', ['answer' => 1])
            ->assertUnauthorized();
        $this->getJson('/api/v1/practice/mastery')->assertUnauthorized();
        $this->getJson('/api/v1/practice/progress?track=ORD')->assertUnauthorized();

        // Y no quedó rastro: el intento anónimo no se registró.
        $this->assertSame(0, PracticeAttempt::count());
    }

    public function test_user_id_en_el_request_es_rechazado_con_422(): void
    {
        $this->actingAs($this->ana);

        // El cliente viejo que aún mande user_id debe enterarse a gritos.
        $this->getJson("/api/v1/objectives/{$this->objective->id}/practice/next?user_id={$this->luis->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('user_id');

        $this->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', [
            'user_id' => $this->luis->id, 'answer' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('user_id');

        $this->getJson("/api/v1/practice/mastery?user_id={$this->luis->id}")
            ->assertStatus(422)->assertJsonValidationErrors('user_id');

        $this->getJson("/api/v1/practice/progress?track=ORD&user_id={$this->luis->id}")
            ->assertStatus(422)->assertJsonValidationErrors('user_id');

        $this->assertSame(0, PracticeAttempt::count());
    }

    public function test_idor_el_intento_siempre_se_registra_al_de_la_sesion(): void
    {
        // Luis autenticado intenta lo que sea: el intento es SUYO, no hay
        // forma de escribirle un intento a Ana.
        $this->actingAs($this->luis)
            ->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', ['answer' => 1])
            ->assertCreated();

        $this->assertSame(1, PracticeAttempt::where('user_id', $this->luis->id)->count());
        $this->assertSame(0, PracticeAttempt::where('user_id', $this->ana->id)->count());
    }

    public function test_idor_el_mastery_y_el_progreso_son_solo_los_propios(): void
    {
        Track::create(['code' => 'ORD', 'label' => ['es' => 'Ordinaria']]);

        // Ana practica (streak 1, mastery > 0).
        $this->actingAs($this->ana)
            ->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', ['answer' => 0])
            ->assertCreated();

        // Luis NO ve nada de Ana: ni en mastery ni en progress. No existe
        // ningún parámetro con el que pedir los datos de otra persona.
        $this->actingAs($this->luis);
        $this->getJson('/api/v1/practice/mastery')->assertOk()->assertJsonCount(0);
        $this->getJson('/api/v1/practice/progress?track=ORD')->assertOk();

        // Ana sí ve lo suyo.
        $this->actingAs($this->ana);
        $this->getJson('/api/v1/practice/mastery')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.objective_id', $this->objective->id);
    }

    public function test_la_api_responde_401_json_y_entrar_orienta_al_alumno(): void
    {
        // Bajo /api/* la respuesta es SIEMPRE JSON (shouldRenderJsonWhen):
        // un invitado recibe 401, nunca un redirect a mitad de un fetch.
        $this->get('/api/v1/practice/mastery')->assertUnauthorized();

        // La página de aterrizaje para sesiones caducadas existe y orienta
        // (las páginas de la app redirigen aquí: redirectGuestsTo).
        $this->get('/entrar')->assertOk()->assertSee('Moodle');
    }
}
