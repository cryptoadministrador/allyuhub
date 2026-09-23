<?php

namespace Database\Seeders;

use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Services\Lesson\DestinosDeBloque;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Siembra los DESCRIPTORES PROPIOS del curso de inglés 0861
 * (`database/data/ingles-0861-interno.php`): el sitio donde aterriza el
 * contenido de `/corso/en` mientras el marco oficial de Cambridge no esté.
 *
 * Estructura: AH-EN0861 → stage (s7, s8, s9) → strand (r, w, sl) → descriptor.
 *
 * Tres reglas, y las tres fallan ANTES de escribir nada:
 *
 *  1. **Cada descriptor apunta a un nodo PÚBLICO de 0861 que existe** (`ref`).
 *     Si `CambridgeEnglishSeeder` no corrió, o la ruta tiene una errata, esto
 *     revienta nombrando el descriptor — la misma disciplina que el injerto.
 *  2. **El código es nuestro y lo parece**: empieza por `EN<stage>.`. Un
 *     código con forma de Cambridge (`7Rv.01`) en un marco interno sería
 *     justo el invento que el grafo prohíbe.
 *  3. **Nada entra verificado ni como oficial.**
 *
 * Idempotente por (versión, path) y (versión, nodo, código), como CefrSeeder.
 */
class InglesInternoSeeder extends Seeder
{
    public function run(): void
    {
        $this->sembrarDesde(database_path('data/ingles-0861-interno.php'));
    }

    public function sembrarDesde(string $ruta): void
    {
        $data = require $ruta;
        $sha256 = hash_file('sha256', $ruta);

        $refs = $this->nodosDeCambridge($data['cambridge']['marco'], $data['cambridge']['raiz']);

        // ===== Validación completa antes de tocar la base =====
        $vistos = [];
        foreach ($data['stages'] as $stage => $strands) {
            foreach ($strands as $strand => $descriptores) {
                if (! isset($data['strands'][$strand])) {
                    throw new RuntimeException("Stage {$stage}: strand «{$strand}» no declarado en 'strands'.");
                }
                foreach ($descriptores as $d) {
                    $code = $d['code'] ?? '';
                    if (preg_match('/^EN'.$stage.'\\.'.preg_quote($strand, '/').'\\.\\d+$/', $code) !== 1) {
                        throw new RuntimeException(
                            "El descriptor «{$code}» no tiene la forma EN{$stage}.{$strand}.n: un código "
                            .'de un marco INTERNO no puede parecerse a uno de Cambridge.',
                        );
                    }
                    if (isset($vistos[$code])) {
                        throw new RuntimeException("Descriptor repetido: «{$code}».");
                    }
                    $vistos[$code] = true;
                    if (! isset($refs[$d['ref'] ?? ''])) {
                        throw new RuntimeException(
                            "El descriptor «{$code}» apunta a «".($d['ref'] ?? '')."», que no existe en "
                            ."{$data['cambridge']['marco']}. ¿Corrió CambridgeEnglishSeeder? ¿Errata en la ruta?",
                        );
                    }
                    if (trim($d['es'] ?? '') === '' || trim($d['en'] ?? '') === '') {
                        throw new RuntimeException("El descriptor «{$code}» necesita enunciado en es y en.");
                    }
                }
            }
        }

        DB::transaction(function () use ($data, $sha256, $refs) {
            // updateOrCreate, no firstOrCreate: editar el fichero tiene que
            // moverse a la base (etiqueta del marco y sha256 de la fuente).
            $fw = Framework::updateOrCreate(
                ['code' => $data['framework']['code']],
                [
                    'authority' => $data['framework']['authority'],
                    'kind' => $data['framework']['kind'],
                    'country' => null,
                    'label' => $data['framework']['label'],
                ],
            );

            $ver = FrameworkVersion::updateOrCreate(
                ['framework_id' => $fw->id, 'label' => $data['version']['label']],
                ['source_url' => $data['version']['source_url'], 'source_sha256' => $sha256],
            );

            // ltree no admite guiones: 'AH-EN0861' → 'ah_en0861'.
            $raiz = strtolower(str_replace('-', '_', $data['framework']['code']));

            $enFichero = [];
            foreach ($data['stages'] as $stage => $strands) {
                $nodoStage = CurNode::updateOrCreate(
                    ['version_id' => $ver->id, 'path' => "{$raiz}.s{$stage}"],
                    [
                        'node_type' => 'stage',
                        'native_code' => "EN{$stage}",
                        'title' => ['es' => "Stage {$stage}", 'en' => "Stage {$stage}"],
                        'seq' => $stage,
                        'attrs' => ['oficial' => false],
                    ],
                );

                $seqStrand = 0;
                foreach ($strands as $strand => $descriptores) {
                    $nodoStrand = CurNode::updateOrCreate(
                        ['version_id' => $ver->id, 'path' => $nodoStage->path.'.'.strtolower($strand)],
                        [
                            'parent_id' => $nodoStage->id,
                            'node_type' => 'strand',
                            'native_code' => "EN{$stage}.{$strand}",
                            'title' => $data['strands'][$strand],
                            'seq' => ++$seqStrand,
                            'attrs' => ['oficial' => false],
                        ],
                    );

                    foreach ($descriptores as $d) {
                        $enFichero[] = $d['code'];
                        LearningObjective::updateOrCreate(
                            ['version_id' => $ver->id, 'node_id' => $nodoStrand->id, 'native_code' => $d['code']],
                            [
                                'statement' => ['es' => $d['es'], 'en' => $d['en']],
                                'is_verified' => false,
                                'attrs' => [
                                    'oficial' => false,
                                    'fuente' => 'allyuhub-interno',
                                    // El puente para reanclar cuando llegue el
                                    // marco oficial: mismo sub-strand público.
                                    'cambridge_ref' => $d['ref'],
                                    'cambridge_ref_titulo' => $refs[$d['ref']],
                                ],
                            ],
                        );
                    }
                }
            }

            $this->retirar($ver, $enFichero);
        });
    }

