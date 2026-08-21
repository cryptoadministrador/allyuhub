<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Resource;
use Illuminate\Http\Request;

class ResourceController extends Controller
{
    /** GET /api/v1/resources?kind=lab&objective=CN.F.5.1.12 */
    public function index(Request $request)
    {
        return Resource::published()
            ->when($request->query('kind'), fn ($q, $k) => $q->where('kind', $k))
            ->when($request->query('objective'), fn ($q, $code) => $q
                ->whereHas('objectives', fn ($o) => $o->where('native_code', $code)))
            ->with('currentVersion:id,semver,bundle_url,published_at')
            ->withCount('objectives')
            ->paginate(24);
    }

    /** GET /api/v1/resources/{slug} — solo publicados: un borrador no existe. */
    public function show(Resource $resource)
    {
        // Por el MISMO scope que `index()`, no por una condición escrita a mano
        // al lado. Aquí vivía `status === 'published'`, que es lo que `published()`
        // significaba el día que se escribió; cuando el scope pasó a exigir
        // además la firma del docente, esta línea se quedó atrás y `show()`
        // servía el texto íntegro de una lección sin revisar —`currentVersion`
        // trae `config`, o sea la lección entera—. Es la segunda vez que estas
        // dos rutas divergen: el comentario que había aquí contaba la primera.
        abort_unless(Resource::published()->whereKey($resource->id)->exists(), 404);

        return $resource->load([
            'currentVersion',
            'objectives:id,native_code,statement,node_id',
            'objectives.node:id,title,path',
        ]);
    }
}
