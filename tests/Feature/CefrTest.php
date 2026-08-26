<?php

namespace Tests\Feature;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Services\Lesson\DestinosDeBloque;
use Database\Seeders\CefrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * EL MCER ENTRA AL GRAFO — y entra mejor que los marcos de pago.
 *
 * Los marcos CAIE/IB entraron con `is_verified=false` porque sus enunciados
 * eran paráfrasis de documentos con copyright. Los descriptores del MCER son
 * PÚBLICOS Y CITABLES (Consejo de Europa), así que entran VERIFICADOS y con su
 * `source_url` — y eso se nota en los datos, no en un comentario.
 *
 * La lengua NO es parte del código: `A1.IO.1` es el mismo descriptor en
 * italiano y en alemán — es lo que hace que los cuatro cursos sean el mismo
 * curso. La lengua es un atributo del CONTENIDO que aterriza (columna `lengua`
 * en ítems y recursos), no del marco.
 */
class CefrTest extends TestCase
{
    use RefreshDatabase;

    // ================= ORÁCULO 2 — verificado y citado =================

    public function test_el_mcer_entra_verificado_y_citado(): void
    {
        $this->seed(CefrSeeder::class);

        $fw = Framework::where('code', 'CEFR')->firstOrFail();
        $version = FrameworkVersion::where('framework_id', $fw->id)->firstOrFail();
        $this->assertNotNull($version->source_url, 'La versión del marco cita su fuente.');

        $descriptores = LearningObjective::where('version_id', $version->id)->get();
        $this->assertGreaterThanOrEqual(10, $descriptores->count());

        foreach ($descriptores as $d) {
            // TODOS, no una muestra: verificado Y con fuente citable, que es la
            // diferencia con CAIE/IB y la razón de que se pueda.
            $this->assertTrue((bool) $d->is_verified,
                "{$d->native_code} entró sin verificar y el MCER es público.");
            $this->assertNotEmpty($d->attrs['source_url'] ?? null,
                "{$d->native_code} entró sin cita.");
            $this->assertStringStartsWith('Puedo', $d->statement['es'],
                "{$d->native_code} no es un descriptor «Puedo…».");
        }

        // Las cinco destrezas comunicativas de A1, colgadas del nivel.
        $areas = CurNode::where('version_id', $version->id)
            ->where('node_type', 'area')->pluck('native_code')->sort()->values()->all();
        $this->assertSame(['A1.CE', 'A1.CO', 'A1.EE', 'A1.IO', 'A1.PO'], $areas);
    }

