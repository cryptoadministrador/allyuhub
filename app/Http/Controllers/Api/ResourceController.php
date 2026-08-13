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
        // Sin esto, la API filtraba el bundle_url de simuladores en borrador
        // (index() sí filtraba; show() no). La página /recurso ya lo hacía bien.
        abort_unless($resource->status === 'published', 404);

        return $resource->load([
            'currentVersion',
            'objectives:id,native_code,statement,node_id',
            'objectives.node:id,title,path',
        ]);
    }
}
