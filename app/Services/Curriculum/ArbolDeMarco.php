<?php

namespace App\Services\Curriculum;

use App\Models\CurNode;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;

/**
 * CÓMO ATERRIZA UN NODO DE UN MARCO en el grafo, escrito una vez.
 *
 * Vivía dentro de `InternationalFrameworksSeeder` (privado) y el sembrador de la
 * lengua inglesa de Cambridge iba a ser el segundo con la misma recursión, las
 * mismas claves del JSON y la misma regla de «los enunciados son paráfrasis, así
 * que `is_verified = false`». Con la regla copiada, el día que cambie el formato
 * del JSON uno de los dos se queda atrás en silencio.
 *
 * Dos invariantes que impone este sitio y no quien lo llama:
 *
 *  - **Idempotencia por `path`** dentro de la versión: re-sembrar actualiza el
 *    nodo, no lo duplica. Es lo que permite volver a correr `migrate --seed`.
 *  - **`is_verified = false` SIEMPRE.** Los documentos de Cambridge y del IB
 *    tienen copyright, así que lo que entra son paráfrasis de trabajo. Marcarlas
 *    como verificadas es tarea de un docente con acceso al syllabus oficial, y
 *    no algo que un fichero de datos pueda decidir por su cuenta.
 */
final class ArbolDeMarco
{
    /**
     * Inserta un nodo, sus objetivos y —recursivamente— su subárbol.
     *
     * @param  array<string, mixed>  $data  Nodo en el formato del JSON de marcos.
     */
    public static function sembrar(FrameworkVersion $ver, array $data, ?string $parentId, int $seq): CurNode
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
            self::sembrar($ver, $child, $node->id, $i);
        }

        return $node;
    }
}
