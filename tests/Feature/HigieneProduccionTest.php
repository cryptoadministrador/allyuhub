<?php

namespace Tests\Feature;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\PracticeItem;
use App\Models\Track;
use Database\Seeders\CurriculumSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PracticeItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PR 0 · LA HIGIENE DE PRODUCCIÓN — pequeña y urgente.
 *
 * El fallo real, tal cual está hoy desplegado: `PracticeItemSeeder` busca cada
 * código en TODAS las versiones de EC-MINEDEC y se niega si aparece más de una
 * vez. En producción `CN.F.5.1.9` aparece en 3 objetivos (el importador
 * replica por subnivel), así que `migrate --seed` ABORTA — y como
 * `DatabaseSeeder` termina llamando a `curriculo:fases-ord`, ese comando no ha
 * corrido NUNCA en producción: las fases del track ORD llevan vacías desde el
 * primer día. El error de arriba tapaba el silencio de abajo.
 *
 * El arreglo es la MISMA decisión que ya tomó el banco en #22: se ancla por
 * (VERSIÓN VIGENTE, código), y dentro de ella el código replicado por grado es
 * COBERTURA, no ambigüedad — cada grado recibe sus ítems.
 */
class HigieneProduccionTest extends TestCase
{
    use RefreshDatabase;

    /** El grafo con la FORMA de producción: dos versiones y el código replicado. */
    private function grafoComoProduccion(): FrameworkVersion
    {
        // La versión VIEJA (semilla demo): el código una sola vez.
        $this->seed(CurriculumSeeder::class);
        $fw = Framework::where('code', 'EC-MINEDEC')->firstOrFail();

        // La versión NUEVA (import oficial): física replicada en los 3 grados
        // de BGU, verificada — exactamente lo que deja `mineduc:import`.
        $vigente = FrameworkVersion::create([
            'framework_id' => $fw->id, 'label' => 'oficial-fisica',
            'valid_from' => now()->addDay(),   // más nueva que la demo
        ]);

        // Los códigos que se replican son LOS VERIFICADOS REALES de la demo —
        // los mismos que ancla el banco de física—, no una lista escrita a
        // mano: una lista propia se desalinea del seeder y el test mentiría
        // (le pasó a la primera versión de este fixture, que inventó CN.F.5.3.7
        // y el banco real usa CN.4.3.7).
        $codigos = LearningObjective::where('is_verified', true)
            ->pluck('native_code')->unique()->values();

        foreach (['g11', 'g12', 'g13'] as $i => $grado) {
            $nodo = CurNode::create([
                'version_id' => $vigente->id, 'node_type' => 'grado',
                'native_code' => $grado, 'title' => ['es' => $grado],
                'path' => "oficial.{$grado}", 'seq' => $i,
            ]);
            foreach ($codigos as $code) {
                LearningObjective::create([
                    'node_id' => $nodo->id, 'version_id' => $vigente->id,
                    'native_code' => $code, 'statement' => ['es' => "Oficial {$code}"],
                    'is_verified' => true,
                ]);
            }
        }

        return $vigente;
    }

    /**
     * EL ABORTO DE PRODUCCIÓN, REPRODUCIDO Y ARREGLADO. Con el código en 3
     * objetivos el seeder ya no se niega: ancla en la versión VIGENTE y siembra
     * el código replicado en CADA grado — replicado es cobertura (#22).
     */
    public function test_el_codigo_replicado_ya_no_aborta_y_siembra_cada_grado(): void
    {
        $vigente = $this->grafoComoProduccion();

        // Antes: RuntimeException «el código CN.F.5.1.9 es ambiguo». Ahora:
        $this->seed(PracticeItemSeeder::class);

        // Los ítems aterrizan SOLO en la versión vigente…
        $enVigente = PracticeItem::whereHas('objective',
            fn ($q) => $q->where('version_id', $vigente->id))->count();
        $enVieja = PracticeItem::whereHas('objective',
            fn ($q) => $q->where('version_id', '!=', $vigente->id))->count();

        $this->assertGreaterThan(0, $enVigente);
        $this->assertSame(0, $enVieja,
            'Un ítem aterrizó en la versión vieja: el ancla no es (versión vigente, código).');

        // …y el código replicado recibe sus ítems EN LOS TRES grados.
        $conItems = LearningObjective::where('version_id', $vigente->id)
            ->where('native_code', 'CN.F.5.1.9')
            ->whereHas('todosLosPracticeItems')->count();
        $this->assertSame(3, $conItems, 'El código replicado no llegó a los tres grados.');
    }

