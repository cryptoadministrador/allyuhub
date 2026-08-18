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
use App\Services\Practice\AdaptiveSelector;
use App\Services\Practice\PracticeEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * FRENTE 1 — /inicio, la página que hace que esto se sienta plataforma.
 *
 * El oráculo que más importa aquí es el de DIVERGENCIA: «Tu siguiente paso»
 * tiene que ser exactamente lo que decide AdaptiveSelector. Si alguien
 * reimplementa la política en el controlador, este test lo caza.
 */
class InicioPageTest extends TestCase
{
    use RefreshDatabase;

    private FrameworkVersion $version;

    private CurNode $node;

    private LearningObjective $base;

    private LearningObjective $prereq;

    private LearningObjective $avance;

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

        $this->base = $this->objetivo('CN.F.5.1.9', 'Explicar el movimiento en el plano inclinado.');
        $this->prereq = $this->objetivo('CN.4.3.5', 'Explicar las fuerzas que actúan sobre los objetos.');
        $this->avance = $this->objetivo('CN.F.5.1.12', 'Determinar el coeficiente de rozamiento.');

        // base ← prerequisite → prereq ; avance ← prerequisite → base (manual: las admite el motor).
        $this->arista($this->base, $this->prereq);
        $this->arista($this->avance, $this->base);

        foreach ([$this->base, $this->prereq, $this->avance] as $o) {
            $this->item($o);
        }

