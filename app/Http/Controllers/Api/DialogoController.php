<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dialogo;
use App\Models\DialogoCompletion;
use App\Services\Practice\MasteryTracker;
use App\Services\Practice\Practitioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * EL INTERLOCUTOR registra que se completó — y NADA MÁS. No evalúa: no hay nota,
 * no hay «bien/mal». Completar el guion es evidencia de la interacción, así que
 * sube el dominio del descriptor (A1.IO.1) UNA vez.
 *
 * ABIERTO como la práctica (modelo Khan): un invitado puede hacer el diálogo
 * entero, pero al completarlo NO escribe ni una fila ni sube dominio de nadie —
 * la regla de oro. La identidad es siempre la sesión; un `user_id` en el
 * request es 422.
 */
class DialogoController extends Controller
{
    public function __construct(private readonly MasteryTracker $tracker) {}

    /** POST /api/v1/dialogos/{dialogo}/completado */
    public function completado(Request $request, Dialogo $dialogo)
    {
        $request->validate(['user_id' => 'prohibited']);

        // No se completa lo que no está firmado: si no se sirve, no se registra.
        // Misma noción que `published()`, escrita una vez (Dialogo::estaFirmado).
        abort_if(! $dialogo->estaFirmado(), 404);

        $quien = Practitioner::fromRequest($request);

        if ($quien->isGuest()) {
            // Ni fila ni dominio: hizo el diálogo, no se guarda.
            return response()->json(['se_guarda' => false, 'ya_completado' => false]);
        }

        $userId = $quien->userId();
        $nuevo = false;

        DB::transaction(function () use ($dialogo, $userId, &$nuevo) {
            $completion = DialogoCompletion::firstOrCreate(
                ['dialogo_id' => $dialogo->id, 'user_id' => $userId],
                ['completed_at' => now()],
            );
            $nuevo = $completion->wasRecentlyCreated;

            // Solo la PRIMERA vez mueve el dominio: completar dos veces no infla.
            // itemsAcertados = 0 a propósito: mueve la EMA pero NUNCA sella
            // mastered_at por sí solo (el hito exige ≥2 ítems distintos), que es
            // justo lo que pide «registra que se completó, nada más».
            if ($nuevo) {
                $this->tracker->apply($userId, $dialogo->objective_id, true, 0, now());
            }
        });

        return response()->json(['se_guarda' => true, 'ya_completado' => ! $nuevo]);
    }
}
