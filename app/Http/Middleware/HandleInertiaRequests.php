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
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() === null ? null : [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                ],
                // Closures, no valores: share() se ejecuta ANTES de la
                // respuesta y en TODO el grupo web — incluidos los endpoints
                // JSON de práctica, que no llevan props Inertia. Diferido, la
                // consulta solo se paga cuando se pinta una página (auditoría).
                'es_docente' => fn () => $this->contextosDocente($request) !== [],
                'contextos' => fn () => $this->contextosDocente($request),
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
            ->get(['id', 'title'])
            // El orden se decide en PHP: `title` es nullable y SQLite pone los
            // NULL primero mientras PostgreSQL los pone últimos — los enlaces
            // del nav saldrían en orden distinto en test y en producción.
            ->sortBy(fn (LtiContext $c) => [$c->title ?? '', $c->id])
            ->map(fn (LtiContext $c) => ['id' => $c->id, 'title' => $c->title])
            ->values()
            ->all();
    }
}
