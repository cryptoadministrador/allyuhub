<?php

namespace Database\Seeders;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra los marcos internacionales (roadmap CLAUDE.md §3) desde
 * database/data/marcos-internacionales.json:
 *
 *   CAIE-LSEC  Cambridge Lower Secondary — framework 2018 (stages 7-9)   ≈ 8.º-10.º EGB
 *   CAIE-IGCSE Cambridge IGCSE — syllabi 0580/0625/0620/0610 (2023-2025) ≈ 1.º-2.º BGU
 *   CAIE-ASA   Cambridge AS & A Level — syllabi 9709/9702 (2025-2027)    ≈ 2.º-3.º BGU
 *   IB-MYP     Middle Years Programme — criterios A-D, años 3 y 5        ≈ 10.º EGB-2.º BGU
 *   IB-DP      Diploma Programme — Física y Matemática AA                ≈ 2.º-3.º BGU
 *
 * Por qué un JSON declarativo y no PHP: la Capa 1 se IMPORTA (regla 1 de CLAUDE.md).
 * Cuando existan los PDF oficiales, un importador sustituirá este archivo sin tocar
 * el seeder — igual que `mineduc:import` sustituye la semilla de EC-MINEDEC.
 *
 * OJO con la regla 2: la clave de un código es (framework, version, código).
 * Cambridge recicla códigos —`0580.C1.1` en Matemática no tiene nada que ver con
 * `0620.1.1.1` en Química, y `7Pf.01` existe en Lower Secondary pero no en IGCSE—,
 * así que jamás busques un objetivo internacional solo por native_code.
 *
 * Los enunciados son PARÁFRASIS de trabajo (los documentos de Cambridge e IBO tienen
 * copyright), por eso entran con is_verified=false. Verificarlos es tarea de un docente
 * con acceso al framework/syllabus oficial.
 */
class InternationalFrameworksSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(database_path('data/marcos-internacionales.json')), true);

        DB::transaction(function () use ($data) {
            foreach ($data['frameworks'] as $fwData) {
                $fw = Framework::create([
                    'code' => $fwData['code'],
                    'authority' => $fwData['authority'],
                    'kind' => $fwData['kind'],
                    'country' => $fwData['country'] ?? null,
                    'label' => $fwData['label'],
                ]);

                $ver = FrameworkVersion::create([
                    'framework_id' => $fw->id,
                    'label' => $fwData['version']['label'],
                    'valid_from' => $fwData['version']['valid_from'] ?? null,
                    'valid_to' => $fwData['version']['valid_to'] ?? null,
                    'source_url' => $fwData['version']['source_url'] ?? null,
                ]);

                foreach ($fwData['nodes'] as $i => $node) {
                    $this->seedNode($ver, $node, null, $i);
                }
            }
        });
    }

    /** Inserta un nodo, sus objetivos y —recursivamente— su subárbol. */
    private function seedNode(FrameworkVersion $ver, array $data, ?string $parentId, int $seq): void
    {
        $node = CurNode::create([
            'version_id' => $ver->id,
            'parent_id' => $parentId,
            'node_type' => $data['node_type'],
            'native_code' => $data['native_code'] ?? null,
            'title' => array_filter([
                'es' => $data['title_es'] ?? null,
                'en' => $data['title_en'] ?? null,
            ]),
            'seq' => $seq,
            'path' => $data['path'],
            'age_min' => $data['age_min'] ?? null,
            'age_max' => $data['age_max'] ?? null,
            'attrs' => $data['attrs'] ?? [],
        ]);

        foreach ($data['objectives'] ?? [] as $o) {
            [$code, $en, $es] = $o;
            $attrs = $o[3] ?? [];

            LearningObjective::create([
                'node_id' => $node->id,
                'version_id' => $ver->id,
                'native_code' => $code,
                'statement' => ['es' => $es, 'en' => $en],
                // Paráfrasis de trabajo: nadie las ha cotejado contra la fuente oficial.
                'is_verified' => false,
                'attrs' => $attrs + ['fuente' => 'parafrasis-semilla'],
            ]);
        }

        foreach ($data['children'] ?? [] as $i => $child) {
            $this->seedNode($ver, $child, $node->id, $i);
        }
    }
}
