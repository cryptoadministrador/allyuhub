<?php

namespace App\Services\Blueprint;

use App\Models\Alignment;
use App\Models\CurNode;
use App\Models\LearningObjective;
use App\Models\Track;
use Illuminate\Support\Collection;

/**
 * El puente entre el grafo y el pipeline "curso-como-código" de e-learnium:
 * dado un nodo del grafo (normalmente una asignatura de un grado), devuelve el
 * BLUEPRINT del curso Moodle — unidades, destrezas (DCD), prerrequisitos y qué
 * se puede practicar en AllyuHub.
 *
 * Regla del proyecto: el grafo es la verdad. El repo de cursos NO copia las DCD
 * a mano; las recibe de aquí. Por eso el blueprint es DETERMINISTA (mismo grafo
 * → mismo byte a byte) y trae `fingerprint`: si cambia, el curso hay que
 * recompilarlo.
 *
 * Identidad: `idnumber` estable (regla 3 del §9 de la estrategia Moodle) —
 * es la clave limpia con la que EduPlat enlazará el curso, nunca el nombre.
 */
class CourseBlueprint
{
    /** Versión del contrato que consume el compilador (README de cursos-moodle). */
    public const CONTRACT = 'allyuhub/curso-blueprint@1';

    /** Cobertura mínima exigida por DCD (§4 de la estrategia: 1 lección, 3 preguntas). */
    public const MIN_LESSONS = 1;

    public const MIN_QUESTIONS = 3;

    public function forNode(CurNode $node, ?Track $track = null, ?string $idnumber = null): array
    {
        $node->loadMissing('version.framework');
        $ancestors = $node->ancestors();
        $chain = $ancestors->concat([$node]);
        $grade = $this->firstOfType($chain, 'grado');
        $area = $this->firstOfType($ancestors, 'area');

        $units = $this->units($node);
        $objectives = $units->flatMap(fn (array $u) => $u['destrezas']);
        $weights = $track ? $this->trackWeights($track, $objectives->pluck('id')) : collect();

        $units = $units->map(function (array $unit) use ($weights) {
            $unit['destrezas'] = array_map(function (array $d) use ($weights) {
                $pivot = $weights->get($d['id']);
                $d['peso'] = $pivot['weight'] ?? null;
                $d['fase'] = $pivot['fase'] ?? null;

                return $d;
            }, $unit['destrezas']->all());

            return $unit;
        })->values()->all();

        $curso = [
            'idnumber' => $idnumber ?: $this->idnumber($node, $grade),
            'titulo' => $this->text($node->title),
            'marco' => $node->version->framework->code,
            'version_marco' => $node->version->label,
            'nodo' => [
                'id' => $node->id,
                'tipo' => $node->node_type,
                'codigo' => $node->native_code,
                'path' => $node->path,
            ],
            'grado' => $grade ? ['codigo' => $grade->native_code, 'titulo' => $this->text($grade->title)] : null,
            'area' => $area ? ['codigo' => $area->native_code, 'titulo' => $this->text($area->title)] : null,
            'track' => $track?->code,
        ];

        $blueprint = [
            'contrato' => self::CONTRACT,
            'curso' => $curso,
            'cobertura' => [
                'min_lecciones_por_dcd' => self::MIN_LESSONS,
                'min_preguntas_por_dcd' => self::MIN_QUESTIONS,
            ],
            'unidades' => $units,
        ];

        $blueprint['resumen'] = $this->summary($units);
        $blueprint['fingerprint'] = $this->fingerprint($curso, $units);

        return $blueprint;
    }

    /**
     * Unidades del curso: un hijo directo = una unidad, y la unidad se lleva
     * TODO su subárbol.
     *
     * Que la unidad tome el subárbol y no solo sus destrezas directas no es un
     * detalle: los árboles tienen profundidades distintas (MINEDEC llega a
     * bloque, Cambridge encadena subject → stage → strand, IGCSE syllabus →
     * topic → subtopic). Mirando solo a los hijos, exportar un grado o
     * cualquier asignatura Cambridge daba un curso con unidades y CERO
     * destrezas, y el comando salía con éxito. Como cada descendiente cuelga de
     * exactamente un hijo directo, así la partición es exhaustiva y disjunta:
     * ninguna destreza se pierde ni se repite.
     *
     * Las destrezas colgadas del propio nodo van a la unidad 0 explícita: una
     * DCD fuera de toda unidad es una DCD que el curso nunca cubriría.
     *
     * @return Collection<int, array>
     */
    private function units(CurNode $node): Collection
    {
        $units = collect();

        $loose = $this->objectivesOf([$node->id]);
        if ($loose->isNotEmpty()) {
            $units->push([
                'seq' => 0,
                'slug' => 'unidad-00',
                'titulo' => 'Destrezas sin bloque',
                'nodo_id' => $node->id,
                'codigo' => $node->native_code,
                'destrezas' => $loose,
            ]);
        }

        foreach ($this->childrenOf($node) as $i => $child) {
            $seq = $i + 1;
            $ids = CurNode::query()->descendantsOf($child)->pluck('id')->push($child->id);
            $units->push([
                'seq' => $seq,
                'slug' => 'unidad-'.str_pad((string) $seq, 2, '0', STR_PAD_LEFT),
                'titulo' => $this->text($child->title),
                'nodo_id' => $child->id,
                'codigo' => $child->native_code,
                'destrezas' => $this->objectivesOf($ids->all()),
            ]);
        }

        return $units;
    }

