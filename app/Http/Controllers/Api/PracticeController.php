<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LearningObjective;
use App\Models\ObjectiveMastery;
use App\Models\PracticeItem;
use App\Models\Track;
use App\Services\Practice\AdaptiveSelector;
use App\Services\Practice\MasteryTracker;
use App\Services\Practice\PracticeEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Motor de práctica: instanciación determinista y verificación en servidor.
 *
 * PROVISIONAL: el alumno se identifica con `user_id` en la petición porque el
 * repo aún no tiene autenticación; cuando llegue LTI 1.3 (roadmap ítem 5) el
 * usuario saldrá del token (lti_iss + lti_sub), nunca del payload.
 */
class PracticeController extends Controller
{
    public function __construct(
        private readonly PracticeEngine $engine,
        private readonly MasteryTracker $tracker,
        private readonly AdaptiveSelector $selector,
    ) {}

    /**
     * GET /api/v1/objectives/{objective}/practice/next?user_id=…
     *
     * Delegado en AdaptiveSelector: refuerzo de prerrequisito, avance o práctica
     * normal (`reason` lo explica). El ítem elegido se instancia con la semilla
     * v1 hash(item:user:intento): mientras el alumno no responda, repetir la
     * petición devuelve exactamente los mismos números (misma semilla).
     * Nunca expone solution_expr ni el valor esperado.
     */
    public function next(LearningObjective $objective, Request $request)
    {
        $data = $request->validate(['user_id' => 'required|integer|exists:users,id']);
        $userId = (int) $data['user_id'];

        $selection = $this->selector->next($objective, $userId);
        abort_if($selection === null, 404, 'El objetivo no tiene ítems de práctica');

        $item = $selection['item'];
        $attemptNo = $selection['attempt_no'];
        $seed = $this->engine->seedFor($item->id, $userId, $attemptNo);
        $params = $this->engine->sampleParams($item->params, $seed);

        return response()->json([
            'item_id' => $item->id,
            'objective_id' => $selection['objective']->id,
            'attempt_no' => $attemptNo,
            'statement' => $this->engine->renderStatement($item->statement, $params),
            'params' => $params,
            'answer_unit' => $item->answer_unit,
            'tolerance' => $item->tolerance,
            'tolerance_kind' => $item->tolerance_kind,
            'reason' => $selection['reason'],
        ]);
    }

    /**
     * POST /api/v1/practice/items/{item}/attempts — {user_id, answer, time_ms?}
     *
     * El servidor re-deriva la semilla del intento en curso, re-instancia los
     * parámetros y evalúa la expresión de solución con tolerancia. Cualquier
     * `is_correct`/`expected` que envíe el cliente se ignora por diseño.
     */
    public function submitAttempt(PracticeItem $item, Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'answer' => 'required|numeric',
            'time_ms' => 'nullable|integer|min:0',
        ]);
        $userId = (int) $data['user_id'];

        $attemptNo = $item->attempts()->where('user_id', $userId)->count() + 1;
        $seed = $this->engine->seedFor($item->id, $userId, $attemptNo);
        $params = $this->engine->sampleParams($item->params, $seed);
        $result = $this->engine->verify(
            $item->solution_expr, $params, (float) $data['answer'],
            $item->tolerance, $item->tolerance_kind,
        );

        // Intento + actualización de mastery en la MISMA transacción:
        // o quedan los dos, o ninguno.
        $attempt = DB::transaction(function () use ($item, $userId, $attemptNo, $seed, $params, $data, $result) {
            $attempt = $item->attempts()->create([
                'user_id' => $userId,
                'attempt_no' => $attemptNo,
                'seed' => $seed,
                'params' => $params,
                'answer' => (float) $data['answer'],
                'expected' => $result['expected'],
                'is_correct' => $result['is_correct'],
                'time_ms' => $data['time_ms'] ?? null,
            ]);

            $this->tracker->apply($userId, $item->objective_id, $result['is_correct'], $attempt->created_at);

            return $attempt;
        });

        // `expected` se revela solo DESPUÉS de responder (retroalimentación);
        // el siguiente intento trae números nuevos, así que no regala nada.
        return response()->json([
            'id' => $attempt->id,
            'attempt_no' => $attemptNo,
            'is_correct' => $result['is_correct'],
            'expected' => $result['expected'],
            'answer' => $attempt->answer,
        ], 201);
    }

    /**
     * GET /api/v1/practice/mastery?user_id=… — estado de dominio por destreza.
     * Orden estable: última práctica primero, con desempate por objective_id.
     */
    public function mastery(Request $request)
    {
        $data = $request->validate(['user_id' => 'required|integer|exists:users,id']);

        return ObjectiveMastery::query()
            ->where('user_id', (int) $data['user_id'])
            ->with('objective:id,native_code,statement,version_id')
            ->orderByDesc('last_attempt_at')
            ->orderBy('objective_id')
            ->get()
            ->map(fn (ObjectiveMastery $m) => [
                'objective_id' => $m->objective_id,
                'native_code' => $m->objective?->native_code,
                'statement' => $m->objective?->statement,
                'mastery' => $m->mastery,
                'streak' => $m->streak,
                'attempts_count' => $m->attempts_count,
                'is_mastered' => $m->is_mastered,
                'mastered_at' => $m->mastered_at,
                'last_attempt_at' => $m->last_attempt_at,
            ]);
    }

    /**
     * GET /api/v1/practice/progress?user_id=…&track=… — resumen por fase del
     * track: destrezas dominadas / en progreso / no iniciadas. Consultas
     * acotadas (fases, enlaces y masteries en bulk): el coste no crece con el
     * número de fases ni de destrezas.
     */
    public function progress(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'track' => 'required|string|exists:tracks,code',
        ]);

        $track = Track::where('code', $data['track'])->first();
        $phases = $track->phases;   // ya ordenadas por seq (relación)

        $links = DB::table('track_phase_objectives')
            ->whereIn('phase_id', $phases->pluck('id'))
            ->get(['phase_id', 'objective_id']);

        $masteries = ObjectiveMastery::query()
            ->where('user_id', (int) $data['user_id'])
            ->whereIn('objective_id', $links->pluck('objective_id')->unique())
            ->get()
            ->keyBy('objective_id');

        $byPhase = $links->groupBy('phase_id');

        return response()->json([
            'track' => $track->code,
            'phases' => $phases->map(function ($phase) use ($byPhase, $masteries) {
                $objectiveIds = ($byPhase[$phase->id] ?? collect())->pluck('objective_id');
                $mastered = $objectiveIds
                    ->filter(fn ($id) => ($masteries[$id] ?? null)?->mastered_at !== null)->count();
                $started = $objectiveIds->filter(fn ($id) => isset($masteries[$id]))->count();

                return [
                    'phase_id' => $phase->id,
                    'seq' => $phase->seq,
                    'label' => $phase->label,
                    'is_propedeutic' => $phase->is_propedeutic,
                    'objectives_total' => $objectiveIds->count(),
                    'mastered' => $mastered,
                    'in_progress' => $started - $mastered,
                    'not_started' => $objectiveIds->count() - $started,
                ];
            })->values(),
        ]);
    }
}
