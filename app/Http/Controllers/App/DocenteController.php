<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\LtiContext;
use App\Models\ObjectiveMastery;
use App\Models\Track;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * El panel del docente (misión vista-docente). AUTORIZACIÓN DURA en cada
 * entrada: solo quien tiene membership `instructor` en ESE contexto — la
 * identidad es la sesión y la autorización es la membership en BD, jamás un
 * id del request. Privacidad: de los alumnos viajan id y nombre, punto.
 */
class DocenteController extends Controller
{
    /** GET /docente/{context} — el curso, su track y cómo van los alumnos. */
    public function panel(Request $request, LtiContext $context)
    {
        abort_unless($context->esInstructor((int) $request->user()->id), 403,
            'Solo el docente de este curso puede ver su panel.');

        $track = $context->track;
        $objectiveIds = $track === null ? collect() : $this->objectivesDelTrack($track);

        return Inertia::render('docente', [
            'context' => [
                'id' => $context->id,
                'title' => $context->title,
                'label' => $context->label,
            ],
            'track' => $track === null ? null : [
                'id' => $track->id,
                'code' => $track->code,
                'label' => $track->label['es'] ?? $track->code,
            ],
            'tracks' => Track::query()->orderBy('code')->get()
                ->map(fn (Track $t) => [
                    'id' => $t->id, 'code' => $t->code, 'label' => $t->label['es'] ?? $t->code,
                ])->values(),
            'objectives_summary' => $track === null ? null : [
                'total' => $objectiveIds->count(),
                'con_items' => $objectiveIds->isEmpty() ? 0 : DB::table('practice_items')
                    ->whereIn('objective_id', $objectiveIds)
                    ->distinct()->count('objective_id'),
            ],
            'students' => $this->students($context, $objectiveIds),
        ]);
    }

    /** POST /docente/{context}/track — asignar (o corregir) el mapeo curso→track. */
    public function asignarTrack(Request $request, LtiContext $context)
    {
        abort_unless($context->esInstructor((int) $request->user()->id), 403,
            'Solo el docente de este curso puede asignar su track.');

        $data = $request->validate(['track_id' => 'required|uuid|exists:tracks,id']);

        $context->update(['track_id' => $data['track_id']]);

        return redirect()->route('docente', $context);
    }

    /**
     * GET /docente/{context}/alumno/{user} — el detalle expandible: mastery
     * por destreza del track. Doble candado: instructor del contexto Y el
     * alumno pedido es learner DE ESE contexto (nada de mirar otros cursos).
     */
    public function alumno(Request $request, LtiContext $context, User $user)
    {
        abort_unless($context->esInstructor((int) $request->user()->id), 403,
            'Solo el docente de este curso puede ver a sus alumnos.');
        abort_unless(
            $context->memberships()->where('user_id', $user->id)->where('role', 'learner')->exists(),
            404,
            'Ese alumno no pertenece a este curso.',
        );

        $track = $context->track;
        if ($track === null) {
            return response()->json(['destrezas' => []]);
        }

        $objectiveIds = $this->objectivesDelTrack($track);
        $masteries = ObjectiveMastery::query()
            ->where('user_id', $user->id)
            ->whereIn('objective_id', $objectiveIds)
            ->get()
            ->keyBy('objective_id');

        $destrezas = DB::table('learning_objectives')
            ->whereIn('id', $objectiveIds)
            ->orderByRaw('LENGTH(native_code)')->orderBy('native_code')
            ->get(['id', 'native_code'])
            ->map(fn ($o) => [
                'native_code' => $o->native_code,
                'mastery' => ($masteries[$o->id] ?? null)?->mastery ?? 0,
                'is_mastered' => ($masteries[$o->id] ?? null)?->mastered_at !== null,
            ])->values();

        return response()->json(['destrezas' => $destrezas]);
    }

    /** Las destrezas que cubren las fases del track (ids únicos). */
    private function objectivesDelTrack(Track $track)
    {
        return DB::table('track_phase_objectives')
            ->join('track_phases', 'track_phases.id', '=', 'track_phase_objectives.phase_id')
            ->where('track_phases.track_id', $track->id)
            ->distinct()
            ->pluck('track_phase_objectives.objective_id');
    }

    /**
     * La tabla de alumnos: learners del contexto con sus conteos contra el
     * track, en DOS consultas (memberships+users, masteries agregadas) — el
     * coste no crece con el número de alumnos. Orden: más rezagados primero
     * (menos dominadas, luego menos en progreso, luego nombre).
     */
    private function students(LtiContext $context, $objectiveIds)
    {
        $members = $context->memberships()
            ->where('role', 'learner')
            ->with('user:id,name')
            ->get();

        $porUsuario = collect();
        if ($objectiveIds->isNotEmpty() && $members->isNotEmpty()) {
            $porUsuario = ObjectiveMastery::query()
                ->whereIn('user_id', $members->pluck('user_id'))
                ->whereIn('objective_id', $objectiveIds)
                ->selectRaw('user_id')
                ->selectRaw('sum(case when mastered_at is not null then 1 else 0 end) as dominadas')
                ->selectRaw('sum(case when mastered_at is null then 1 else 0 end) as en_progreso')
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id');
        }

        $total = $objectiveIds->count();

        return $members
            ->map(function ($m) use ($porUsuario, $total) {
                $fila = $porUsuario[$m->user_id] ?? null;
                $dominadas = (int) ($fila->dominadas ?? 0);
                $enProgreso = (int) ($fila->en_progreso ?? 0);

                return [
                    'id' => $m->user_id,
                    'name' => $m->user?->name ?? 'Alumno',
                    'dominadas' => $dominadas,
                    'en_progreso' => $enProgreso,
                    'sin_empezar' => max(0, $total - $dominadas - $enProgreso),
                    'last_launched_at' => $m->last_launched_at?->toIso8601String(),
                ];
            })
            ->sort(fn ($a, $b) => [$a['dominadas'], $a['en_progreso'], $a['name']]
                <=> [$b['dominadas'], $b['en_progreso'], $b['name']])
            ->values();
    }
}
