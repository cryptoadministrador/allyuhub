<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alignment;
use App\Models\CurNode;
use App\Models\Framework;
use App\Models\LearningObjective;
use Illuminate\Http\Request;

/**
 * API de navegación del grafo curricular (Capa 1) y del crosswalk (Capa 2).
 * Solo lectura: el grafo se importa, no se edita por API.
 */
class CurriculumController extends Controller
{
    /** GET /api/v1/frameworks — marcos disponibles con sus versiones. */
    public function frameworks()
    {
        return Framework::with('versions:id,framework_id,label,valid_from,valid_to')
            ->get(['id', 'code', 'authority', 'kind', 'country', 'label']);
    }

    /** GET /api/v1/frameworks/{code}/tree?depth=2 — raíces del marco (+ hijos opcionales). */
    public function tree(string $code, Request $request)
    {
        $fw = Framework::where('code', $code)->firstOrFail();
        $version = $fw->versions()->latest('valid_from')->firstOrFail();

        $depth = min((int) $request->query('depth', 2), 4);
        $with = collect(range(1, $depth))
            ->map(fn ($d) => implode('.', array_fill(0, $d, 'children')))
            ->all();

        return $version->roots()->with($with)->get();
    }

    /** GET /api/v1/nodes/{node} — un nodo con hijos y objetivos. */
    public function node(CurNode $node)
    {
        return $node->load([
            'children:id,parent_id,node_type,native_code,title,seq,attrs',
            'objectives:id,node_id,native_code,statement,is_essential,is_verified',
        ]);
    }

    /** GET /api/v1/nodes/{node}/objectives — todas las destrezas del subárbol (ltree). */
    public function nodeObjectives(CurNode $node, Request $request)
    {
        $ids = CurNode::query()->descendantsOf($node)->pluck('id')->push($node->id);

        return LearningObjective::whereIn('node_id', $ids)
            ->with('node:id,title,node_type,path')
            ->when($request->boolean('verified'), fn ($q) => $q->where('is_verified', true))
            ->paginate(50);
    }

    /** GET /api/v1/objectives/search?q=rozamiento — búsqueda full-text en español. */
    public function search(Request $request)
    {
        $request->validate(['q' => 'required|string|min:3|max:120']);

        return LearningObjective::search($request->query('q'))
            ->with('node:id,title,path,node_type')
            ->limit(40)
            ->get();
    }

    /** GET /api/v1/objectives/{objective} — detalle con recursos y crosswalk. */
    public function objective(LearningObjective $objective)
    {
        return $objective->load([
            'node:id,title,path,node_type',
            'resources' => fn ($q) => $q->published()
                ->select('resources.id', 'slug', 'kind', 'title', 'duration_min', 'status'),
        ])->setAttribute('alignments', Alignment::query()
            ->where(fn ($q) => $q->where('source_id', $objective->id)
                                 ->orWhere('target_id', $objective->id))
            ->production()
            ->with(['source:id,native_code,version_id', 'target:id,native_code,version_id'])
            ->get());
    }
}
