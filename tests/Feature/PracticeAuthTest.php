<?php

namespace Tests\Feature;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\ObjectiveMastery;
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
 *  - Anónimo → atiende los cuatro endpoints (el contenido es abierto), pero
 *    no escribe NI UNA FILA: la puerta que se cerró no es la de practicar,
 *    es la de guardar.
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
            // Firmado: este fixture prueba el MOTOR, y un ítem sin
            // revisar no llega al motor (ver DominioYFirmaTest).
            'reviewed_at' => now(),
        ]);

        $this->ana = User::factory()->create();
        $this->luis = User::factory()->create();
    }

    /**
     * Contenido abierto: los cuatro endpoints ATIENDEN al anónimo. Lo que este
     * test conserva —y es lo único que de verdad protegía— es la segunda mitad:
     * después de pasar por todos, no queda rastro. El 401 era el medio; la
     * ausencia de escritura es el fin.
     */
    public function test_el_anonimo_usa_los_cuatro_endpoints_y_no_deja_rastro(): void
    {
        Track::create(['code' => 'ORD', 'label' => ['es' => 'Ordinaria']]);

        $this->getJson("/api/v1/objectives/{$this->objective->id}/practice/next")
            ->assertOk();
        $this->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', [
            'answer' => 1, 'billete' => $this->billete(self::ITEM_ID),
        ])
            ->assertOk()
            ->assertJsonPath('se_guarda', false);
        $this->getJson('/api/v1/practice/mastery')->assertOk()->assertExactJson([]);
        $this->getJson('/api/v1/practice/progress?track=ORD')
            ->assertOk()
            ->assertJsonPath('se_guarda', false);

        // Y no quedó rastro: el intento anónimo no se registró.
        $this->assertSame(0, PracticeAttempt::count());
        $this->assertSame(0, ObjectiveMastery::count());
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
            'billete' => $this->billete(self::ITEM_ID, $this->ana->id),
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
        // EL BILLETE DE OTRO NO VALE. Es un vector nuevo: el billete lleva
        // dentro el numero de intento y la semilla, asi que si no fuera de
        // quien lo presenta, Luis podria escribir con el billete de Ana — o el
        // invitado con el de un alumno. Va atado a `Practitioner::seedKey`, la
        // MISMA identidad con la que se sella la semilla, para que no haya dos
        // nociones de quien-es-quien que puedan separarse.
        $this->actingAs($this->luis)
            ->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', [
                'answer' => 1, 'billete' => $this->billete(self::ITEM_ID, $this->ana->id),
            ])->assertStatus(422)->assertJsonValidationErrors('billete');

        // Ni el del invitado: '0' y 'invitado' no son la misma clave.
        $this->actingAs($this->luis)
            ->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', [
                'answer' => 1, 'billete' => $this->billete(self::ITEM_ID),
            ])->assertStatus(422)->assertJsonValidationErrors('billete');

        // NI EL DE OTRO ÍTEM, aunque sea suyo y esté bien firmado. Sin esta
        // comprobación —hueco real que encontró una mutación, no una lectura—
        // el billete de un ejercicio fácil serviría para responder otro: la
        // semilla que se aplicaría a este ítem sería la que se sorteó para el
        // otro, y el alumno estaría eligiendo su instancia.
        $otro = PracticeItem::create([
            'objective_id' => $this->objective->id, 'seq' => 1,
            'statement' => ['es' => 'Otro ejercicio'],
            'params' => ['x' => ['min' => 1, 'max' => 9, 'step' => 1]],
            'solution_expr' => 'x', 'tolerance' => 0.01, 'tolerance_kind' => 'abs',
            'reviewed_at' => now(),
        ]);

        $this->actingAs($this->luis)
            ->postJson("/api/v1/practice/items/{$otro->id}/attempts", [
                'answer' => 1, 'billete' => $this->billete(self::ITEM_ID, $this->luis->id),
            ])->assertStatus(422)->assertJsonValidationErrors('billete');

        $this->assertSame(0, PracticeAttempt::count());

        // Con el suyo, el intento es SUYO: no hay forma de escribirle uno a Ana.
        $this->actingAs($this->luis)
            ->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', [
                'answer' => 1, 'billete' => $this->billete(self::ITEM_ID, $this->luis->id),
            ])->assertCreated();

        $this->assertSame(1, PracticeAttempt::where('user_id', $this->luis->id)->count());
        $this->assertSame(0, PracticeAttempt::where('user_id', $this->ana->id)->count());
    }

    public function test_idor_el_mastery_y_el_progreso_son_solo_los_propios(): void
    {
        Track::create(['code' => 'ORD', 'label' => ['es' => 'Ordinaria']]);

        // Ana practica (streak 1, mastery > 0).
        $this->actingAs($this->ana)
            ->postJson('/api/v1/practice/items/'.self::ITEM_ID.'/attempts', [
                'answer' => 0, 'billete' => $this->billete(self::ITEM_ID, $this->ana->id),
            ])
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

    /**
     * La garantía que sigue viva tras abrir el contenido: un `fetch` NUNCA
     * recibe HTML. Antes se comprobaba con el 401 de la API; ahora esos
     * endpoints responden 200, así que lo que se fija es el tipo de respuesta —
     * JSON bajo /api/*, y la puerta con marca solo al NAVEGAR a lo del alumno.
     */
    public function test_un_fetch_nunca_recibe_html_y_entrar_orienta_al_alumno(): void
    {
        $respuesta = $this->get('/api/v1/practice/mastery')->assertOk();
        $this->assertStringStartsWith('application/json',
            $respuesta->headers->get('content-type'));

        // Lo del alumno sigue mandando a la puerta, y con un redirect de
        // navegación, no con un cuerpo que un fetch confundiría con datos.
        $this->get('/inicio')->assertRedirect('/entrar');

        // La página de aterrizaje existe y orienta: dice para qué sirve entrar
        // (guardar) y ofrece la salida abierta a quien solo quiere mirar.
        $this->get('/entrar')
            ->assertOk()
            ->assertSee('guardar')
            ->assertSee('aula virtual')
            ->assertSee('/catalogo');
    }
}
