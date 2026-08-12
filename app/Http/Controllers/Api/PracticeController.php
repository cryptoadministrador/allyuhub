<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LearningObjective;
use App\Models\PracticeAttempt;
use App\Models\PracticeItem;
use App\Services\Practice\PracticeEngine;
use Illuminate\Http\Request;

/**
 * Motor de práctica: instanciación determinista y verificación en servidor.
 *
 * PROVISIONAL: el alumno se identifica con `user_id` en la petición porque el
 * repo aún no tiene autenticación; cuando llegue LTI 1.3 (roadmap ítem 5) el
 * usuario saldrá del token (lti_iss + lti_sub), nunca del payload.
 */
class PracticeController extends Controller
{
    public function __construct(private readonly PracticeEngine $engine) {}

    /**
     * GET /api/v1/objectives/{objective}/practice/next?user_id=…
     *
     * Devuelve el ítem menos practicado del objetivo, instanciado con la semilla
     * hash(item:user:intento). Mientras el alumno no responda, repetir la
     * petición devuelve exactamente los mismos números (misma semilla).
     * Nunca expone solution_expr ni el valor esperado.
     */
    public function next(LearningObjective $objective, Request $request)
    {
        $data = $request->validate(['user_id' => 'required|integer|exists:users,id']);
        $userId = (int) $data['user_id'];

        $items = $objective->practiceItems()
            ->orderBy('seq')->orderBy('created_at')->orderBy('id')
            ->get();
        abort_if($items->isEmpty(), 404, 'El objetivo no tiene ítems de práctica');

        $counts = PracticeAttempt::query()
            ->whereIn('item_id', $items->pluck('id'))
            ->where('user_id', $userId)
            ->selectRaw('item_id, count(*) as total')
            ->groupBy('item_id')
            ->pluck('total', 'item_id');

        // El menos practicado; a igualdad, el orden estable (seq) decide.
        $minCount = $items->map(fn ($i) => $counts[$i->id] ?? 0)->min();
        $item = $items->first(fn ($i) => ($counts[$i->id] ?? 0) === $minCount);

        $attemptNo = ($counts[$item->id] ?? 0) + 1;
        $seed = $this->engine->seedFor($item->id, $userId, $attemptNo);
        $params = $this->engine->sampleParams($item->params, $seed);

        return response()->json([
            'item_id' => $item->id,
            'objective_id' => $objective->id,
            'attempt_no' => $attemptNo,
            'statement' => $this->engine->renderStatement($item->statement, $params),
            'params' => $params,
            'answer_unit' => $item->answer_unit,
            'tolerance' => $item->tolerance,
            'tolerance_kind' => $item->tolerance_kind,
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
}