    /**
     * Hijos directos con desempate TOTAL: `seq` es integer default 0 y no es
     * único, así que un importador que no lo fije dejaría el orden de las
     * unidades (y con él los slugs unidad-01/02… y el fingerprint) a merced
     * del plan de consulta.
     *
     * @return Collection<int, CurNode>
     */
    private function childrenOf(CurNode $node): Collection
    {
        return CurNode::query()
            ->where('parent_id', $node->id)
            ->orderBy('seq')
            ->orderByRaw('LENGTH(COALESCE(native_code, \'\'))')
            ->orderBy('native_code')
            ->orderBy('id')
            ->get();
    }

    /**
     * Destrezas de unos nodos en ORDEN CURRICULAR (longitud + alfabético: el
     * mismo criterio que el catálogo, para que CN.F.5.1.2 vaya antes que
     * CN.F.5.1.10) con lo que el compilador necesita para decidir qué generar.
     *
     * @return Collection<int, array>
     */
    private function objectivesOf(array $nodeIds): Collection
    {
        $objectives = LearningObjective::query()
            ->whereIn('node_id', $nodeIds)
            ->withExists('practiceItems as has_items')
            ->withCount('practiceItems as items_count')
            ->orderByRaw('LENGTH(native_code)')
            ->orderBy('native_code')
            ->orderBy('id')
            ->get();

        $prereqs = $this->prerequisites($objectives->pluck('id'));

        return $objectives->map(fn (LearningObjective $o) => [
            'id' => $o->id,
            'codigo' => $o->native_code,
            'enunciado' => $this->text($o->statement),
            'esencial' => (bool) $o->is_essential,
            'verificada' => (bool) $o->is_verified,
            'practicable' => (bool) $o->has_items,
            'items' => (int) $o->items_count,
            'prerrequisitos' => $prereqs->get($o->id, []),
            // Enlace de práctica: es lo que la lección Moodle embebe (o lanza
            // por LTI) para que el alumno practique en AllyuHub.
            'practica_url' => $o->has_items ? url('/practicar/'.$o->id) : null,
        ]);
    }

    /**
     * Prerrequisitos por destreza. Se admite la misma arista que admite el
     * motor de práctica (`prerequisite` con method=manual o revisada), pero
     * OJO: el motor además exige que el prerrequisito tenga ítems (si no, la
     * arista es decorativa para él). Aquí NO se filtra por eso — para secuenciar
     * un curso, un prerrequisito sin ítems sigue siendo información curricular
     * útil — y por eso cada entrada dice si es `practicable` y de qué `marco`
     * viene: una arista de crosswalk puede apuntar a otro marco y el curso no
     * debe presentarla como si fuera del suyo.
     *
     * @return Collection<string, array<int, array>>
     */
    private function prerequisites(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        return Alignment::query()
            ->where('relation', 'prerequisite')
            ->where(fn ($q) => $q->where('method', 'manual')->orWhereNotNull('reviewed_at'))
            ->whereIn('source_id', $ids)
            ->with(['target' => fn ($q) => $q->withExists('practiceItems as has_items')
                ->with('node.version.framework:id,code')])
            ->get()
            ->filter(fn (Alignment $a) => $a->target !== null)
            ->sortBy(fn (Alignment $a) => [strlen((string) $a->target->native_code), $a->target->native_code, $a->target->id])
            ->groupBy('source_id')
            ->map(fn (Collection $edges) => $edges->map(fn (Alignment $a) => [
                'id' => $a->target->id,
                'codigo' => $a->target->native_code,
                'enunciado' => $this->text($a->target->statement),
                'marco' => $a->target->node?->version?->framework?->code,
                'practicable' => (bool) $a->target->has_items,
            ])->values()->all());
    }

