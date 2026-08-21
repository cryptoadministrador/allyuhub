<?php

namespace Tests\Unit;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\ObjectiveMastery;
use App\Models\User;
use App\Services\Practice\MasteryTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mastery learning por media móvil exponencial, determinista:
 *   acierto: mastery += α(1 − mastery)   (α = 0.35)
 *   fallo:   mastery ×= (1 − β)          (β = 0.30)
 * Racha FIRMADA: >0 aciertos seguidos, <0 fallos seguidos.
 * mastered ⇔ racha ≥ 3 aciertos con mastery ≥ 0.8 (se sella mastered_at).
 */
class MasteryTrackerTest extends TestCase
{
    use RefreshDatabase;

    private LearningObjective $objective;

    private User $user;

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
        $this->user = User::factory()->create();
    }

    /**
     * @param  int  $itemsAcertados  Ítems DISTINTOS acertados de esa destreza.
     *                               Por defecto los dos que exige el dominio: estos tests miden la
     *                               ARITMÉTICA de la EMA, y el listón de los dos ítems tiene los suyos.
     */
    private function apply(bool $isCorrect, int $itemsAcertados = MasteryTracker::ITEMS_TO_MASTER): ObjectiveMastery
    {
        return (new MasteryTracker)->apply(
            $this->user->id, $this->objective->id, $isCorrect, $itemsAcertados,
        );
    }

    public function test_acierto_sube_por_media_movil_exponencial(): void
    {
        $m = $this->apply(true);
        $this->assertEqualsWithDelta(0.35, $m->mastery, 1e-4);

        $m = $this->apply(true);
        $this->assertEqualsWithDelta(0.5775, $m->mastery, 1e-4);
        $this->assertSame(2, $m->streak);
        $this->assertSame(2, $m->attempts_count);
    }

    public function test_fallo_baja_multiplicativo_y_rompe_la_racha(): void
    {
        $this->apply(true);            // 0.35
        $m = $this->apply(false);      // 0.35 × 0.7 = 0.245

        $this->assertEqualsWithDelta(0.245, $m->mastery, 1e-4);
        $this->assertSame(-1, $m->streak);

        // Un segundo fallo profundiza la racha negativa.
        $m = $this->apply(false);
        $this->assertEqualsWithDelta(0.1715, $m->mastery, 1e-4);
        $this->assertSame(-2, $m->streak);

        // Un acierto la reinicia a +1.
        $m = $this->apply(true);
        $this->assertSame(1, $m->streak);
    }

    public function test_mastered_exige_racha_de_3_con_mastery_alto(): void
    {
        // 3 aciertos: racha 3 pero mastery 0.7254 < 0.8 → aún no dominado.
        $this->apply(true);
        $this->apply(true);
        $m = $this->apply(true);
        $this->assertEqualsWithDelta(0.725375, $m->mastery, 1e-4);
        $this->assertNull($m->mastered_at);
        $this->assertFalse($m->is_mastered);

        // 4.º acierto: mastery 0.8215 ≥ 0.8 y racha 4 ≥ 3 → dominado.
        $m = $this->apply(true);
        $this->assertEqualsWithDelta(0.821494, $m->mastery, 1e-3);
        $this->assertNotNull($m->mastered_at);
        $this->assertTrue($m->is_mastered);
    }

    /**
     * EL LISTÓN DE LOS DOS ÍTEMS. Una destreza con una sola pregunta no puede
     * dominarse por mucho que se repita: un `choice` no re-aleatoriza nada
     * entre intentos y al fallar se revela cuál era la buena, así que sellar
     * dominio ahí sería sellarlo por insistir. La aritmética sube igual —eso no
     * cambia—; lo que no se sella es el hito.
     */
    public function test_con_un_solo_item_acertado_no_se_sella_por_muchos_aciertos(): void
    {
        foreach (range(1, 10) as $i) {
            $m = $this->apply(true, itemsAcertados: 1);
        }

        $this->assertGreaterThan(MasteryTracker::MASTERY_THRESHOLD, (float) $m->mastery);
        $this->assertGreaterThanOrEqual(MasteryTracker::STREAK_TO_MASTER, $m->streak);
        $this->assertNull($m->mastered_at, 'Se selló el dominio con un único ítem.');
        $this->assertFalse($m->is_mastered);
    }

    /** Y en cuanto acierta un segundo ítem distinto, el hito se sella. */
    public function test_el_segundo_item_acertado_desbloquea_el_hito(): void
    {
        foreach (range(1, 5) as $i) {
            $this->apply(true, itemsAcertados: 1);
        }

        $m = $this->apply(true, itemsAcertados: 2);

        $this->assertNotNull($m->mastered_at);
    }

    public function test_mastered_es_un_hito_pero_el_mastery_sigue_vivo(): void
    {
        foreach (range(1, 4) as $i) {
            $this->apply(true);
        }
        $masteredAt = $this->apply(true)->mastered_at;
        $this->assertNotNull($masteredAt);

        // Un fallo posterior baja el mastery (×0.7 de 0.88397) pero no borra el hito.
        $m = $this->apply(false);
        $this->assertEqualsWithDelta(0.61878, $m->mastery, 1e-3);
        $this->assertEquals($masteredAt, $m->mastered_at);
    }

    public function test_una_fila_por_usuario_y_destreza(): void
    {
        $this->apply(true);
        $this->apply(false);
        $this->apply(true);

        $this->assertSame(1, ObjectiveMastery::count());
        $m = ObjectiveMastery::first();
        $this->assertSame(3, $m->attempts_count);
        $this->assertNotNull($m->last_attempt_at);
    }
}
