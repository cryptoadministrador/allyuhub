<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Api\CurriculumController;
use App\Http\Controllers\Controller;
use App\Models\CurNode;
use App\Models\Framework;
use App\Models\FrameworkVersion;
use App\Models\LearningObjective;
use App\Models\ObjectiveMastery;
use App\Models\Resource;
use App\Models\Track;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Páginas Inertia de la app (grupo web + auth). Las props son MÍNIMAS: el
 * ítem instanciado, la solución y las semillas JAMÁS viajan como props —
 * la página pide el siguiente ítem a la API de práctica (misma sesión).
 */
class PageController extends Controller
{
    /** GET /practicar/{objective} — el bucle de práctica de una destreza. */
    public function practicar(Request $request, LearningObjective $objective)
    {
        $mastery = ObjectiveMastery::query()
            ->where('user_id', $request->user()->id)
            ->where('objective_id', $objective->id)
            ->value('mastery');

        return Inertia::render('Practicar', [
            'objective' => [
                'id' => $objective->id,
                'native_code' => $objective->native_code,
                'statement' => $objective->statement['es'] ?? '',
                'has_items' => $objective->practiceItems()->exists(),
            ],
            'mastery' => $mastery === null ? null : (float) $mastery,
        ]);
    }

    /** GET /recurso/{resource} — un simulador publicado con su bundle. */
    public function recurso(Resource $resource)
    {
        abort_unless($resource->status === 'published', 404);

        return Inertia::render('Recurso', [
            'resource' => [
                'id' => $resource->id,
                'slug' => $resource->slug,
                'title' => $resource->title['es'] ?? $resource->slug,
                'bundle_url' => $resource->currentVersion?->bundle_url,
            ],
        ]);
    }

    /**
     * GET /catalogo — los marcos y el árbol de EC-MINEDEC hasta grado.
     * Primer pintado server-side; la navegación posterior va contra /api/v1.
     */
    public function catalogo()
    {
        $frameworks = Framework::query()->orderBy('code')->get()
            ->map(fn (Framework $fw) => [
                'id' => $fw->id,
                'code' => $fw->code,
                'label' => $fw->label['es'] ?? $fw->code,
                'kind' => $fw->kind,
            ])->values();

        $tree = [];
        $minedec = Framework::where('code', 'EC-MINEDEC')->first();
        if ($minedec !== null) {
            $version = $minedec->versions()->latest('valid_from')->first();
            $tree = $version?->roots()->with('children.children')->get()
                ->map(fn (CurNode $n) => $this->nodoLigero($n, 2))->values() ?? collect();
        }

        return Inertia::render('catalogo', [
            'frameworks' => $frameworks,
            'tree' => $tree,
        ]);
    }

    /** Nodo del árbol con lo MÍNIMO para pintar (nada de attrs ni paths). */
    private function nodoLigero(CurNode $node, int $depth): array
    {
        $ligero = [
            'id' => $node->id,
            'node_type' => $node->node_type,
            'native_code' => $node->native_code,
            'title' => $node->title['es'] ?? '',
        ];

        if ($depth > 0) {
            $ligero['children'] = $node->children
                ->map(fn (CurNode $c) => $this->nodoLigero($c, $depth - 1))->values()->all();
        }

        return $ligero;
    }

    /**
     * GET /catalogo/{node} — un nodo: migas, hijos y sus destrezas paginadas.
     * La lista de destrezas es EXACTAMENTE la de la API nodeObjectives
     * (misma consulta, misma paginación de 50): una sola fuente de verdad.
     */
    public function catalogoNodo(Request $request, CurNode $node)
    {
        $paginator = (new CurriculumController)->nodeObjectives($node, $request);

        return Inertia::render('catalogo-nodo', [
            'node' => [
                'id' => $node->id,
                'node_type' => $node->node_type,
                'native_code' => $node->native_code,
                'title' => $node->title['es'] ?? '',
            ],
            'breadcrumbs' => $node->ancestors()
                ->map(fn (CurNode $n) => [
                    'id' => $n->id, 'title' => $n->title['es'] ?? '', 'node_type' => $n->node_type,
                ])->values(),
            'children' => $node->children
                ->map(fn (CurNode $c) => [
                    'id' => $c->id, 'title' => $c->title['es'] ?? '',
                    'node_type' => $c->node_type, 'native_code' => $c->native_code,
                ])->values(),
            'objectives' => [
                'data' => collect($paginator->items())->map(fn (LearningObjective $o) => [
                    'id' => $o->id,
                    'native_code' => $o->native_code,
                    'statement' => $o->statement['es'] ?? '',
                    'is_verified' => (bool) $o->is_verified,
                    'has_items' => (bool) $o->has_items,
                ])->values(),
                'total' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
            ],
        ]);
    }

