<?php

namespace App\Http\Middleware;

use App\Models\LtiContext;
use App\Models\LtiContextMembership;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Props compartidas con TODAS las páginas Inertia. Regla de oro: al cliente
 * viaja lo MÍNIMO — del usuario solo id y nombre (nunca email, lti_sub ni
 * ningún atributo del modelo entero).
 *
 * `auth.es_docente` + `auth.contextos` encienden «Panel del curso» en la
 * navegación. El rol es POR CONTEXTO (regla dura del repo): aquí solo entran
 * los cursos donde el usuario es INSTRUCTOR, con id y título y nada más —
 * un learner no recibe ni los ids de sus cursos.
 */
class HandleInertiaRequests extends Middleware
{
    /** @var string */
    protected $rootView = 'app';

    /** Memo por petición: el nav pide es_docente y contextos, y es UNA consulta. */
    private ?array $contextosDocente = null;

    public function share(Request $request): array
    {
        $contextos = $this->contextosDocente($request);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() === null ? null : [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                ],
                'es_docente' => $contextos !== [],
                'contextos' => $contextos,
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }

    /**
     * Los cursos donde el usuario es instructor: {id, title}. Una sola consulta
     * acotada por usuario (subconsulta sobre memberships) — el coste no crece
     * con los cursos de otros docentes.
     *
     * @return list<array{id: string, title: ?string}>
     */
    private function contextosDocente(Request $request): array
    {
        if ($this->contextosDocente !== null) {
            return $this->contextosDocente;
        }

        $user = $request->user();
        if ($user === null) {
            return $this->contextosDocente = [];
        }

        return $this->contextosDocente = LtiContext::query()
            ->whereIn('id', LtiContextMembership::query()
                ->where('user_id', $user->id)
                ->where('role', 'instructor')
                ->select('lti_context_id'))
            ->orderBy('title')
            ->orderBy('id')
            ->get(['id', 'title'])
            ->map(fn (LtiContext $c) => ['id' => $c->id, 'title' => $c->title])
            ->all();
    }
}
