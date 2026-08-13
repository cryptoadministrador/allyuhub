<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\PushLtiScore;
use App\Models\LearningObjective;
use App\Models\LtiResourceLink;
use App\Models\ObjectiveMastery;
use App\Models\PracticeItem;
use App\Models\Track;
use App\Services\Practice\AdaptiveSelector;
use App\Services\Practice\MasteryTracker;
use App\Services\Practice\PracticeEngine;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Motor de práctica: instanciación determinista y verificación en servidor.
 *
 * IDENTIDAD (deuda v1 CERRADA): el alumno es SIEMPRE el usuario autenticado
 * de la sesión (Auth::id()) — la sesión nace en el launch LTI. Un `user_id`
 * en el request es un 422 explícito (regla `prohibited`): mejor un cliente
 * desactualizado que grita que uno que suplanta en silencio.
 */
class PracticeController extends Controller
{
    public function __construct(
        private readonly PracticeEngine $engine,
        private readonly MasteryTracker $tracker,
        private readonly AdaptiveSelector $selector,
    ) {}

    /**
     * GET /api/v1/objectives/{objective}/practice/next
     *
     * Delegado en AdaptiveSelector: refuerzo de prerrequisito, avance o práctica
     * normal (`reason` lo explica). El ítem elegido se instancia con la semilla
     * v1 hash(item:user:intento): mientras el alumno no responda, repetir la
     * petición devuelve exactamente los mismos números (misma semilla).
     * Nunca expone solution_expr ni el valor esperado.
     */
    public function next(LearningObjective $objective, Request $request)
    {
        $request->validate(['user_id' => 'prohibited']);
        $userId = (int) $request->user()->id;

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
     * POST /api/v1/practice/items/{item}/attempts — {answer, time_ms?}
     *
     * El servidor re-deriva la semilla del intento en curso, re-instancia los
     * parámetros y evalúa la expresión de solución con tolerancia. Cualquier
     * `is_correct`/`expected` que envíe el cliente se ignora por diseño.
     */
    public function submitAttempt(PracticeItem $item, Request $request)
    {
        $data = $request->validate([
            'user_id' => 'prohibited',
            'answer' => 'required|numeric',
            'time_ms' => 'nullable|integer|min:0',
        ]);
        $userId = (int) $request->user()->id;

        $attemptNo = $item->attempts()->where('user_id', $userId)->count() + 1;
        $seed = $this->engine->seedFor($item->id, $userId, $attemptNo);
        $params = $this->engine->sampleParams($item->params, $seed);
        $result = $this->engine->verify(
            $item->solution_expr, $params, (float) $data['answer'],
            $item->tolerance, $item->tolerance_kind,
        );

        // Intento + actualización de mastery en la MISMA transacción:
        // o quedan los dos, o ninguno. Si dos peticiones simultáneas calcularon el
        // mismo attempt_no (unique por ítem+usuario), la perdedora responde 409 y el
        // cliente reintenta — nunca un 500.
        try {
            $attempt = $this->persistAttempt($item, $userId, $attemptNo, $seed, $params, $data, $result);
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'Intento duplicado: otra petición registró este intento primero. Pide el siguiente ítem y reintenta.',
            ], 409);
        }

        // Si el alumno llegó por LTI con AGS, se re-publica su mastery en el
        // gradebook de Moodle (cola con backoff; una consulta fija por intento).
        // $userId ES el usuario autenticado — el cinturón defensivo de la
        // auditoría LTI sobra desde que la ruta exige auth.
        $this->queueLtiScore($userId, $item->objective_id);

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

    /** Despacha el push AGS si el alumno tiene un resource link LTI para esta destreza. */
    private function queueLtiScore(int $userId, string $objectiveId): void
    {
        $linkId = LtiResourceLink::query()
            ->where('user_id', $userId)
            ->where('objective_id', $objectiveId)
            ->orderByDesc('last_launched_at')
            ->value('id');

        if ($linkId !== null) {
            PushLtiScore::dispatch($linkId);
        }
    }

    private function persistAttempt($item, int $userId, int $attemptNo, string $seed, array $params, array $data, array $result)
    {
        return DB::transaction(function () use ($item, $userId, $attemptNo, $seed, $params, $data, $result) {
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
    }

    /**
     * GET /api/v1/practice/mastery — el dominio POR DESTREZA del alumno de la
     * sesión (no existe parámetro para pedir el de otro).
     * Orden estable: última práctica primero, con desempate por objective_id.
     */
    public function mastery(Request $request)
    {
        $request->validate(['user_id' => 'prohibited']);

        return ObjectiveMastery::query()
            ->where('user_id', (int) $request->user()->id)
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
     * GET /api/v1/practice/progress?track=… — resumen por fase del track PARA
     * EL ALUMNO DE LA SESIÓN: destrezas dominadas / en progreso / no iniciadas.
     * Consultas acotadas (fases, enlaces y masteries en bulk): el coste no
     * crece con el número de fases ni de destrezas.
     */
    public function progress(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'prohibited',
            'track' => 'required|string|exists:tracks,code',
        ]);

        $track = Track::where('code', $data['track'])->first();
        $phases = $track->phases;   // ya ordenadas por seq (relación)

        $links = DB::table('track_phase_objectives')
            ->whereIn('phase_id', $phases->pluck('id'))
            ->get(['phase_id', 'objective_id']);

        $masteries = ObjectiveMastery::query()
            ->where('user_id', (int) $request->user()->id)
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
