<?php

namespace Tests\Feature;

use App\Console\Commands\ImportMineduc;
use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

class ImportMineducTest extends TestCase
{
    use RefreshDatabase;

    private FrameworkVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        $fw = Framework::create([
            'code' => 'EC-MINEDEC', 'authority' => 'MINEDEC', 'kind' => 'national',
            'country' => 'EC', 'label' => ['es' => 'Currículo Nacional'],
        ]);
        $this->version = FrameworkVersion::create(['framework_id' => $fw->id, 'label' => 'test']);

        // Grafo mínimo: g11 → Física → bloque 1 (destino de CN.F.5.x.x).
        foreach (['g11', 'g12', 'g13'] as $i => $gid) {
            $grade = CurNode::create([
                'version_id' => $this->version->id, 'node_type' => 'grado',
                'native_code' => $gid, 'title' => ['es' => 'BGU '.($i + 1)],
                'path' => 'bgu.'.$gid, 'seq' => $i + 11,
            ]);
            $subj = CurNode::create([
                'version_id' => $this->version->id, 'parent_id' => $grade->id,
                'node_type' => 'asignatura', 'native_code' => 'CN.F',
                'title' => ['es' => 'Física'], 'path' => 'bgu.'.$gid.'.cn_f',
            ]);
            CurNode::create([
                'version_id' => $this->version->id, 'parent_id' => $subj->id,
                'node_type' => 'bloque', 'title' => ['es' => 'Movimiento y fuerza'],
                'seq' => 1, 'path' => 'bgu.'.$gid.'.cn_f.b1',
            ]);
        }
    }

    protected function tearDown(): void
    {
        // El fixture no debe quedar como residuo en storage/framework.
        @unlink(storage_path('framework/test-import.txt'));

        parent::tearDown();
    }

    private function fixture(string $content): string
    {
        $path = storage_path('framework/test-import.txt');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $content);

        return $path;
    }

    public function test_importa_y_replica_por_subnivel(): void
    {
        $file = $this->fixture(
            'CN.F.5.1.12. Determinar experimentalmente el coeficiente de rozamiento '.
            'entre dos superficies, a partir del ángulo crítico. Ref. CE.CN.5.3'
        );

        $this->artisan('mineduc:import', ['file' => $file])->assertSuccessful();

        // Subnivel 5 → replicada en los 3 cursos de BGU.
        $this->assertSame(3, LearningObjective::where('native_code', 'CN.F.5.1.12')->count());
        // La referencia al criterio de evaluación se limpia del enunciado.
        $this->assertStringNotContainsString(
            'Ref.',
            LearningObjective::where('native_code', 'CN.F.5.1.12')->first()->statement['es'],
        );
        // Sin --official queda sin verificar.
        $this->assertFalse(LearningObjective::where('native_code', 'CN.F.5.1.12')->first()->is_verified);
    }

    public function test_official_verifica_y_registra_sha(): void
    {
        $file = $this->fixture('CN.F.5.1.9. Explicar el movimiento de un cuerpo sobre un plano inclinado, identificando las fuerzas que actúan.');

        $this->artisan('mineduc:import', ['file' => $file, '--official' => true])->assertSuccessful();

        $this->assertTrue(LearningObjective::where('native_code', 'CN.F.5.1.9')->first()->is_verified);
        $this->assertSame(hash_file('sha256', $file), $this->version->fresh()->source_sha256);
    }

    public function test_no_official_no_degrada_verificadas(): void
    {
        $bloque = CurNode::where('node_type', 'bloque')->first();
        LearningObjective::create([
            'node_id' => $bloque->id, 'version_id' => $this->version->id,
            'native_code' => 'CN.F.5.1.9',
            'statement' => ['es' => 'Enunciado oficial ya verificado.'],
            'is_verified' => true,
        ]);

        $file = $this->fixture('CN.F.5.1.9. Enunciado distinto proveniente de un borrador cualquiera de prueba.');
        $this->artisan('mineduc:import', ['file' => $file])->assertSuccessful();

        $obj = LearningObjective::where('native_code', 'CN.F.5.1.9')
            ->where('statement->es', 'Enunciado oficial ya verificado.')->first();
        $this->assertNotNull($obj, 'El enunciado verificado no debe ser pisado por un import no oficial');
        $this->assertTrue($obj->is_verified);
    }

    public function test_rechaza_archivo_sin_codigos(): void
    {
        $file = $this->fixture(str_repeat('Texto cualquiera sin códigos curriculares. ', 30));
        $this->artisan('mineduc:import', ['file' => $file])->assertFailed();
    }

    // ---------- El oráculo de calidad en el comando (misión ANTY, frente 2) ----------

    /** Un documento limpio reporta su calidad y entra sin pelear. */
    public function test_reporta_el_porcentaje_de_calidad(): void
    {
        $file = $this->fixture(
            'CN.F.5.1.9. Explicar el movimiento de un cuerpo sobre un plano inclinado, '
            ."identificando las fuerzas que actúan sobre él.\n\n"
            .'CN.F.5.1.12. Determinar experimentalmente el coeficiente de rozamiento entre dos '
            .'superficies, a partir del ángulo crítico.',
        );

        $this->artisan('mineduc:import', ['file' => $file, '--dry-run' => true])
            ->expectsOutputToContain('Calidad de la extracción: 100 %')
            ->assertSuccessful();
    }

    /**
     * EL ORÁCULO QUE PROTEGE AL COLEGIO: si la extracción viene sucia,
     * --official no importa nada. Mejor no importar que importar basura.
     */
    public function test_official_aborta_si_la_calidad_baja_del_umbral(): void
    {
        // Una buena y tres podridas (25 % de validez, muy por debajo del 95 %).
        $file = $this->fixture(
            'CN.F.5.1.9. Explicar el movimiento de un cuerpo sobre un plano inclinado, '
            ."identificando las fuerzas que actúan.\n\n"
            ."CN.F.5.1.4. Aplicar las leyes de Newton al análisis de sistemas y expli- car sus efectos\n\n"
            ."CN.F.5.3.7. Describir la formación de imágenes en lentes delgadas convergentes y\n\n"
            .'CN.F.5.3.8. Determinar el aumento lateral de una imagen y la posicionCN del objeto',
        );

        $this->artisan('mineduc:import', ['file' => $file, '--official' => true])
            ->expectsOutputToContain('ABORTADO')
            ->assertFailed();

        // Y no escribió NADA: ni la destreza buena.
        $this->assertSame(0, LearningObjective::where('native_code', 'CN.F.5.1.9')->count());
        $this->assertNull($this->version->fresh()->source_sha256);
    }

    /** Por encima del umbral, las pocas malas se omiten en vez de verificarse. */
    public function test_official_omite_las_destrezas_que_no_pasan_el_oraculo(): void
    {
        $buenas = collect(range(1, 20))->map(fn ($i) => sprintf(
            'CN.F.5.1.%d. Explicar el fenómeno número %d con sus causas y sus efectos observables '
            .'en situaciones cotidianas del entorno.', $i, $i,
        ))->implode("\n\n");

        $file = $this->fixture($buenas."\n\nCN.F.5.2.1. Describir el fenómeno y la posicionCN del objeto");

        $this->artisan('mineduc:import', ['file' => $file, '--official' => true])
            ->expectsOutputToContain('Se omiten 1 destreza(s)')
            ->assertSuccessful();

        $this->assertSame(0, LearningObjective::where('native_code', 'CN.F.5.2.1')->count());
        $this->assertGreaterThan(0, LearningObjective::where('native_code', 'CN.F.5.1.1')->count());
    }

    public function test_el_dry_run_jamas_escribe(): void
    {
        $antes = LearningObjective::count();

        $file = $this->fixture(
            'CN.F.5.1.9. Explicar el movimiento de un cuerpo sobre un plano inclinado, '
            .'identificando las fuerzas que actúan sobre él.',
        );
        $this->artisan('mineduc:import', ['file' => $file, '--dry-run' => true])->assertSuccessful();
        $this->artisan('mineduc:import', ['file' => $file, '--dry-run' => true, '--official' => true])
            ->assertSuccessful();

        $this->assertSame($antes, LearningObjective::count());
        $this->assertNull($this->version->fresh()->source_sha256);
    }

    /**
     * EL ÚNICO TEST QUE TOCA EL PDF DE VERDAD. Los fixtures son .txt y por eso
     * ninguno veía el modo de extracción: quitar `-raw` de pdfToText dejaba la
     * suite en verde mientras el importador entrelazaba columnas (auditoría).
     *
     * Se salta si no está el PDF (está en .gitignore, así que el CI no lo
     * tiene) — en la máquina de quien vaya a importar, corre.
     */
    public function test_el_pdf_oficial_se_extrae_con_calidad_suficiente(): void
    {
        $pdf = base_path('storage/curriculo/CCNN_COMPLETO.pdf');
        if (! is_file($pdf)) {
            $this->markTestSkipped('Sin storage/curriculo/CCNN_COMPLETO.pdf (ver storage/curriculo/README.md).');
        }
        if ((new ExecutableFinder)->find('pdftotext') === null) {
            $this->markTestSkipped('Sin pdftotext (poppler-utils) en el PATH.');
        }

        $codigo = Artisan::call('mineduc:import', ['file' => $pdf]);
        $salida = Artisan::output();
        $this->assertSame(0, $codigo, $salida);

        preg_match('/Calidad de la extracción: ([\d.]+) %/u', $salida, $m);
        $this->assertNotEmpty($m, 'El comando no reportó la calidad');
        $this->assertGreaterThanOrEqual(
            ImportMineduc::UMBRAL_CALIDAD, (float) $m[1],
            'La extracción del PDF oficial ya no alcanza el umbral de --official',
        );

        // El % NO basta: con el modo de extracción equivocado la calidad seguía
        // por encima del umbral y aun así el enunciado era falso. Se comprueba
        // el CONTENIDO de una destreza concreta contra el texto oficial.
        $ley = LearningObjective::where('native_code', 'CN.F.5.1.17')->first();
        $this->assertNotNull($ley, 'No se importó CN.F.5.1.17');
        $this->assertStringContainsString(
            'aceleración y fuerza que actúan sobre un objeto y su masa',
            $ley->statement['es'],
        );
        $this->assertStringNotContainsString('relaambiente', $ley->statement['es']);

        // Y una destreza donde el modo de extracción se nota de verdad: por
        // defecto, pdftotext le encaja texto de la columna de objetivos.
        $deformacion = LearningObjective::where('native_code', 'CN.F.5.1.30')->first();
        $this->assertNotNull($deformacion);
        $this->assertStringContainsString(
            'fuerzas de compresión o de tracción que causan la deformación',
            $deformacion->statement['es'],
        );
        $this->assertStringNotContainsString('su lugar en el Universo', $deformacion->statement['es']);
    }

    /**
     * REGRESIÓN del desempate, por la RUTA REAL (el comando, no una copia de
     * la comparación dentro del test). Fixture con las DOS ocurrencias reales
     * del PDF para el mismo código: la limpia de la tabla de destrezas y la
     * entrelazada con la columna de objetivos. Las dos pasan el oráculo, así
     * que solo el criterio «entre válidas, la más larga» las distingue.
     */
    public function test_entre_dos_ocurrencias_reales_se_queda_la_completa(): void
    {
        $fixture = dirname(__DIR__).'/fixtures/curriculo/ccnn-codigo-duplicado.txt';

        $this->artisan('mineduc:import', ['file' => $fixture])->assertSuccessful();

        $ley = LearningObjective::where('native_code', 'CN.F.5.1.17')->first();
        $this->assertNotNull($ley);
        $this->assertStringContainsString(
            'aceleración y fuerza que actúan sobre un objeto y su masa',
            $ley->statement['es'],
        );
        $this->assertStringNotContainsString('relaambiente', $ley->statement['es']);
        $this->assertStringNotContainsString('curiosidad por explorar', $ley->statement['es']);
    }

    /**
     * El desempate, aislado: cuando la MISMA destreza aparece dos veces y AMBAS
     * versiones pasan el oráculo, se queda la completa. Ocurre cuando la matriz
     * de criterios solo trae la primera frase y el mobiliario de página se lleva
     * el resto — el recorte la deja terminada en punto, o sea válida y CORTA.
     */
    public function test_entre_dos_variantes_validas_se_queda_la_completa(): void
    {
        $file = $this->fixture(
            'CN.F.5.1.9. Explicar el movimiento de un cuerpo sobre un plano inclinado. '
            ."156 Educación General Básica Superior CIENCIAS NATURALES\n\n"
            .'CN.F.5.1.9. Explicar el movimiento de un cuerpo sobre un plano inclinado. '
            .'Descomponer el peso en sus componentes y calcular la fuerza resultante.',
        );

        $this->artisan('mineduc:import', ['file' => $file])->assertSuccessful();

        $ley = LearningObjective::where('native_code', 'CN.F.5.1.9')->first();
        $this->assertNotNull($ley);
        $this->assertStringContainsString('Descomponer el peso en sus componentes', $ley->statement['es']);
        $this->assertStringNotContainsString('Educación General Básica', $ley->statement['es']);
    }

    public function test_reimportar_es_idempotente(): void
    {
        $file = $this->fixture(
            'CN.F.5.1.9. Explicar el movimiento de un cuerpo sobre un plano inclinado, '
            ."identificando las fuerzas que actúan sobre él.\n\n"
            .'CN.F.5.1.12. Determinar experimentalmente el coeficiente de rozamiento entre dos '
            .'superficies, a partir del ángulo crítico.',
        );

        $this->artisan('mineduc:import', ['file' => $file, '--official' => true])->assertSuccessful();
        $primera = LearningObjective::count();

        $this->artisan('mineduc:import', ['file' => $file, '--official' => true])->assertSuccessful();

        $this->assertSame($primera, LearningObjective::count(), 'Re-importar duplicó destrezas');
        // Una por grado del subnivel 5 (g11, g12, g13) y por código.
        $this->assertSame(3, LearningObjective::where('native_code', 'CN.F.5.1.9')->count());
    }
}
