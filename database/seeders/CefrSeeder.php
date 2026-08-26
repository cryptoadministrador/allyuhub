<?php

namespace Database\Seeders;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * El MCER entra al grafo — y entra mejor que los marcos de pago.
 *
 * CAIE e IB entraron con `is_verified = false` porque sus enunciados eran
 * paráfrasis de documentos con copyright. Los descriptores del MCER son
 * públicos y citables (Consejo de Europa), así que entran VERIFICADOS y con
 * `source_url` en cada uno — y el seeder lo EXIGE: un descriptor sin cita no
 * entra, y como todo va en una transacción, tampoco deja medio marco detrás.
 *
 * Estructura: CEFR → nivel (A1) → área (A1.CO, A1.CE, A1.IO, A1.PO, A1.EE) →
 * descriptores «Puedo…». El nivel se DERIVA del código de área (el prefijo
 * hasta el primer punto), no se declara dos veces.
 */
class CefrSeeder extends Seeder
{
    public function run(): void
    {
        $this->sembrarDesde(database_path('data/cefr-a1.php'));
    }

    public function sembrarDesde(string $ruta): void
    {
        $data = require $ruta;
        $sha256 = hash_file('sha256', $ruta);

        // La validación ANTES de escribir nada: un descriptor sin cita
        // revienta nombrándose, y la base ni se toca.
        foreach ($data['areas'] as $area) {
            foreach ($area['descriptores'] as $d) {
                if (trim((string) ($d['source_url'] ?? '')) === '') {
                    throw new InvalidArgumentException(
                        "El descriptor «{$d['code']}» no cita su fuente (source_url). ".
                        'El MCER es público y citable: la cita es lo que permite que entre '.
                        'verificado, al revés que las paráfrasis de CAIE/IB.',
                    );
                }
            }
        }

        DB::transaction(function () use ($data, $sha256) {
            $fw = Framework::firstOrCreate(
                ['code' => $data['framework']['code']],
                [
                    'authority' => $data['framework']['authority'],
                    'kind' => $data['framework']['kind'],
                    'country' => null,
                    'label' => $data['framework']['label'],
                ],
            );

            $ver = FrameworkVersion::firstOrCreate(
                ['framework_id' => $fw->id, 'label' => $data['version']['label']],
                [
                    'source_url' => $data['version']['source_url'],
                    'source_sha256' => $sha256,
                ],
            );

            $raiz = strtolower($data['framework']['code']);

            foreach ($data['areas'] as $seq => $area) {
                // El nivel sale del código de área: 'A1.CO' → 'A1'.
                $nivelCode = explode('.', $area['code'])[0];
                $nivel = CurNode::updateOrCreate(
                    ['version_id' => $ver->id, 'path' => "{$raiz}.".strtolower($nivelCode)],
                    [
                        'node_type' => 'nivel',
                        'native_code' => $nivelCode,
                        'title' => ['es' => "Nivel {$nivelCode}"],
                        'seq' => 0,
                        'attrs' => ['source_url' => $data['version']['source_url']],
                    ],
                );

                $nodoArea = CurNode::updateOrCreate(
                    ['version_id' => $ver->id,
                        'path' => $nivel->path.'.'.strtolower(str_replace('.', '_', $area['code']))],
                    [
                        'parent_id' => $nivel->id,
                        'node_type' => 'area',
                        'native_code' => $area['code'],
                        'title' => $area['titulo'],
                        'seq' => $seq + 1,
                        'attrs' => ['source_url' => $data['version']['source_url']],
                    ],
                );

                foreach ($area['descriptores'] as $d) {
                    LearningObjective::updateOrCreate(
                        ['version_id' => $ver->id, 'node_id' => $nodoArea->id,
                            'native_code' => $d['code']],
                        [
                            'statement' => $d['statement'],
                            // VERIFICADO: la redacción sale de la parrilla de
                            // autoevaluación y del Volumen Complementario, que
                            // son públicos — la cita de cada uno va en attrs.
                            'is_verified' => true,
                            'attrs' => ['source_url' => $d['source_url'], 'fuente' => 'mcer-publico'],
                        ],
                    );
                }
            }
        });
    }
}
