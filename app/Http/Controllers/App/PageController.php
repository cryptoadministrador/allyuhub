<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
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