    /**
     * GET /destreza/{objective} — la ficha: migas, estado de verificación,
     * práctica, recursos publicados, equivalencias REVISADAS y prerrequisitos.
     * Reusa la consulta de la API objectives/{id} (recursos published +
     * alignments production): la trampa del crosswalk se respeta por diseño.
     */
    public function destreza(LearningObjective $objective)
    {
        $detalle = (new CurriculumController)->objective($objective);

        // El otro extremo de cada alineación revisada, con su marco (en bulk).
        $alignments = collect($detalle->getAttribute('alignments'))
            ->map(fn ($a) => [
                'relation' => $a->relation,
                'other' => $a->source_id === $objective->id ? $a->target : $a->source,
            ])
            ->filter(fn ($fila) => $fila['other'] !== null);
        $frameworkPorVersion = FrameworkVersion::query()
            ->whereIn('id', $alignments->pluck('other.version_id')->unique()->filter())
            ->with('framework:id,code')
            ->get()
            ->keyBy('id');

        $node = $objective->node;
        $breadcrumbs = $node === null ? collect() : $node->ancestors()->push($node);

        return Inertia::render('destreza', [
            'objective' => [
                'id' => $objective->id,
                'native_code' => $objective->native_code,
                'statement' => $objective->statement['es'] ?? '',
                'is_verified' => (bool) $objective->is_verified,
                'has_items' => $objective->practiceItems()->exists(),
            ],
            'breadcrumbs' => $breadcrumbs
                ->map(fn (CurNode $n) => [
                    'id' => $n->id, 'title' => $n->title['es'] ?? '', 'node_type' => $n->node_type,
                ])->values(),
            'resources' => collect($detalle->resources)
                ->map(fn ($r) => [
                    'id' => $r->id, 'slug' => $r->slug, 'kind' => $r->kind,
                    'title' => $r->title['es'] ?? $r->slug,
                ])->values(),
            'alignments' => $alignments
                ->map(fn ($fila) => [
                    'native_code' => $fila['other']->native_code,
                    'relation' => $fila['relation'],
                    'framework' => $frameworkPorVersion[$fila['other']->version_id]?->framework?->code,
                    'objective_id' => $fila['other']->id,
                ])->values(),
            'prerequisites' => $objective->prerequisites()
                // Mismas aristas que admite el motor: manual o revisada.
                ->where(fn ($q) => $q->where('alignments.method', 'manual')
                    ->orWhereNotNull('alignments.reviewed_at'))
                ->get()
                ->map(fn (LearningObjective $p) => [
                    'id' => $p->id,
                    'native_code' => $p->native_code,
                    'statement' => $p->statement['es'] ?? '',
                ])->values(),
        ]);
    }

    /**
     * GET /buscar?q= — resultados server-side si q ≥ 3 (misma consulta que la
     * API objectives/search); el tecleo posterior va contra la API con debounce.
     */
    public function buscar(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $results = null;
        if (mb_strlen($q) >= 3) {
            $results = (new CurriculumController)->search($request)
                ->map(fn (LearningObjective $o) => [
                    'id' => $o->id,
                    'native_code' => $o->native_code,
                    'statement' => mb_strimwidth($o->statement['es'] ?? '', 0, 160, '…'),
                    'is_verified' => (bool) $o->is_verified,
                    'has_items' => (bool) $o->has_items,
                    'node_title' => $o->node?->title['es'] ?? '',
                ])->values();
        }

        return Inertia::render('buscar', [
            'q' => $q,
            'results' => $results,
        ]);
    }

    /** GET /progreso — resumen por track (la página consulta la API por track). */
    public function progreso()
    {
        return Inertia::render('Progreso', [
            'tracks' => Track::query()->orderBy('code')->get()
                ->map(fn (Track $track) => [
                    'id' => $track->id,
                    'code' => $track->code,
                    'label' => $track->label['es'] ?? $track->code,
                ])
                ->values(),
        ]);
    }
}
