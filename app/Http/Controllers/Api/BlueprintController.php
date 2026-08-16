<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CurNode;
use App\Models\LearningObjective;
use App\Models\Track;
use App\Services\Blueprint\CourseBlueprint;
use Illuminate\Http\Request;

/**
 * Blueprint del curso para el pipeline "curso-como-código" (e-learnium).
 * Solo lectura, como todo /api/v1: el grafo se importa, no se edita por API.
 */
class BlueprintController extends Controller
{
    /**
     * Un blueprint no se pagina (el compilador lo necesita entero), así que el
     * tope es no servir nodos que no son un curso: pedir el blueprint de un
     * nivel entero devolvería cientos de KB y no describe ningún curso real.
     */
    private const MAX_OBJECTIVES = 1500;

    /** GET /api/v1/nodes/{node}/blueprint?track=ORD&idnumber=AH-MAT-1BGU */
    public function show(CurNode $node, Request $request, CourseBlueprint $blueprint)
    {
        $request->validate([
            'track' => 'nullable|string|max:32',
            'idnumber' => 'nullable|string|max:100|regex:/^[A-Za-z0-9._-]+$/',
        ]);

        $ids = CurNode::query()->descendantsOf($node)->pluck('id')->push($node->id);
        $count = LearningObjective::whereIn('node_id', $ids)->count();
        abort_if($count > self::MAX_OBJECTIVES, 422,
            "El nodo tiene {$count} destrezas: es demasiado grande para un curso. "
            .'Pide el blueprint de una asignatura, o usa `php artisan curso:blueprint`.');

        $track = $request->filled('track')
            ? Track::where('code', $request->query('track'))->firstOrFail()
            : null;

        return $blueprint->forNode($node, $track, $request->query('idnumber'));
    }
}