    /**
     * Pesos y fase del track para estas destrezas (track_phase_objectives).
     * Un track es un recorrido, no una copia: el mismo curso puede compilarse
     * para ORD y para PCEI-BSI cambiando solo esto.
     *
     * @return Collection<string, array>
     */
    private function trackWeights(Track $track, Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        return $track->phases()->with(['objectives' => fn ($q) => $q->whereIn('learning_objectives.id', $ids)])
            ->get()
            ->flatMap(fn ($phase) => $phase->objectives->map(fn ($o) => [
                'id' => $o->id,
                // decimal(4,2) sin cast: PostgreSQL lo devuelve como STRING y
                // SQLite como float. Sin este (float), el contrato cambiaba de
                // tipo (peso: "1.50" vs peso: 1.5) entre el CI y producción, y
                // la suite no podía verlo porque assertEquals(1.5, '1.50') pasa.
                'weight' => (float) $o->pivot->weight,
                'fase' => $this->text($phase->label) ?: 'fase '.$phase->seq,
            ]))
            ->keyBy('id');
    }

    /**
     * Huella de lo CURRICULAR, y solo de eso: códigos, enunciados, orden,
     * esencial/verificada, pesos y códigos de los prerrequisitos.
     *
     * Deliberadamente NO entran los uuid (ni los de las destrezas, ni el del
     * nodo, ni la practica_url que los lleva dentro) ni el número de ítems ni
     * el idnumber: un `migrate --seed` o una reimportación cambia todos los
     * uuid sin tocar una coma del currículo, y con ellos dentro el fingerprint
     * mandaba a recompilar TODOS los cursos. Lo que debe disparar una
     * recompilación es que cambie el currículo.
     *
     * @param  array<int, array>  $units
     */
    private function fingerprint(array $curso, array $units): string
    {
        $canonical = [
            'marco' => $curso['marco'],
            'version_marco' => $curso['version_marco'],
            'nodo' => $curso['nodo']['codigo'].'|'.$curso['nodo']['path'],
            'track' => $curso['track'],
            'unidades' => array_map(fn (array $u) => [
                'seq' => $u['seq'],
                'codigo' => $u['codigo'],
                'titulo' => $u['titulo'],
                'destrezas' => array_map(fn (array $d) => [
                    'codigo' => $d['codigo'],
                    'enunciado' => $d['enunciado'],
                    'esencial' => $d['esencial'],
                    'verificada' => $d['verificada'],
                    'peso' => $d['peso'],
                    'fase' => $d['fase'],
                    'prerrequisitos' => array_map(fn (array $p) => $p['marco'].':'.$p['codigo'], $d['prerrequisitos']),
                ], $u['destrezas']),
            ], $units),
        ];

        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @param  array<int, array>  $units */
    private function summary(array $units): array
    {
        $objectives = collect($units)->flatMap(fn (array $u) => $u['destrezas']);

        return [
            'unidades' => count($units),
            'destrezas' => $objectives->count(),
            'destrezas_practicables' => $objectives->where('practicable', true)->count(),
            'destrezas_sin_items' => $objectives->where('practicable', false)->count(),
            'destrezas_sin_verificar' => $objectives->where('verificada', false)->count(),
        ];
    }

    /** El primer nodo de un tipo dado (los ancestros vienen raíz → padre). */
    private function firstOfType(Collection $nodes, string $type): ?CurNode
    {
        return $nodes->last(fn (CurNode $n) => $n->node_type === $type);
    }

    /**
     * idnumber estable y legible: AH-<CODIGO_NODO>-<GRADO>. Sin puntos ni
     * espacios (Moodle los admite, pero un idnumber limpio es más fácil de
     * casar a mano y de leer en un log).
     */
    private function idnumber(CurNode $node, ?CurNode $grade): string
    {
        $clean = fn (?string $p) => preg_replace('/[^A-Za-z0-9]+/', '', (string) $p);
        $code = $clean($node->native_code);

        // Sin código nativo (pasa en Cambridge/IB: programme y syllabus se
        // sembraron sin código) el identificador sale de un HASH del uuid, no
        // de su prefijo: los uuid son v7, o sea que sus primeros hex son la
        // marca de tiempo, y dos nodos sembrados en el mismo milisegundo se
        // llevaban el mismo idnumber (con el seeder actual ya colisionaban
        // dp.phy y dp.math_aa, que además comparten grado). El idnumber es la
        // identidad del curso en Moodle: no puede repetirse.
        if ($code === '') {
            return 'AH-'.strtoupper(substr(hash('sha256', $node->id), 0, 10));
        }

        return strtoupper(collect(['AH', $code, $clean($grade?->native_code)])->filter()->implode('-'));
    }

    /** Texto multilingüe → español (el curso es en español). */
    private function text(mixed $value): string
    {
        if (is_array($value)) {
            return (string) ($value['es'] ?? $value['en'] ?? reset($value) ?: '');
        }

        return (string) ($value ?? '');
    }
}