        $this->ana = User::factory()->create();
    }

    private function objetivo(string $code, string $texto): LearningObjective
    {
        return LearningObjective::create([
            'node_id' => $this->node->id, 'version_id' => $this->version->id,
            'native_code' => $code, 'statement' => ['es' => $texto], 'is_verified' => true,
        ]);
    }

    private function arista(LearningObjective $source, LearningObjective $target): void
    {
        Alignment::create([
            'source_id' => $source->id, 'target_id' => $target->id,
            'relation' => 'prerequisite', 'method' => 'manual', 'confidence' => 0.9,
        ]);
    }

    private function item(LearningObjective $objetivo): PracticeItem
    {
        return PracticeItem::create([
            'objective_id' => $objetivo->id,
            'statement' => ['es' => 'm={m} kg, θ={theta}°, g={g}'],
            'params' => [
                'm' => ['min' => 1, 'max' => 20, 'step' => 0.5],
                'theta' => ['min' => 10, 'max' => 45, 'step' => 1],
                'g' => ['const' => 9.8],
            ],
            'solution_expr' => 'm * g * sin(deg2rad(theta))',
            'tolerance' => 0.02, 'tolerance_kind' => 'rel', 'answer_unit' => 'N',
        ]);
    }

    /** Responde el intento en curso de la destreza, bien o mal. */
    private function intento(LearningObjective $objetivo, bool $correcto): void
    {
        $item = $objetivo->practiceItems()->first();
        $engine = new PracticeEngine;
        $n = $item->attempts()->where('user_id', $this->ana->id)->count() + 1;
        $params = $engine->sampleParams($item->params, $engine->seedFor($item->id, $this->ana->id, $n));
        $esperado = $params['m'] * $params['g'] * sin(deg2rad($params['theta']));

        $this->actingAs($this->ana)->postJson("/api/v1/practice/items/{$item->id}/attempts", [
            'answer' => $correcto ? $esperado : $esperado + 50,
        ])->assertCreated();
    }

    public function test_inicio_exige_sesion(): void
    {
        $this->get('/inicio')->assertRedirect('/entrar');
    }

    /**
     * FRENTE 3: cada tarjeta se pinta con el acento de SU asignatura. El nodo
     * de la destreza es un bloque, así que el icono y el color se heredan
     * subiendo por el árbol hasta la asignatura.
     */
    public function test_las_tarjetas_traen_el_acento_de_su_asignatura(): void
    {
        $asignatura = CurNode::create([
            'version_id' => $this->version->id, 'parent_id' => $this->node->id,
            'node_type' => 'asignatura', 'native_code' => 'CN.F',
            'title' => ['es' => 'Física'], 'path' => 'bgu.g11.cn_f',
            'attrs' => ['icon' => '⚛️', 'color' => '#3aa675'],
        ]);
        $bloque = CurNode::create([
            'version_id' => $this->version->id, 'parent_id' => $asignatura->id,
            'node_type' => 'bloque', 'title' => ['es' => 'Movimiento'],
            'path' => 'bgu.g11.cn_f.b2',
        ]);
        $this->base->update(['node_id' => $bloque->id]);

        $this->intento($this->base, false);

        $this->actingAs($this->ana)->get('/inicio')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('continuar.asignatura.title', 'Física')
                ->where('continuar.asignatura.icon', '⚛️')
                ->where('continuar.asignatura.color', '#3aa675')
            );
    }

    /** Sin asignatura en la cadena (o sin estilos), la prop es null y la UI degrada. */
    public function test_sin_asignatura_el_acento_es_null(): void
    {
        $this->intento($this->base, false);

        $this->actingAs($this->ana)->get('/inicio')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('continuar.asignatura', null));
    }

    public function test_alumno_nuevo_recibe_un_estado_vacio_digno(): void
    {
        $this->actingAs($this->ana)->get('/inicio')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('inicio')
                ->where('continuar', null)
                ->where('siguiente', null)
                ->where('resumen.dominadas', 0)
                ->where('resumen.en_progreso', 0)
                ->where('resumen.practicadas', 0)
            );
    }

    public function test_continua_donde_ibas_es_la_ultima_destreza_practicada(): void
    {
        $this->intento($this->prereq, true);
        $this->intento($this->base, false);   // la más reciente

        $this->actingAs($this->ana)->get('/inicio')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('continuar.objective_id', $this->base->id)
                ->where('continuar.native_code', 'CN.F.5.1.9')
                ->where('continuar.statement', 'Explicar el movimiento en el plano inclinado.')
                ->has('continuar.mastery')
                ->where('resumen.practicadas', 2)
            );
    }

    /**
     * EL ORÁCULO DE DIVERGENCIA. Se arma el grafo, se pone al alumno en un
     * estado concreto y se compara la prop de /inicio con lo que devuelve el
     * servicio. Si alguien duplica la política en el controlador, divergen.
     */
    public function test_el_siguiente_paso_es_exactamente_el_del_selector_en_refuerzo(): void
    {
        // Dos fallos seguidos en base con mastery < 0.4 ⇒ el selector RETROCEDE.
        $this->intento($this->base, false);
        $this->intento($this->base, false);

        $decision = app(AdaptiveSelector::class)->next($this->base->fresh(), $this->ana->id);
        $this->assertSame(AdaptiveSelector::REASON_RETREAT, $decision['reason'], 'El escenario no provoca refuerzo');

        $this->actingAs($this->ana)->get('/inicio')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('siguiente.objective_id', $decision['objective']->id)
                ->where('siguiente.native_code', $decision['objective']->native_code)
                ->where('siguiente.reason', $decision['reason'])
            );
    }

    public function test_el_siguiente_paso_es_exactamente_el_del_selector_en_avance(): void
    {
        // Cuatro aciertos ⇒ base dominada ⇒ el selector AVANZA.
        foreach (range(1, 4) as $i) {
            $this->intento($this->base, true);
        }

        $decision = app(AdaptiveSelector::class)->next($this->base->fresh(), $this->ana->id);
        $this->assertSame(AdaptiveSelector::REASON_ADVANCE, $decision['reason'], 'El escenario no provoca avance');

        $this->actingAs($this->ana)->get('/inicio')
            ->assertInertia(fn (Assert $page) => $page
                ->where('siguiente.objective_id', $decision['objective']->id)
                ->where('siguiente.reason', AdaptiveSelector::REASON_ADVANCE)
            );
    }

    public function test_el_resumen_cuenta_dominadas_y_en_progreso(): void
    {
        ObjectiveMastery::create([
            'user_id' => $this->ana->id, 'objective_id' => $this->base->id,
            'mastery' => 0.85, 'streak' => 4, 'attempts_count' => 5, 'mastered_at' => now(),
        ]);
        ObjectiveMastery::create([
            'user_id' => $this->ana->id, 'objective_id' => $this->prereq->id,
            'mastery' => 0.35, 'streak' => 1, 'attempts_count' => 1,
        ]);
        // De OTRO alumno: no puede contar.
        ObjectiveMastery::create([
            'user_id' => User::factory()->create()->id, 'objective_id' => $this->avance->id,
            'mastery' => 0.9, 'streak' => 5, 'attempts_count' => 6, 'mastered_at' => now(),
        ]);

        $this->actingAs($this->ana)->get('/inicio')
            ->assertInertia(fn (Assert $page) => $page
                ->where('resumen.dominadas', 1)
                ->where('resumen.en_progreso', 1)
                ->where('resumen.practicadas', 2)
            );
    }

    /** ORÁCULO: props mínimas — ni la solución ni la semilla salen de casa. */
    public function test_las_props_no_llevan_nada_sensible(): void
    {
        $this->intento($this->base, false);
        $this->intento($this->base, false);

        $respuesta = $this->actingAs($this->ana)->get('/inicio')->assertOk();

        $respuesta->assertInertia(fn (Assert $page) => $page
            ->missing('continuar.solution_expr')
            ->missing('continuar.seed')
            ->missing('siguiente.solution_expr')
            ->missing('siguiente.item')
            ->missing('siguiente.seed')
            ->where('auth.user.id', $this->ana->id)
            ->missing('auth.user.email')
        );

        $html = $respuesta->getContent();
        $this->assertStringNotContainsString('solution_expr', $html);
        $this->assertStringNotContainsString('deg2rad', $html);
        $this->assertStringNotContainsString('sampleParams', $html);
    }

    public function test_la_raiz_autenticada_lleva_al_inicio(): void
    {
        $this->actingAs($this->ana)->get('/')->assertRedirect('/inicio');
    }
}
