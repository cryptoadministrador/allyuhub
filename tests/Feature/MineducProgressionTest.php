<?php

namespace Tests\Feature;

use App\Models\Alignment;
use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\ObjectiveMastery;
use App\Models\User;
use Database\Seeders\CrosswalkSeeder;
use Database\Seeders\InternationalFrameworksSeeder;
use Database\Seeders\PracticeItemSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Progresión de prerrequisitos DENTRO de EC-MINEDEC (roadmap §4, «falta»).
 *
 * Por qué existe este archivo aparte de AdaptivePracticeTest: aquel prueba la
 * POLÍTICA del selector con un grafo de juguete construido a mano. Este prueba
 * el GRAFO REAL que siembra CrosswalkSeeder — el que va a producción — y que
 * con él el retroceso y el avance de verdad disparan. Hasta ahora todas las
 * aristas `prerequisite` eran inter-marco (Cambridge/IB) y ningún objetivo
 * Cambridge/IB tiene practice_items, así que en producción el motor adaptativo
 * era estructuralmente inerte: siempre «práctica normal». Un test que solo
 * mirase la política no habría visto nunca ese agujero.
 */
class MineducProgressionTest extends TestCase
{
    use RefreshDatabase;

    /** Las 8 destrezas verificadas que hoy sostienen el banco de ítems. */
    private const ANCHORS = [
        'CN.4.3.5', 'CN.4.3.7', 'CN.4.3.10',
        'CN.F.5.1.4', 'CN.F.5.1.9', 'CN.F.5.1.12',
        'CN.F.5.3.7', 'CN.F.5.3.8',
    ];

    private User $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $fw = Framework::create([
            'code' => 'EC-MINEDEC', 'authority' => 'MINEDEC', 'kind' => 'national',
            'country' => 'EC', 'label' => ['es' => 'Currículo Nacional del Ecuador'],
        ]);
        $ver = FrameworkVersion::create(['framework_id' => $fw->id, 'label' => '2016+2023']);
        $node = CurNode::create([
            'version_id' => $ver->id, 'node_type' => 'bloque',
            'title' => ['es' => 'Física — movimiento, fuerza y óptica'], 'path' => 'bgu.g11.cn_f.b2',
        ]);
        foreach (self::ANCHORS as $code) {
            LearningObjective::create([
                'node_id' => $node->id, 'version_id' => $ver->id,
                'native_code' => $code, 'statement' => ['es' => $code],
                'is_verified' => true,
            ]);
        }

        $this->seed(InternationalFrameworksSeeder::class);
        $this->seed(CrosswalkSeeder::class);
        $this->seed(PracticeItemSeeder::class);

