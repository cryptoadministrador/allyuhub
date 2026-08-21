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
use App\Services\Catalog\AcentoDeAsignatura;
use App\Services\Catalog\SubtreeCounts;
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
        // El invitado no tiene dominio guardado que mostrar: `null`, y la página
        // arranca su barra efímera en cero. Ni una consulta con `user_id = null`,
        // que en PostgreSQL no casa con nada pero tampoco significa nada.
        $mastery = $request->user() === null ? null : ObjectiveMastery::query()
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

    /**
     * GET /recurso/{resource} — la LECCIÓN (o el simulador) publicado.
     *
     * `published()` ya exige que la versión vigente esté firmada, así que aquí
     * no hay una segunda comprobación que pudiera divergir de la del catálogo.
     */
    public function recurso(Resource $resource)
    {
        abort_unless(Resource::published()->whereKey($resource->id)->exists(), 404);

        $version = $resource->currentVersion;

        return Inertia::render('Recurso', [
            'recurso' => [
                'id' => $resource->id,
                'slug' => $resource->slug,
                'kind' => $resource->kind,
                'title' => $resource->title['es'] ?? $resource->slug,
                'summary' => $resource->summary['es'] ?? null,
                'duration_min' => $resource->duration_min,
                // Los bloques van YA validados y con las fórmulas convertidas a
                // árbol MathML: el cliente no interpreta nada, solo pinta.
                'bloques' => $version?->config['bloques'] ?? [],
                // Solo para lo que no es lectura (Fase 2): un simulador sigue
                // teniendo su bundle. Una lección no tiene, y no debe tener.
                'bundle_url' => $resource->esLeccion() ? null : $version?->bundle_url,
            ],
            'destrezas' => $resource->objectives()
                ->get(['learning_objectives.id', 'native_code', 'statement'])
                ->map(fn (LearningObjective $o) => [
                    'id' => $o->id,
                    'native_code' => $o->native_code,
                    'statement' => $o->statement['es'] ?? '',
                ])->values(),
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

        $tree = collect();
        $minedec = Framework::where('code', 'EC-MINEDEC')->first();
        if ($minedec !== null) {
            $version = $minedec->versions()->latest('valid_from')->first();
            $raices = $version?->roots()->with('children.children')->get() ?? collect();

            // Las tarjetas de grado llevan cuántas destrezas hay debajo: se
            // cuentan de una vez para TODOS los grados (dos consultas), no una
            // por tarjeta. Un grado no tiene destrezas propias — cuelgan de
            // sus bloques— así que hace falta el subárbol.
            $grados = $raices->flatMap(fn (CurNode $n) => $n->children)
                ->flatMap(fn (CurNode $n) => $n->children)
                ->filter(fn (CurNode $n) => $n->node_type === 'grado');
            $cuentas = SubtreeCounts::para($grados);

            $tree = $raices->map(fn (CurNode $n) => $this->nodoLigero($n, 2, $cuentas))->values();
        }

        return Inertia::render('catalogo', [
            'frameworks' => $frameworks,
            'tree' => $tree,
        ]);
    }

    /**
     * Nodo del árbol con lo mínimo para pintar SU TARJETA: nada de paths ni de
     * attrs enteros, solo la etiqueta corta y la edad del grado (las que hacen
     * legible «1.º EGB · 5 años» frente a «Primer Grado de Educación General
     * Básica») y el icono y color de la asignatura.
     */
    private function nodoLigero(CurNode $node, int $depth, array $cuentas = []): array
    {
        $attrs = $node->attrs ?? [];

        $ligero = array_filter([
            'id' => $node->id,
            'node_type' => $node->node_type,
            'native_code' => $node->native_code,
            'title' => $node->title['es'] ?? '',
            'corto' => $attrs['corto'] ?? null,
            'edad' => $node->age_min,
            'icon' => $attrs['icon'] ?? null,
            'color' => $attrs['color'] ?? null,
        ], fn ($v) => $v !== null);

        if (isset($cuentas[$node->id])) {
            $ligero += $cuentas[$node->id];
        }

        if ($depth > 0) {
            $ligero['children'] = $node->children
                ->map(fn (CurNode $c) => $this->nodoLigero($c, $depth - 1, $cuentas))
                ->values()->all();
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

        $ancestros = $node->ancestors();
        $hijos = $node->children;
        // Una sola pasada para las tarjetas de los hijos (dos consultas en total).
        $cuentas = SubtreeCounts::para($hijos);

        return Inertia::render('catalogo-nodo', [
            'node' => $this->nodoLigero($node, 0),
            // El acento visual lo pone la asignatura, esté el usuario en ella o
            // en uno de sus bloques: se hereda del ancestro más cercano.
            // concat, no push: push MUTA la colección y las migas de abajo
            // acabarían incluyendo al propio nodo.
            'asignatura' => AcentoDeAsignatura::deCadena($ancestros->concat([$node])),
            'breadcrumbs' => $ancestros
                ->map(fn (CurNode $n) => [
                    'id' => $n->id, 'title' => $n->title['es'] ?? '', 'node_type' => $n->node_type,
                ])->values(),
            'children' => $hijos
                ->map(fn (CurNode $c) => $this->nodoLigero($c, 0, $cuentas))->values(),
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
        // Las aristas `prerequisite` NO son equivalencias entre marcos: aunque
        // un docente las firme, se muestran solo en su sección (auditoría).
        $alignments = collect($detalle->getAttribute('alignments'))
            ->reject(fn ($a) => $a->relation === 'prerequisite')
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
            'asignatura' => AcentoDeAsignatura::deCadena($breadcrumbs),
            'breadcrumbs' => $breadcrumbs
                ->map(fn (CurNode $n) => [
                    'id' => $n->id, 'title' => $n->title['es'] ?? '', 'node_type' => $n->node_type,
                ])->values(),
            // La LECCIÓN va aparte del resto de recursos: en el hub de la
            // destreza no es «un recurso más», es el primer paso. Leer, luego
            // practicar — sin eso la lección y la práctica serían dos sitios
            // sin relación y el alumno no encontraría el texto.
            'leccion' => $this->leccionDe($detalle),
            'resources' => collect($detalle->resources)
                ->reject(fn ($r) => $r->kind === Resource::LECTURA)
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
     * La lección de una destreza: el recurso de tipo lectura, si lo hay.
     *
     * Llega ya filtrada por `published()` —que exige versión firmada— desde la
     * misma consulta que alimenta la ficha, así que no hay una segunda
     * comprobación de la puerta que pudiera divergir.
     *
     * @return array<string, mixed>|null
     */
    private function leccionDe(LearningObjective $detalle): ?array
    {
        $leccion = collect($detalle->resources)
            ->first(fn ($r) => $r->kind === Resource::LECTURA);

        if ($leccion === null) {
            return null;
        }

        return [
            'id' => $leccion->id,
            'title' => $leccion->title['es'] ?? $leccion->slug,
            'summary' => $leccion->summary['es'] ?? null,
            'duration_min' => $leccion->duration_min,
            // Cuántos bloques tiene, para que la tarjeta diga si son dos
            // párrafos o una lección entera. El contenido se pide al abrirla.
            'bloques' => count($leccion->currentVersion?->config['bloques'] ?? []),
        ];
    }

    /**
     * GET /buscar?q= — resultados server-side si q ≥ 3 (misma consulta que la
     * API objectives/search); el tecleo posterior va contra la API con debounce.
     */
    public function buscar(Request $request)
    {
        // q hostil (array, >120 chars) no revienta ni expulsa de la app:
        // se trata como «sin búsqueda» y la página lo explica (auditoría).
        $crudo = $request->query('q', '');
        $q = is_string($crudo) ? trim($crudo) : '';

        $results = null;
        if (mb_strlen($q) >= 3 && mb_strlen($q) <= 120) {
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
