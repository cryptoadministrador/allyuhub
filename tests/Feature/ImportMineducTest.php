<?php

namespace Tests\Feature;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
