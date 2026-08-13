<?php

namespace App\Jobs;

use App\Models\LtiResourceLink;
use App\Models\ObjectiveMastery;
use App\Services\Lti\LtiDatabase;
use App\Services\Lti\LtiHttpConnector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Packback\Lti1p3\LtiAssignmentsGradesService;
use Packback\Lti1p3\LtiConstants;
use Packback\Lti1p3\LtiGrade;
use RuntimeException;

/**
 * Publica la calificación del alumno en el gradebook de Moodle vía AGS.
 *
 * CRITERIO DEL SCORE (decidido y documentado): se envía el MASTERY de la
 * destreza ×100 (media móvil exponencial del motor v2), no el
 * correcto/incorrecto del intento suelto. El libro de calificaciones debe
 * reflejar dominio acumulado; cada intento re-publica el valor actualizado,
 * y AGS es idempotente respecto del último score por usuario.
 *
 * Si la Platform no dio AGS (o sin el scope de score): no-op silencioso y
 * logueado. Errores de red o 4xx/5xx de la Platform: excepción → la cola
 * reintenta con backoff creciente.
 */
class PushLtiScore implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public readonly string $resourceLinkId) {}

    /** @return array<int, int> Backoff creciente entre reintentos (segundos). */
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function handle(LtiDatabase $db, LtiHttpConnector $connector): void
    {
        $link = LtiResourceLink::query()->with(['user', 'platform'])->find($this->resourceLinkId);
        if ($link === null || $link->objective_id === null) {
            return;   // el launch desapareció o no apuntaba a una destreza
        }

        $ags = $link->ags ?? [];
        if (empty($ags['scope']) || ! in_array(LtiConstants::AGS_SCOPE_SCORE, $ags['scope'], true)
            || (empty($ags['lineitem']) && empty($ags['lineitems']))) {
            Log::info('LTI AGS: la Platform no dio endpoint/scope de score; no se envía nada.', [
                'resource_link' => $link->resource_link_id,
            ]);

            return;   // no-op silencioso y logueado (misión fase D)
        }

        $mastery = ObjectiveMastery::query()
            ->where('user_id', $link->user_id)
            ->where('objective_id', $link->objective_id)
            ->first();
        if ($mastery === null) {
            return;   // sin intentos aún: nada que publicar
        }

        $registration = $db->findRegistrationByIssuer($link->platform->issuer, $link->platform->client_id);
        if ($registration === null) {
            Log::warning('LTI AGS: la Platform del resource link ya no está registrada/activa.', [
                'issuer' => $link->platform->issuer,
            ]);

            return;
        }

        $grade = LtiGrade::new()
            ->setScoreGiven(round($mastery->mastery * 100, 2))
            ->setScoreMaximum(100)
            ->setActivityProgress('Completed')
            ->setGradingProgress('FullyGraded')
            ->setTimestamp(($mastery->last_attempt_at ?? now())->toIso8601String())
            ->setUserId($link->user->lti_sub);

        $service = new LtiAssignmentsGradesService($connector, $registration, $ags);
        $response = $service->putGrade($grade);

        $status = $response['status'] ?? 0;
        if ($status >= 400) {
            // El cliente Http de Laravel no lanza en 4xx/5xx: se lanza aquí
            // para que la cola reintente con el backoff declarado.
            throw new RuntimeException("LTI AGS: la Platform rechazó el score (HTTP {$status}).");
        }
    }
}
