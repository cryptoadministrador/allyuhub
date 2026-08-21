<?php

namespace Tests\Feature;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\PracticeItem;
use App\Services\Catalog\SubtreeCounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Los conteos de las tarjetas del catálogo. Se agregan por `path` porque es lo
 * único que ata un nodo con su subárbol sin recursión, y ahí hay dos trampas
 * que estos tests fijan: los prefijos que se comen a sus hermanos («bgu.g1»
 * contra «bgu.g11») y los paths que se repiten entre versiones del marco.
 */
class SubtreeCountsTest extends TestCase
{
    use RefreshDatabase;

    private Framework $fw;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fw = Framework::create([
            'code' => 'EC-MINEDEC', 'authority' => 'MINEDEC', 'kind' => 'national',
            'country' => 'EC', 'label' => ['es' => 'Currículo Nacional'],
        ]);
    }

    private function nodo(FrameworkVersion $v, string $path, ?CurNode $padre = null): CurNode
    {
        return CurNode::create([
            'version_id' => $v->id, 'parent_id' => $padre?->id,
            'node_type' => 'grado', 'title' => ['es' => $path], 'path' => $path,
        ]);
    }

    private function destreza(CurNode $nodo, string $code, bool $verificada = false, bool $conItems = false): void
    {
        $o = LearningObjective::create([
            'node_id' => $nodo->id, 'version_id' => $nodo->version_id,
            'native_code' => $code, 'statement' => ['es' => 'x'], 'is_verified' => $verificada,
        ]);

        if ($conItems) {
            PracticeItem::create([
                'objective_id' => $o->id, 'statement' => ['es' => 'm={m}'],
                'params' => ['m' => ['min' => 1, 'max' => 5, 'step' => 1]],
                'solution_expr' => 'm', 'tolerance' => 0.02, 'tolerance_kind' => 'rel',
                // Firmado: este fixture prueba el MOTOR, y un ítem sin
                // revisar no llega al motor (ver DominioYFirmaTest).
                'reviewed_at' => now(),
            ]);
        }
    }

    /** Un nodo suma lo suyo Y lo de sus descendientes, sin contarse dos veces. */
    public function test_suma_el_subarbol_entero_sin_duplicar(): void
    {
        $v = FrameworkVersion::create(['framework_id' => $this->fw->id, 'label' => '2016']);
        $grado = $this->nodo($v, 'bgu.g1');
        $bloque = $this->nodo($v, 'bgu.g1.b1', $grado);

        $this->destreza($grado, 'A.1', verificada: true);                       // propia
        $this->destreza($bloque, 'A.2', verificada: true, conItems: true);      // descendiente
        $this->destreza($bloque, 'A.3');

        $this->assertSame(
            ['destrezas' => 3, 'verificadas' => 2, 'practicables' => 1],
            SubtreeCounts::para([$grado])[$grado->id],
        );
        $this->assertSame(
            ['destrezas' => 2, 'verificadas' => 1, 'practicables' => 1],
            SubtreeCounts::para([$bloque])[$bloque->id],
        );
    }

    /**
     * «bgu.g1» NO es ancestro de «bgu.g11»: son hermanos. El punto que cierra
     * el prefijo es lo único que lo impide, y sin él 1.º de Básica se comería
     * las destrezas de 11.º.
     */
    public function test_un_path_no_se_traga_a_su_hermano_de_prefijo_mas_largo(): void
    {
        $v = FrameworkVersion::create(['framework_id' => $this->fw->id, 'label' => '2016']);
        $g1 = $this->nodo($v, 'bgu.g1');
        $g11 = $this->nodo($v, 'bgu.g11');

        $this->destreza($g1, 'A.1');
        foreach (['B.1', 'B.2', 'B.3'] as $code) {
            $this->destreza($g11, $code);
        }

        $cuentas = SubtreeCounts::para([$g1, $g11]);
        $this->assertSame(1, $cuentas[$g1->id]['destrezas']);
        $this->assertSame(3, $cuentas[$g11->id]['destrezas']);
    }

    /**
     * REGRESIÓN (auditoría): dos versiones del mismo marco reutilizan los
     * paths. Agregando solo por `path`, cada tarjeta sumaba las destrezas de
     * TODAS las versiones — «1.º BGU: 4 destrezas» cuando la versión vigente
     * tiene 3 y la vieja 1. No disparaba porque el catálogo pinta una sola
     * versión, pero está a una colección mixta de distancia.
     */
    public function test_dos_versiones_con_el_mismo_path_no_se_suman_entre_si(): void
    {
        $vieja = FrameworkVersion::create(['framework_id' => $this->fw->id, 'label' => '2016']);
        $nueva = FrameworkVersion::create(['framework_id' => $this->fw->id, 'label' => '2016+2023']);

        $enVieja = $this->nodo($vieja, 'bgu.g1');
        $enNueva = $this->nodo($nueva, 'bgu.g1');

        $this->destreza($enVieja, 'A.1');
        foreach (['A.1', 'A.2', 'A.3'] as $code) {
            $this->destreza($enNueva, $code);
        }

        $cuentas = SubtreeCounts::para([$enVieja, $enNueva]);
        $this->assertSame(1, $cuentas[$enVieja->id]['destrezas']);
        $this->assertSame(3, $cuentas[$enNueva->id]['destrezas']);
    }

    /** Sin nodos no consulta nada, y con muchos sigue costando dos consultas. */
    public function test_cuesta_dos_consultas_pase_lo_que_pase(): void
    {
        $v = FrameworkVersion::create(['framework_id' => $this->fw->id, 'label' => '2016']);
        $nodos = collect(range(1, 20))->map(function (int $i) use ($v) {
            $n = $this->nodo($v, "bgu.g{$i}");
            $this->destreza($n, "C.{$i}");

            return $n;
        });

        $this->assertSame([], SubtreeCounts::para([]));

        $consultas = 0;
        DB::listen(function () use (&$consultas) {
            $consultas++;
        });
        SubtreeCounts::para($nodos);

        $this->assertSame(2, $consultas);
    }
}
