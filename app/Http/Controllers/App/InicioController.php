<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\LearningObjective;
use App\Models\ObjectiveMastery;
use App\Models\PracticeAttempt;
use App\Services\Catalog\AcentoDeAsignatura;
use App\Services\Practice\AdaptiveSelector;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * /inicio — la casa del alumno: dónde iba, qué toca ahora y cómo va.
 *
 * «Tu siguiente paso» NO reimplementa la política adaptativa: se la pide a
 * AdaptiveSelector, el MISMO servicio que usa el motor de práctica. Si algún
 * día alguien la duplica aquí, el test de divergencia lo caza.
 */
class InicioController extends Controller
{
    public function __construct(private readonly AdaptiveSelector $selector) {}

    public function __invoke(Request $request)
    {
        $userId = (int) $request->user()->id;

        $ultima = $this->ultimaDestrezaPracticada($userId);
        $masteries = $this->masteriesDe($userId, $ultima);

        return Inertia::render('inicio', [
            'continuar' => $ultima === null ? null : [
                'objective_id' => $ultima->id,
                'native_code' => $ultima->native_code,
                'statement' => $ultima->statement['es'] ?? '',
                'mastery' => (float) ($masteries['de_la_ultima'] ?? 0),
                'asignatura' => AcentoDeAsignatura::deNodo($ultima->node),
            ],
            'siguiente' => $this->siguientePaso($ultima, $userId),
            'resumen' => [
                'dominadas' => $masteries['dominadas'],
                'en_progreso' => $masteries['en_progreso'],
                'practicadas' => $masteries['practicadas'],
            ],
        ]);
    }

    /** La destreza del intento más reciente (orden estable ante empates de segundo). */
    private function ultimaDestrezaPracticada(int $userId): ?LearningObjective
    {
        $attempt = PracticeAttempt::query()
            ->where('user_id', $userId)
            ->with('item.objective')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        return $attempt?->item?->objective;
    }

    /**
     * La recomendación del selector, traducida a lo mínimo que necesita la
     * página: destreza y razón. Ni el ítem, ni la semilla, ni la solución.
     *
     * @return array{objective_id: string, native_code: ?string, statement: string, reason: string}|null
     */
    private function siguientePaso(?LearningObjective $ultima, int $userId): ?array
    {
        if ($ultima === null) {
            return null;
        }

        $decision = $this->selector->next($ultima, $userId);
        if ($decision === null) {
            return null;   // la destreza se quedó sin ítems: no hay paso que proponer
        }

        $objetivo = $decision['objective'];

        return [
            'objective_id' => $objetivo->id,
            'native_code' => $objetivo->native_code,
            'statement' => $objetivo->statement['es'] ?? '',
            'reason' => $decision['reason'],
            'asignatura' => AcentoDeAsignatura::deNodo($objetivo->node),
        ];
    }

    /**
     * Los conteos de dominio del alumno en UNA consulta (y de paso el mastery
     * de la última destreza, para no pedirlo aparte).
     *
     * @return array{dominadas: int, en_progreso: int, practicadas: int, de_la_ultima: ?float}
     */
    private function masteriesDe(int $userId, ?LearningObjective $ultima): array
    {
        $filas = ObjectiveMastery::query()
            ->where('user_id', $userId)
            ->get(['objective_id', 'mastery', 'mastered_at']);

        return [
            'dominadas' => $filas->whereNotNull('mastered_at')->count(),
            'en_progreso' => $filas->whereNull('mastered_at')->count(),
            'practicadas' => $filas->count(),
            'de_la_ultima' => $ultima === null
                ? null
                : $filas->firstWhere('objective_id', $ultima->id)?->mastery,
        ];
    }
}
