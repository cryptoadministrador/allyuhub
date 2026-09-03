<?php

namespace App\Services\Docente;

use App\Models\LtiContext;
use App\Models\LtiContextMembership;
use App\Models\LtiPlatform;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * QUIÉN ES DOCENTE, escrito una vez.
 *
 * El rol NUNCA vive en `users`: es POR CONTEXTO (la misma persona es instructor
 * de un curso y learner de otro). Docente = tiene membership `instructor` en al
 * menos un contexto de una Platform ACTIVA — un Moodle desconectado no debe
 * seguir abriendo puertas, igual que no enciende el panel en el nav ni entra en
 * la CSP (auditoría PR #18).
 *
 * Vivía dentro de `HandleInertiaRequests` (para el nav) y la pantalla de
 * revisión iba a ser el segundo sitio con la misma consulta. Ahora los dos
 * llaman aquí: si mañana «docente» deja de significar esto, cambia en un sitio.
 *
 * DECISIÓN (misión §1): un docente ve y firma TODAS las lenguas. No existe un
 * rol «profesor de italiano» en el modelo y no se inventa: la responsabilidad
 * es de quien firma, y su nombre queda en la firma y en el rastro.
 */
final class Docencia
{
    /** Los contextos donde este usuario es instructor (Platform activa). */
    public static function contextos(?User $user): Collection
    {
        if ($user === null) {
            return collect();
        }

        return LtiContext::query()
            ->whereIn('platform_id', LtiPlatform::query()->active()->select('id'))
            ->whereIn('id', LtiContextMembership::query()
                ->where('user_id', $user->id)
                ->where('role', 'instructor')
                ->select('lti_context_id'))
            ->get(['id', 'title']);
    }

    /** ¿Es docente? Un invitado, no; un alumno, tampoco. */
    public static function es(?User $user): bool
    {
        return self::contextos($user)->isNotEmpty();
    }
}