    /**
     * Un descriptor que ya NO está en el fichero (renombrado o retirado).
     *
     * Sin contenido colgando, se borra: dejarlo sería un «Puedo…» fantasma en
     * `/buscar` y en el anclaje. CON contenido, REVIENTA y deshace la siembra:
     * las claves foráneas borran en cascada, así que borrarlo se llevaría por
     * delante ítems, dominio de alumnos y producciones; y dejarlo en silencio
     * haría desaparecer ese contenido del curso sin que nadie se entere.
     * Renombrar un descriptor con contenido es una migración de datos, no una
     * edición del fichero.
     *
     * @param  list<string>  $enFichero
     */
    private function retirar(FrameworkVersion $ver, array $enFichero): void
    {
        $sobrantes = LearningObjective::query()
            ->where('version_id', $ver->id)
            ->whereNotIn('native_code', $enFichero)
            ->get(['id', 'native_code']);

        foreach ($sobrantes as $o) {
            $usos = collect(self::DEPENDIENTES)
                ->filter(fn ($tabla) => DB::table($tabla)->where('objective_id', $o->id)->exists())
                ->merge(DB::table('alignments')->where('source_id', $o->id)->orWhere('target_id', $o->id)->exists()
                    ? ['alignments'] : []);

            if ($usos->isNotEmpty()) {
                throw new RuntimeException(
                    "El descriptor «{$o->native_code}» ya no está en el fichero pero tiene contenido "
                    .'('.$usos->implode(', ').'). Reanclar ese contenido es una migración de datos: '
                    .'no se borra ni se abandona en silencio.',
                );
            }

            $o->delete();
        }
    }

    /** Tablas que cuelgan de un descriptor por `objective_id` (todas con cascada). */
    private const DEPENDIENTES = [
        'practice_items', 'resource_objectives', 'objective_masteries', 'track_phase_objectives',
        'lti_resource_links', 'producciones', 'dialogos',
    ];

    /**
     * Rutas de los nodos PÚBLICOS de 0861 → su título, para validar `ref`.
     *
     * @return array<string, mixed>
     */
    private function nodosDeCambridge(string $marco, string $raiz): array
    {
        $versiones = DestinosDeBloque::versionesDe($marco);
        if ($versiones === null) {
            throw new RuntimeException(
                "No existe el marco {$marco}: los descriptores de inglés cuelgan de sus strands. "
                .'Corre InternationalFrameworksSeeder y CambridgeEnglishSeeder antes.',
            );
        }

        $programa = CurNode::query()->whereIn('version_id', $versiones)->where('path', $raiz)->first();
        if ($programa === null) {
            throw new RuntimeException(
                "No existe «{$raiz}» en {$marco}: los descriptores de inglés cuelgan de sus strands. "
                .'Corre CambridgeEnglishSeeder antes.',
            );
        }

        return CurNode::query()
            ->descendantsOf($programa)
            ->get(['path', 'title'])
            ->push($programa)
            ->mapWithKeys(fn ($n) => [(string) $n->path => $n->title])
            ->all();
    }
}