        $this->ana = User::factory()->create();
    }

    private function objective(string $code): LearningObjective
    {
        return LearningObjective::where('native_code', $code)->firstOrFail();
    }

    private function next(LearningObjective $objective)
    {
        return $this->actingAs($this->ana)
            ->getJson("/api/v1/objectives/{$objective->id}/practice/next");
    }

    /** Falla el ítem que el motor esté proponiendo para ese objetivo. */
    private function falla(LearningObjective $objective): void
    {
        // Circuito completo: se responde con el billete que vino con el ítem.
        $servido = $this->next($objective);
        $itemId = $servido->json('item_id');

        // Respuesta absurda: ninguna solución del banco se acerca al 2 % de esto.
        $this->actingAs($this->ana)
            ->postJson("/api/v1/practice/items/{$itemId}/attempts", [
                'answer' => -987654321, 'billete' => $servido->json('billete'),
            ])
            ->assertCreated()
            ->assertJsonPath('is_correct', false);
    }

    /**
     * ORÁCULO ESTRUCTURAL: toda arista de prerrequisito dentro de EC-MINEDEC
     * une dos destrezas CON ítems. Una arista hacia una destreza sin ítems es
     * decorativa — el selector la descarta en bestCandidate() y el retroceso
     * vuelve a ser silenciosamente «práctica normal».
     */
    public function test_las_aristas_internas_de_minedec_son_practicables(): void
    {
        $internas = $this->cadena();

        foreach ($internas as $arista) {
            foreach (['source', 'target'] as $lado) {
                $this->assertTrue(
                    $arista->{$lado}->practiceItems()->exists(),
                    "La arista {$arista->source->native_code} ← {$arista->target->native_code} ".
                    "apunta a {$arista->{$lado}->native_code}, que no tiene ítems: es inerte."
                );
            }
            $this->assertSame('manual', $arista->method);
        }
    }

    /**
     * ORÁCULO: la progresión interna no tiene ciclos ni bucles sobre sí misma.
     * Un ciclo A←B←A hace que el refuerzo mande al alumno de ida y vuelta sin
     * llegar nunca a contenido más simple.
     */
    public function test_la_progresion_interna_no_tiene_ciclos(): void
    {
        $aristas = $this->cadena()
            ->map(fn ($a) => [$a->source->native_code, $a->target->native_code]);

        foreach ($aristas as [$source, $target]) {
            $this->assertNotSame($source, $target, 'una destreza no es prerrequisito de sí misma');
        }

        // Recorrido en profundidad desde cada nodo siguiendo los prerrequisitos.
        $salidas = [];
        foreach ($aristas as [$source, $target]) {
            $salidas[$source][] = $target;
        }
        $visita = function (string $nodo, array $camino) use (&$visita, $salidas) {
            $this->assertNotContains($nodo, $camino, 'ciclo: '.implode(' ← ', [...$camino, $nodo]));
            foreach ($salidas[$nodo] ?? [] as $siguiente) {
                $visita($siguiente, [...$camino, $nodo]);
            }
        };
        foreach (array_keys($salidas) as $nodo) {
            $visita($nodo, []);
        }
    }

    /**
     * El caso que importa de verdad: μ se determina por el ángulo crítico, que
     * es un plano inclinado. Quien falla dos veces el rozamiento vuelve al
     * plano inclinado, no sigue chocándose con el mismo ítem.
     */
    public function test_el_retroceso_dispara_de_verdad_con_el_grafo_sembrado(): void
    {
        $rozamiento = $this->objective('CN.F.5.1.12');

        $this->falla($rozamiento);
        $this->falla($rozamiento);

        $this->next($rozamiento)->assertOk()
            ->assertJsonPath('objective_id', $this->objective('CN.F.5.1.9')->id)
            ->assertJsonPath('reason', 'refuerzo de prerrequisito');
    }

    /** Y al dominar las lentes delgadas, lo siguiente es el aumento lateral. */
    public function test_el_avance_dispara_de_verdad_con_el_grafo_sembrado(): void
    {
        $lentes = $this->objective('CN.F.5.3.7');

        ObjectiveMastery::create([
            'user_id' => $this->ana->id, 'objective_id' => $lentes->id,
            'mastery' => 0.85, 'streak' => 4, 'attempts_count' => 4,
            'mastered_at' => now(),
        ]);

        $this->next($lentes)->assertOk()
            ->assertJsonPath('objective_id', $this->objective('CN.F.5.3.8')->id)
            ->assertJsonPath('reason', 'avance');
    }

    /** Sigue sin entrar nada a producción: las revisa un docente (regla 5). */
    public function test_las_aristas_internas_tampoco_estan_revisadas(): void
    {
        foreach ($this->cadena() as $arista) {
            $this->assertNull($arista->reviewed_at);
            $this->assertGreaterThanOrEqual(
                0.8,
                (float) $arista->confidence,
                'la reserva pedagógica va en el comentario, no en confidence: por '.
                'debajo de 0.8 la arista quedaría fuera de production() incluso '.
                'después de que un docente la firme'
            );
        }
    }

    /**
     * ORÁCULO EXACTO de la cadena, y guardia contra tests vacuos: todos los
     * demás tests iteran sobre ESTE resultado, así que si la sección del
     * seeder desapareciera no habría un solo test que pasara sin ejecutar
     * aserciones (un `foreach` sobre una colección vacía pasa siempre).
     *
     * La lista literal es deliberada: sin ella, INVERTIR una arista deja la
     * suite en verde —los oráculos estructurales (ambos extremos con ítems,
     * sin ciclos) son simétricos— y el motor mandaría al alumno que falla
     * hacia contenido MÁS difícil.
     *
     * @return Collection<int, Alignment>
     */
    private function cadena()
    {
        $internas = $this->aristasInternas();

        $this->assertEqualsCanonicalizing(
            [
                ['CN.4.3.7', 'CN.4.3.5'],
                ['CN.4.3.10', 'CN.4.3.5'],
                ['CN.F.5.1.4', 'CN.4.3.10'],
                ['CN.F.5.1.9', 'CN.F.5.1.4'],
                ['CN.F.5.1.12', 'CN.F.5.1.9'],
                ['CN.F.5.3.8', 'CN.F.5.3.7'],
            ],
            $internas->map(fn ($a) => [$a->source->native_code, $a->target->native_code])->all(),
            'la cadena de prerrequisitos intra-MINEDEC cambió: revísala a mano '.
            'contra el currículo antes de tocar este literal'
        );

        return $internas;
    }

    /**
     * INVARIANTE CURRICULAR: un prerrequisito nunca está en un grado posterior
     * al de la destreza que lo exige. Mata de golpe cualquier arista invertida,
     * también las que se añadan en el futuro.
     */
    public function test_ningun_prerrequisito_vive_en_un_grado_posterior(): void
    {
        // Subnivel y numeral del código MINEDEC: ÁREA[.F].subnivel.bloque.n.
        $orden = function (string $code): array {
            $partes = array_values(array_filter(explode('.', $code), 'is_numeric'));

            return array_map('intval', $partes);
        };

        foreach ($this->cadena() as $arista) {
            $this->assertGreaterThanOrEqual(
                $orden($arista->target->native_code),
                $orden($arista->source->native_code),
                "{$arista->source->native_code} exige {$arista->target->native_code}, ".
                'que va DESPUÉS en el currículo: la arista está invertida'
            );
        }
    }

    /**
     * REGRESIÓN (auditoría): CN.4.3.5 es el único nodo con DOS sucesores
     * (CN.4.3.7 de 8.º y CN.4.3.10 de 10.º). El desempate del selector ordenaba
     * `native_code` como cadena, y '1' < '7' hacía ganar a CN.4.3.10: al
     * dominar las fuerzas de 8.º el alumno saltaba dos grados y se saltaba la
     * destreza de su propio grado. Ahora el desempate es en orden NATURAL.
     */
    public function test_el_avance_con_dos_sucesores_no_salta_de_grado(): void
    {
        $fuerzas = $this->objective('CN.4.3.5');

        ObjectiveMastery::create([
            'user_id' => $this->ana->id, 'objective_id' => $fuerzas->id,
            'mastery' => 0.85, 'streak' => 4, 'attempts_count' => 4,
            'mastered_at' => now(),
        ]);

        $this->next($fuerzas)->assertOk()
            ->assertJsonPath('objective_id', $this->objective('CN.4.3.7')->id)
            ->assertJsonPath('reason', 'avance');
    }

    /**
     * REGRESIÓN (auditoría): cuando el selector desvía, la respuesta tiene que
     * decir a QUÉ destreza pertenece el ítem. Sin esto la página rotulaba el
     * ejercicio del prerrequisito con el código y el enunciado de la destreza
     * pedida — el alumno leía «coeficiente de rozamiento» sobre un ejercicio
     * de plano inclinado.
     */
    public function test_el_desvio_dice_a_que_destreza_pertenece_el_item(): void
    {
        $rozamiento = $this->objective('CN.F.5.1.12');
        $this->falla($rozamiento);
        $this->falla($rozamiento);

        $plano = $this->objective('CN.F.5.1.9');
        $this->next($rozamiento)->assertOk()
            ->assertJsonPath('objective_id', $plano->id)
            ->assertJsonPath('objective_code', 'CN.F.5.1.9')
            ->assertJsonPath('objective_statement', $plano->statement['es']);
    }

    /**
     * Las aristas prerequisite con AMBOS extremos en EC-MINEDEC.
     *
     * Se filtra por marco (framework_versions de EC-MINEDEC), no por el prefijo
     * del código: «empieza por CN.» es exactamente el atajo que prohíbe la
     * regla 2 de CLAUDE.md, y con el CurriculumSeeder real engancharía las CN
     * de los 13 grados.
     *
     * @return Collection<int, Alignment>
     */
    private function aristasInternas()
    {
        $versiones = FrameworkVersion::whereIn(
            'framework_id',
            Framework::where('code', 'EC-MINEDEC')->pluck('id'),
        )->pluck('id');
        $minedec = LearningObjective::whereIn('version_id', $versiones)->pluck('id');

        return Alignment::query()
            ->where('relation', 'prerequisite')
            ->whereIn('source_id', $minedec)
            ->whereIn('target_id', $minedec)
            ->with(['source', 'target'])
            ->get();
    }
}
