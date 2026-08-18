<?php

namespace Tests\Feature;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FRENTE 0 — la identidad visual del currículo (icono y color por asignatura)
 * lleva años en database/data/curriculo-semilla.json sin que nadie la guarde.
 *
 * El comando es QUIRÚRGICO: en producción ya está encima el currículo REAL
 * importado, así que solo escribe attrs de nodos asignatura EXISTENTES. Ni
 * crea nodos, ni roza una sola destreza.
 */
class CurriculumStylesTest extends TestCase
{
    use RefreshDatabase;

    private FrameworkVersion $version;

    private CurNode $fisica;

    private CurNode $matematica;

    protected function setUp(): void
    {
        parent::setUp();

        $fw = Framework::create([
            'code' => 'EC-MINEDEC', 'authority' => 'MINEDEC', 'kind' => 'national',
            'country' => 'EC', 'label' => ['es' => 'Currículo Nacional'],
        ]);
        $this->version = FrameworkVersion::create(['framework_id' => $fw->id, 'label' => '2016+2023']);

        $grado = CurNode::create([
            'version_id' => $this->version->id, 'node_type' => 'grado',
            'native_code' => 'g11', 'title' => ['es' => '1.º BGU'], 'path' => 'bgu.g11',
            'attrs' => ['corto' => '1.º BGU'],
        ]);
        $this->fisica = CurNode::create([
            'version_id' => $this->version->id, 'parent_id' => $grado->id,
            'node_type' => 'asignatura', 'native_code' => 'CN.F',
            'title' => ['es' => 'Física'], 'path' => 'bgu.g11.cn_f',
            'attrs' => ['area' => 'Ciencias Naturales', 'horas' => 4],
        ]);
        $this->matematica = CurNode::create([
            'version_id' => $this->version->id, 'parent_id' => $grado->id,
            'node_type' => 'asignatura', 'native_code' => 'M',
            'title' => ['es' => 'Matemática'], 'path' => 'bgu.g11.m',
            'attrs' => ['area' => 'Matemática'],
        ]);

        // Una destreza REAL importada, con su trazabilidad: es intocable.
        LearningObjective::create([
            'node_id' => $this->fisica->id, 'version_id' => $this->version->id,
            'native_code' => 'CN.F.5.1.9', 'statement' => ['es' => 'Plano inclinado.'],
            'is_verified' => true,
            'attrs' => ['imported_from' => 'CCNN_COMPLETO.pdf'],
        ]);
    }

    public function test_escribe_icono_y_color_en_las_asignaturas(): void
    {
        $this->artisan('curriculo:estilos')->assertSuccessful();

        $fisica = $this->fisica->fresh();
        $this->assertSame('#3aa675', $fisica->attrs['color']);
        $this->assertNotEmpty($fisica->attrs['icon']);
        // Y no pisa lo que ya había en attrs.
        $this->assertSame('Ciencias Naturales', $fisica->attrs['area']);
        $this->assertSame(4, $fisica->attrs['horas']);

        $this->assertSame('#4a86e8', $this->matematica->fresh()->attrs['color']);
    }

    /** ORÁCULO 6: ni una fila de learning_objectives se mueve. */
    public function test_no_toca_ni_una_destreza(): void
    {
        $antes = LearningObjective::orderBy('id')->get(['id', 'native_code', 'statement', 'is_verified', 'attrs']);
        $cuantas = LearningObjective::count();

        $this->artisan('curriculo:estilos')->assertSuccessful();

        $despues = LearningObjective::orderBy('id')->get(['id', 'native_code', 'statement', 'is_verified', 'attrs']);
        $this->assertSame($cuantas, LearningObjective::count());
        $this->assertEquals($antes->toArray(), $despues->toArray(), 'El comando modificó destrezas');
    }

    /** No crea nodos: si una asignatura del JSON no está en el grafo, se salta. */
    public function test_no_crea_nodos_nuevos(): void
    {
        $antes = CurNode::count();

        $this->artisan('curriculo:estilos')->assertSuccessful();

        $this->assertSame($antes, CurNode::count());
        // El JSON trae Lengua y Literatura, que aquí no existe: no se inventa.
        $this->assertSame(0, CurNode::where('native_code', 'LL')->count());
    }

    /**
     * MUTACIÓN SUPERVIVIENTE (bucle B): quitar el filtro por `node_type` no
     * ponía nada rojo. Y sí importa: los códigos de asignatura del JSON —«CN»,
     * «CS», «M»— son EXACTAMENTE los que llevan los nodos de ÁREA del currículo
     * real, así que sin el filtro el comando pintaría también las áreas y el
     * catálogo heredaría acentos por donde no debe.
     */
    public function test_solo_pinta_asignaturas_aunque_otro_nodo_repita_el_codigo(): void
    {
        $area = CurNode::create([
            'version_id' => $this->version->id, 'node_type' => 'area',
            'native_code' => 'CN', 'title' => ['es' => 'Ciencias Naturales'],
            'path' => 'bgu.area_cn', 'attrs' => ['nota' => 'agrupador'],
        ]);
        $bloque = CurNode::create([
            'version_id' => $this->version->id, 'parent_id' => $this->fisica->id,
            'node_type' => 'bloque', 'native_code' => 'M',
            'title' => ['es' => 'Movimiento'], 'path' => 'bgu.g11.cn_f.b2',
        ]);

        $this->artisan('curriculo:estilos')->assertSuccessful();

        $this->assertArrayNotHasKey('color', $area->fresh()->attrs ?? []);
        $this->assertArrayNotHasKey('icon', $area->fresh()->attrs ?? []);
        $this->assertSame('agrupador', $area->fresh()->attrs['nota']);
        $this->assertArrayNotHasKey('color', $bloque->fresh()->attrs ?? []);

        // Y la asignatura de verdad sí se pinta.
        $this->assertSame('#3aa675', $this->fisica->fresh()->attrs['color']);
    }

    public function test_es_idempotente(): void
    {
        $this->artisan('curriculo:estilos')->assertSuccessful();
        $primera = $this->fisica->fresh()->attrs;
        $tocado = $this->fisica->fresh()->updated_at;

        $this->artisan('curriculo:estilos')->assertSuccessful();

        $this->assertSame($primera, $this->fisica->fresh()->attrs);
        // Sin cambios reales no se reescribe la fila.
        $this->assertEquals($tocado, $this->fisica->fresh()->updated_at);
    }

    /** Los grados también llevan lo suyo: etiqueta corta y edad para las tarjetas. */
    public function test_el_grado_conserva_su_etiqueta_corta(): void
    {
        $this->artisan('curriculo:estilos')->assertSuccessful();

        $grado = CurNode::where('native_code', 'g11')->firstOrFail();
        $this->assertSame('1.º BGU', $grado->attrs['corto']);
    }

    /** Todos los colores del JSON son hex de 6 dígitos: si no, la UI los pintaría mal. */
    public function test_todos_los_colores_del_json_son_hex_validos(): void
    {
        $json = json_decode(file_get_contents(database_path('data/curriculo-semilla.json')), true);

        foreach ($json['grados'] as $g) {
            foreach ($g['asignaturas'] as $a) {
                $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $a['color'],
                    "Color inválido en {$a['codigo']}");
                $this->assertNotSame('', trim($a['icon']), "Sin icono en {$a['codigo']}");
            }
        }
    }
}