    public function test_un_descriptor_sin_source_url_no_entra(): void
    {
        // El fichero de datos con un descriptor sin cita: el seeder revienta y
        // NO deja nada a medias (transacción).
        $ruta = sys_get_temp_dir().'/cefr-sin-cita-'.getmypid().'.php';
        file_put_contents($ruta, '<?php return '.var_export([
            'framework' => ['code' => 'CEFR-X', 'authority' => 'Consejo de Europa',
                'kind' => 'international', 'label' => ['es' => 'MCER de prueba']],
            'version' => ['label' => 'prueba', 'source_url' => 'https://www.coe.int/x'],
            'areas' => [
                ['code' => 'A1.PO', 'titulo' => ['es' => 'Producción oral'], 'descriptores' => [
                    ['code' => 'A1.PO.1', 'statement' => ['es' => 'Puedo saludar.']],   // SIN source_url
                ]],
            ],
        ], true).';');

        try {
            (new CefrSeeder)->sembrarDesde($ruta);
            $this->fail('Entró un descriptor sin cita.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('A1.PO.1', $e->getMessage());
        } finally {
            @unlink($ruta);
        }

        $this->assertSame(0, LearningObjective::count(), 'La transacción dejó medio marco sembrado.');
    }

    public function test_resembrar_el_mcer_es_idempotente(): void
    {
        $this->seed(CefrSeeder::class);
        $antes = [
            LearningObjective::count(),
            LearningObjective::orderBy('native_code')->pluck('id')->all(),
        ];

        $this->seed(CefrSeeder::class);

        $this->assertSame($antes, [
            LearningObjective::count(),
            LearningObjective::orderBy('native_code')->pluck('id')->all(),
        ], 'Re-sembrar duplicó o re-creó descriptores.');
    }

    // ========== ORÁCULO 1 — la errata de área revienta; el hueco avisa ==========

    /**
     * LO QUE HABRÍA CAZADO LA ERRATA DE PRODUCCIÓN. El banco pedía `CS.FL.5.1`
     * y el grafo tiene `CS.F.5.1`: dos letras, y Filosofía entera —159
     * destrezas— sin ejercicios, reportado como un hueco más entre «o el área
     * no está importada, o no está verificada». Esa disyuntiva con «o» es la
     * que dejó pasar la errata — y la diferencia ES computable.
     */
    public function test_un_prefijo_de_area_inexistente_revienta_y_un_bloque_hueco_avisa(): void
    {
        $fw = Framework::create([
            'code' => 'EC-MINEDEC', 'authority' => 'MINEDEC', 'kind' => 'national',
            'country' => 'EC', 'label' => ['es' => 'Currículo Nacional'],
        ]);
        $version = FrameworkVersion::create(['framework_id' => $fw->id, 'label' => '2016+2023']);
        $nodo = CurNode::create([
            'version_id' => $version->id, 'node_type' => 'grado',
            'native_code' => 'g11', 'title' => ['es' => '1.º BGU'], 'path' => 'bgu.g11',
        ]);
        // El área CS.F existe (con una destreza VERIFICADA)…
        LearningObjective::create([
            'node_id' => $nodo->id, 'version_id' => $version->id,
            'native_code' => 'CS.F.5.1.1', 'statement' => ['es' => 'Filosofía.'],
            'is_verified' => true,
        ]);
        // …y el área CN.Q existe SOLO SIN VERIFICAR: sigue siendo un área.
        LearningObjective::create([
            'node_id' => $nodo->id, 'version_id' => $version->id,
            'native_code' => 'CN.Q.5.1.1', 'statement' => ['es' => 'Química.'],
            'is_verified' => false,
        ]);

        $versiones = collect([$version->id]);

        // La errata: NINGUNA destreza empieza por CS.FL → el área no existe.
        $this->assertFalse(DestinosDeBloque::areaExiste('CS.FL.5.1', $versiones));

        // Las dos mitades del sí: el área con verificadas y el área que existe
        // solo sin verificar (CS.EC en producción) — hueco, no errata. Sin esta
        // mitad, un validador que dice que no a todo pasa el test.
        $this->assertTrue(DestinosDeBloque::areaExiste('CS.F.5.9', $versiones));
        $this->assertTrue(DestinosDeBloque::areaExiste('CN.Q.5.4', $versiones));
    }

    /** Y la siembra de MINEDEC lo aplica: errata → falla; hueco → sigue y avisa. */
    public function test_la_siembra_revienta_con_la_errata_y_avisa_con_el_hueco(): void
    {
        $fw = Framework::create([
            'code' => 'EC-MINEDEC', 'authority' => 'MINEDEC', 'kind' => 'national',
            'country' => 'EC', 'label' => ['es' => 'Currículo Nacional'],
        ]);
        $version = FrameworkVersion::create(['framework_id' => $fw->id, 'label' => '2016+2023']);
        $nodo = CurNode::create([
            'version_id' => $version->id, 'node_type' => 'grado',
            'native_code' => 'g8', 'title' => ['es' => '8.º'], 'path' => 'egb.g8',
        ]);
        LearningObjective::create([
            'node_id' => $nodo->id, 'version_id' => $version->id,
            'native_code' => 'M.4.1.1', 'statement' => ['es' => 'x'], 'is_verified' => true,
        ]);

        $banco = fn (string $bloque) => [[$bloque, 'numeric', ['es' => 'Suma {a}'],
            ['a' => ['const' => 1]], 'a', 0.01, 'abs', null]];
        $ruta = sys_get_temp_dir().'/banco-errata-'.getmypid().'.php';

        // La ERRATA (área M.X no existe): el comando FALLA nombrándola.
        file_put_contents($ruta, '<?php return '.var_export($banco('M.X.1'), true).';');
        $this->artisan('practica:sembrar', ['--banco' => $ruta])
            ->expectsOutputToContain('M.X')
            ->assertFailed();

        // El HUECO (área M existe, bloque 9 no): el comando TERMINA y avisa.
        file_put_contents($ruta, '<?php return '.var_export($banco('M.4.9'), true).';');
        $this->artisan('practica:sembrar', ['--banco' => $ruta])
            ->expectsOutputToContain('sin destreza donde aterrizar')
            ->assertSuccessful();

        @unlink($ruta);
    }

    /** La errata real, corregida: el banco de MINEDEC ya no pide CS.FL. */
    public function test_el_banco_real_ya_no_contiene_el_prefijo_errado(): void
    {
        $bloques = array_column(require database_path('data/banco-practica.php'), 0);

        foreach ($bloques as $bloque) {
            $this->assertFalse(str_starts_with($bloque, 'CS.FL.'),
                "La errata CS.FL sigue en el banco ({$bloque}): Filosofía entera sin ejercicios.");
        }

        // Y las tres preguntas de Filosofía apuntan a donde el grafo vive.
        $this->assertContains('CS.F.5.1', $bloques);
    }
}