    /** La regla de solo-verificadas no se aflojó al arreglar la ambigüedad. */
    public function test_sigue_fallando_ruidoso_si_la_destreza_no_esta_verificada(): void
    {
        $this->seed(CurriculumSeeder::class);
        // Toda copia del código queda sin verificar: no hay dónde anclar.
        LearningObjective::where('native_code', 'CN.F.5.1.9')->update(['is_verified' => false]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/CN\.F\.5\.1\.9/');

        $this->seed(PracticeItemSeeder::class);
    }

    /**
     * LA FACTURA COMPLETA: la cadena entera de `migrate --seed` corre sobre el
     * grafo con forma de producción, y `curriculo:fases-ord` — que nunca había
     * corrido allí — deja el track ORD CON fases y con destrezas dentro.
     */
    public function test_la_cadena_entera_corre_y_ord_deja_de_estar_vacio(): void
    {
        $this->grafoComoProduccion();

        // Lo que producción no ha conseguido nunca: terminar la cadena.
        $this->seed(DatabaseSeeder::class);

        $ord = Track::where('code', 'ORD')->firstOrFail();
        $fases = $ord->phases;

        $this->assertGreaterThan(0, $fases->count(), 'ORD sigue sin fases.');
        $conDestrezas = $fases->filter(fn ($f) => $f->objectives()->count() > 0);
        $this->assertGreaterThan(0, $conDestrezas->count(),
            'Las fases de ORD existen pero siguen vacías: fases-ord no enganchó destrezas.');
    }

    /**
     * EL ORDEN DE LA CADENA, fijado: lo estructural ANTES que el contenido.
     *
     * `fases-ord` iba al final, detrás del seeder de ítems, y cuando este
     * abortó en producción las fases se quedaron vacías SIN que nadie lo viera
     * — el error de arriba tapaba el silencio de abajo. Este test rompe el
     * seeder de ítems A PROPÓSITO y exige que las fases existan igual: si
     * alguien devuelve fases-ord al final de la cadena, esto se pone rojo.
     */
    public function test_si_el_contenido_falla_las_fases_quedan_en_pie_igual(): void
    {
        $this->grafoComoProduccion();
        // Se rompe el seeder de ítems: ninguna copia verificada donde anclar.
        LearningObjective::query()->update(['is_verified' => false]);

        try {
            $this->seed(DatabaseSeeder::class);
            $this->fail('El seeder de ítems debía fallar con todo sin verificar.');
        } catch (\RuntimeException) {
            // El fallo de CONTENIDO es real y ruidoso — pero llegó DESPUÉS de
            // lo estructural.
        }

        $ord = Track::where('code', 'ORD')->firstOrFail();
        // CON destrezas, no solo fases: CurriculumSeeder ya deja fases-grado
        // VACÍAS, así que contar fases pasaba en verde con el orden invertido
        // — lo enseñó una mutación. Lo que fases-ord aporta es el enganche.
        $conDestrezas = $ord->phases->filter(fn ($f) => $f->objectives()->exists());
        $this->assertGreaterThan(0, $conDestrezas->count(),
            'El fallo de contenido volvió a silenciar a fases-ord: el orden de la cadena se invirtió.');
    }

    // ================= CS.EC: retirado a pendientes, con nota =================

    /**
     * DECISIÓN (Carlos, 2026-08-26): Educación para la Ciudadanía NO se importa
     * ahora, así que sus tres preguntas salen del banco activo — con el
     * pre-pase de #28, un área sin destrezas REVIENTA la siembra y esas tres
     * preguntas la habrían parado en producción. Viven en el fichero de
     * pendientes, enteras, esperando al importador.
     */
    public function test_cs_ec_esta_fuera_del_banco_activo_y_en_pendientes(): void
    {
        $activos = array_column(require database_path('data/banco-practica.php'), 0);
        foreach ($activos as $bloque) {
            $this->assertFalse(str_starts_with($bloque, 'CS.EC.'),
                "CS.EC sigue en el banco activo ({$bloque}) y va a parar la siembra en producción.");
        }

        // Las tres preguntas NO se perdieron: están en pendientes, completas.
        $pendientes = require database_path('data/banco-pendiente.php');
        $bloquesPendientes = array_column($pendientes, 0);
        $this->assertSame(['CS.EC.5.1', 'CS.EC.5.2', 'CS.EC.5.3'], $bloquesPendientes);
        foreach ($pendientes as $entrada) {
            $this->assertNotEmpty($entrada[2], 'Una pregunta pendiente perdió su enunciado.');
        }
    }
}
