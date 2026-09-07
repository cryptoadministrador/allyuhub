<?php

namespace Database\Seeders;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Services\Curriculum\ArbolDeMarco;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Siembra la LENGUA INGLESA de Cambridge desde
 * `database/data/marcos-ingles-cambridge.json`.
 *
 * Cambridge es inglés, y hasta ahora el grafo solo tenía su STEM
 * (`marcos-internacionales.json`). Aquí entran:
 *
 *   CAIE-PRI   Cambridge Primary English 0058 (stages 1-6)   ← marco NUEVO
 *   CAIE-LSEC  Lower Secondary English 0861 (stages 7-9)     ← injerto
 *   CAIE-IGCSE 0500, 0510, 0511, 0472                        ← injertos
 *   CAIE-ASA   English Language 9093                         ← injertos
 *
 * DOS FORMAS DE ENTRAR, y por eso el JSON tiene dos claves:
 *
 *  - `frameworks`: un marco entero que no existía (Cambridge Primary).
 *  - `injertos`: un nodo que cuelga de un programa YA sembrado por
 *    `InternationalFrameworksSeeder` (el inglés de Lower Secondary cuelga del
 *    mismo `lsec` que Matemática y Ciencias). No se duplica el programa: se
 *    busca por `path` dentro de su versión y se falla RUIDOSAMENTE si no está,
 *    porque un injerto silencioso que no encuentra su rama deja media lengua
 *    fuera del grafo sin que nadie se entere.
 *
 * LO QUE NO HACE, a propósito: **no inventa códigos**. Los marcos de Primary y
 * Lower Secondary son de descarga protegida (solo escuelas Cambridge), así que
 * de ellos entran los *strands* y *sub-strands* —públicos en el curriculum
 * outline— como NODOS sin código, y CERO `learning_objectives`. De IGCSE y
 * AS & A Level, cuyos syllabus sí son públicos, entran los assessment
 * objectives con su código real prefijado por syllabus (`0500.R1`), porque la
 * clave de un código es (marco, versión, código) y Cambridge los recicla.
 */
class CambridgeEnglishSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/marcos-ingles-cambridge.json');
        $data = json_decode(file_get_contents($path), true);
        $sha256 = hash_file('sha256', $path);

        DB::transaction(function () use ($data, $sha256) {
            foreach ($data['frameworks'] ?? [] as $fwData) {
                $ver = $this->versionDe($fwData, $sha256);
                foreach ($fwData['nodes'] as $i => $node) {
                    ArbolDeMarco::sembrar($ver, $node, null, $i);
                }
            }

            foreach ($data['injertos'] ?? [] as $injerto) {
                $this->injertar($injerto);
            }
        });
    }

    /** El marco y su versión, creados si no estaban. */
    private function versionDe(array $fwData, string $sha256): FrameworkVersion
    {
        $fw = Framework::firstOrCreate(
            ['code' => $fwData['code']],
            [
                'authority' => $fwData['authority'],
                'kind' => $fwData['kind'],
                'country' => $fwData['country'] ?? null,
                'label' => $fwData['label'],
            ],
        );

        return FrameworkVersion::firstOrCreate(
            ['framework_id' => $fw->id, 'label' => $fwData['version']['label']],
            [
                'valid_from' => $fwData['version']['valid_from'] ?? null,
                'valid_to' => $fwData['version']['valid_to'] ?? null,
                'source_url' => $fwData['version']['source_url'] ?? null,
                'source_sha256' => $sha256,
            ],
        );
    }

    /**
     * Cuelga un nodo de un programa ya sembrado. Revienta si el marco, su
     * versión o el nodo padre no existen: es la misma disciplina de la errata
     * `CS.FL`/`CS.F` — un destino que no está no se ignora en silencio.
     */
    private function injertar(array $injerto): void
    {
        $fw = Framework::where('code', $injerto['framework'])->first();
        if ($fw === null) {
            throw new RuntimeException(
                "El marco «{$injerto['framework']}» no está sembrado: corre antes InternationalFrameworksSeeder.",
            );
        }

        // La versión MÁS RECIENTE del marco, con el mismo criterio explícito que
        // `DestinosDeBloque::versionesDe`: una versión sin fecha es la más VIEJA,
        // y el orden implícito sobre un nullable diverge entre SQLite y PostgreSQL.
        $ver = FrameworkVersion::query()
            ->where('framework_id', $fw->id)
            ->orderByRaw('(valid_from is null) asc')
            ->orderByDesc('valid_from')
            ->orderByDesc('created_at')
            ->first();

        if ($ver === null) {
            throw new RuntimeException("El marco «{$injerto['framework']}» no tiene ninguna versión sembrada.");
        }

        $padre = CurNode::where('version_id', $ver->id)->where('path', $injerto['padre'])->first();
        if ($padre === null) {
            throw new RuntimeException(
                "No existe el nodo «{$injerto['padre']}» en {$injerto['framework']}: el injerto de ".
                "«{$injerto['nodo']['path']}» se habría perdido en silencio.",
            );
        }

        // `seq` al final de los hermanos: el inglés llega después del STEM que ya
        // estaba, y el orden de los hermanos existentes no se toca.
        $seq = (int) CurNode::where('parent_id', $padre->id)->max('seq') + 1;

        ArbolDeMarco::sembrar($ver, $injerto['nodo'], $padre->id, $seq);
    }
}
