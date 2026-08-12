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
 *   CAIE-LSEC  Cambridge Lower Secondary — framework 2020 (stages 7-9)  ≈ 8.º-10.º EGB
 *   CAIE-IGCSE Cambridge IGCSE — 0580, 0625, 0620, 0610                 ≈ 1.º-2.º BGU
 *   CAIE-ASA   Cambridge AS & A Level — 9709, 9702                      ≈ 2.º-3.º BGU
 *   IB-MYP     Middle Years Programme — criterios A-D, años 3 y 5       ≈ 10.º EGB-2.º BGU
 *   IB-DP      Diploma Programme — Física y Matemática AA               ≈ 2.º-3.º BGU
 *
 * Sobre las versiones: cada syllabus de Cambridge tiene su propio ciclo (0580 va por
 * 2025-2027 mientras 0625/0620/0610 van por 2026-2028) y cada guía del IB cambia por
 * separado. Como `framework_versions` es una fila por marco, la etiqueta es una FOTO
 * fechada (`foto-2026-08`) y la vigencia real de cada syllabus/guía viaja en
 * `attrs.vigencia` + `attrs.source_url` del nodo. Cuando entre en vigor un ciclo nuevo
 * —por ejemplo 9702 2028-2030, ya publicado— se siembra como una versión aparte.
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
        $path = database_path('data/marcos-internacionales.json');
        $data = json_decode(file_get_contents($path), true);
        // Trazabilidad al archivo exacto que se sembró, igual que hace `mineduc:import`.
        $sha256 = hash_file('sha256', $path);

        DB::transaction(function () use ($data, $sha256) {
            foreach ($data['frameworks'] as $fwData) {
                $fw = Framework::firstOrCreate(
                    ['code' => $fwData['code']],
                    [
                        'authority' => $fwData['authority'],
                        'kind' => $fwData['kind'],
                        'country' => $fwData['country'] ?? null,
                        'label' => $fwData['label'],
                    ],
                );

                $ver = FrameworkVersion::firstOrCreate(
                    ['framework_id' => $fw->id, 'label' => $fwData['version']['label']],
                    [
                        'valid_from' => $fwData['version']['valid_from'] ?? null,
                        'valid_to' => $fwData['version']['valid_to'] ?? null,
                        'source_url' => $fwData['version']['source_url'] ?? null,
                        'source_sha256' => $sha256,
                    ],
                );

                foreach ($fwData['nodes'] as $i => $node) {
                    $this->seedNode($ver, $node, null, $i);
                }
            }
        });
    }

    /** Inserta un nodo, sus objetivos y —recursivamente— su subárbol. */
    private function seedNode(FrameworkVersion $ver, array $data, ?string $parentId, int $seq): void
    {
        $node = CurNode::updateOrCreate(
            ['version_id' => $ver->id, 'path' => $data['path']],
            [
                'parent_id' => $parentId,
                'node_type' => $data['node_type'],
                'native_code' => $data['native_code'] ?? null,
                'title' => array_filter([
                    'es' => $data['title_es'] ?? null,
                    'en' => $data['title_en'] ?? null,
                ]),
                'seq' => $seq,
                'age_min' => $data['age_min'] ?? null,
                'age_max' => $data['age_max'] ?? null,
                'attrs' => $data['attrs'] ?? [],
            ],
        );

        foreach ($data['objectives'] ?? [] as $o) {
            [$code, $en, $es] = $o;
            $attrs = $o[3] ?? [];

            LearningObjective::updateOrCreate(
                ['version_id' => $ver->id, 'node_id' => $node->id, 'native_code' => $code],
                [
                    'statement' => ['es' => $es, 'en' => $en],
                    // Paráfrasis de trabajo: nadie las ha cotejado contra la fuente oficial.
                    'is_verified' => false,
                    'attrs' => $attrs + ['fuente' => 'parafrasis-semilla'],
                ],
            );
        }

        foreach ($data['children'] ?? [] as $i => $child) {
            $this->seedNode($ver, $child, $node->id, $i);
        }
    }
}
