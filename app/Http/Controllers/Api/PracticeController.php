<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LearningObjective;
use App\Models\ObjectiveMastery;
use App\Models\PracticeAttempt;
use App\Models\PracticeItem;
use App\Models\Track;
use App\Services\Practice\AdaptiveSelector;
use App\Services\Practice\AttemptTicket;
use App\Services\Practice\MasteryTracker;
use App\Services\Practice\PracticeEngine;
use App\Services\Practice\RachaDeAlumno;
use App\Services\Practice\RepasoDiario;
use App\Services\Practice\RegistroDeIntento;
use App\Services\Practice\Practitioner;
use App\Services\Practice\RepasoService;
use App\Services\Practice\Tipos\Registro;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Motor de práctica: instanciación determinista y verificación en servidor.
 *
 * IDENTIDAD (deuda v1 CERRADA): el alumno es SIEMPRE el usuario autenticado
 * de la sesión (Auth::id()) — la sesión nace en el launch LTI. Un `user_id`
 * en el request es un 422 explícito (regla `prohibited`): mejor un cliente
 * desactualizado que grita que uno que suplanta en silencio.
 *
 * CONTENIDO ABIERTO (modelo Khan): estos endpoints ya no exigen sesión. Un
 * invitado pide ítems y recibe corrección real —la misma verificación en
 * servidor, el mismo PracticeEngine::verify()— pero NO escribe ni una fila:
 * ni intento, ni dominio, ni AGS. La sesión LTI es lo que hace que el avance
 * se guarde y que la nota viaje al aula. Quién es quién lo decide Practitioner.
 */
class PracticeController extends Controller
{
    public function __construct(
        private readonly PracticeEngine $engine,
        private readonly MasteryTracker $tracker,
        private readonly AdaptiveSelector $selector,
        private readonly RepasoService $repaso,
        private readonly RegistroDeIntento $registro,
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
        $data = $request->validate([
            'user_id' => 'prohibited',
            'intento' => 'nullable|integer|min:1|max:500',
            // Marca de REPASO: la pone la cola de repaso (`repaso=1`). Viaja al
            // billete firmado para que `submitAttempt` sepa que NO cuenta para
            // la nota AGS sin que el cliente pueda forjarlo para inflarla.
            'repaso' => 'nullable|boolean',
            // La lengua del contenido pedido, de LISTA CERRADA: fuera de ella
            // es 422, no una lengua nueva creada por un typo. Sin lengua solo
            // se sirve contenido sin lengua — cerrado en las dos direcciones.
            'lengua' => ['nullable', 'string', Rule::in(\App\Services\Practice\Lenguas::LISTA)],
        ]);
        $lengua = $data['lengua'] ?? null;
        $quien = Practitioner::fromRequest($request);

        if ($quien->isGuest()) {
            // El invitado NO pasa por el selector adaptativo. No es un atajo:
            // el selector decide mirando el historial del alumno, y un invitado
            // no tiene ninguno, así que consultándolo con un id que no casa con
            // nadie siempre devolvía el mismo ítem —el de menor `seq`— y dejaba
            // el resto del banco inalcanzable sin sesión (auditoría). Aquí rota
            // por número de intento, que es lo único que el invitado sí lleva,
            // sobre el MISMO orden estable que usa la rotación del alumno.
            // De paso, su ítem deja de depender de las filas de ningún usuario.
            $items = $this->selector->itemsOf($objective, $lengua);
            abort_if($items->isEmpty(), 404, 'El objetivo no tiene ítems de práctica');

            $attemptNo = (int) ($data['intento'] ?? 1);
            $item = $items[($attemptNo - 1) % $items->count()];
            $shown = $objective;
            $reason = AdaptiveSelector::REASON_NORMAL;
        } else {
            $selection = $this->selector->next($objective, $quien->queryId(), $lengua);
            abort_if($selection === null, 404, 'El objetivo no tiene ítems de práctica');

            $item = $selection['item'];
            $attemptNo = $selection['attempt_no'];
            // El objetivo DEVUELTO puede no ser el pedido: con las aristas de
            // prerrequisito intra-MINEDEC el selector desvía a un refuerzo o al
            // siguiente escalón. El cliente necesita saber a qué destreza
            // pertenece el ítem para no rotular el ejercicio con la destreza
            // equivocada (nada sensible: código y enunciado son públicos).
            $shown = $selection['objective'];
            $reason = $selection['reason'];
        }

        $seed = $this->engine->seedFor($item->id, $quien->seedKey(), $attemptNo);

        // Lo específico de cada tipo lo declara SU clase (Tipos\*::payload),
        // elegida por el Registro: el controlador no sabe cuántos tipos hay ni
        // qué forma tienen. Cada payload es lista blanca campo a campo, y la
        // solución no está porque ningún tipo la pone — no porque alguien la
        // quite aquí.
        $propio = Registro::de($item->kind)->payload($item, $this->engine, $seed);

        return response()->json([
            'item_id' => $item->id,
            'kind' => $item->kind,
            'objective_id' => $shown->id,
            'objective_code' => $shown->native_code,
            'objective_statement' => $shown->statement['es'] ?? null,
            'attempt_no' => $attemptNo,
            // EL BILLETE. Firmado, y con el número de intento y la semilla
            // con la que se instanciaron estos números dentro. Al responder, el
            // servidor corrige contra ESTO y no contra un recuento de filas
            // hecho un segundo más tarde, que es lo que hacía que un alumno se
            // corrigiera contra números que no vio nunca.
            'billete' => AttemptTicket::emitir(
                $item->id, $quien->seedKey(), $attemptNo, $seed,
                repaso: (bool) ($data['repaso'] ?? false),
            ),
            ...$propio,
            'reason' => $reason,
            // El cliente NO puede fiarse de la prop `auth` para saber si esto se
            // guarda: se renderizó cuando la sesión aún vivía. Si caducó a media
            // práctica, aquí llega `false` y la página lo dice (auditoría).
            'se_guarda' => ! $quien->isGuest(),
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
        // Lo que el tipo exige y lo que prohíbe, el billete y qué pasa al
        // responder viven en `RegistroDeIntento`: son las MISMAS piezas que usa
        // la prueba de unidad. Aquí solo queda la forma HTTP.
        $data = $request->validate([
            'user_id' => 'prohibited',
            ...$this->registro->reglasDe($item),
            'time_ms' => 'nullable|integer|min:0',
            // El número de intento viene firmado dentro del billete. Dos
            // fuentes para el mismo dato es la forma exacta en que empezó el
            // fallo que el billete cerró.
            'intento' => 'prohibited',
            // Y qué vuelta del bucle de «otra vez» es, también: viene FIRMADO
            // en el billete. Un `reintento: false` en el cuerpo es 422, no un
            // intento que cuenta.
            'reintento' => 'prohibited',
            'billete' => 'required|string',
        ]);
        $quien = Practitioner::fromRequest($request);
        $ticket = $this->registro->abrirBillete($data['billete'], $item, $quien);

        try {
            ['veredicto' => $veredicto, 'attempt' => $attempt] = $this->registro->procesar($item, $data, $ticket, $quien);
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'Intento duplicado: otra petición registró este intento primero. Pide el siguiente ítem y reintenta.',
            ], 409);
        }

        // El bucle de «otra vez» —pista sin solución y billete del reintento
        // mientras quede vuelta— lo decide el servidor, en un sitio.
        $veredicto = $this->registro->bucle($item, $veredicto, $ticket, $quien->seedKey());

        if ($attempt === null) {
            // 200 y no 201: no se creó nada. Ni intento, ni dominio, ni AGS —
            // la regla de oro del contenido abierto. `se_guarda` viaja para que
            // la interfaz no tenga que adivinarlo por ausencia de sesión.
            return response()->json([
                'attempt_no' => $ticket['attempt_no'],
                ...$veredicto,
                'se_guarda' => false,
            ]);
        }

        // La explicación —`expected` o `expected_key`— se revela solo DESPUÉS
        // de responder; el siguiente intento trae números nuevos (o una
        // barajada nueva), así que no regala nada.
        return response()->json([
            'id' => $attempt->id,
            'attempt_no' => $ticket['attempt_no'],
            ...$veredicto,
            'se_guarda' => true,
        ], 201);
    }

    /**
     * GET /api/v1/practice/repasos?lengua=it — la cola de repaso del alumno.
     *
     * Abierto como el resto: el invitado recibe una cola VACÍA (nunca la de
     * otro) y no escribe nada. La lengua es de lista cerrada.
     */
    public function repasos(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'prohibited',
            'lengua' => ['required', 'string', Rule::in(\App\Services\Practice\Lenguas::LISTA)],
        ]);
        $quien = Practitioner::fromRequest($request);

        return response()->json([
            ...$this->repaso->cola($quien->userId(), $data['lengua']),
            'se_guarda' => ! $quien->isGuest(),
        ]);
    }

    /**
     * GET /api/v1/practice/repaso-diario?lengua=it — «tu repaso de hoy».
     *
     * Hasta diez ítems por prioridad (vencidos del repaso espaciado, fallos
     * recientes, relleno), cada uno con su billete firmado — con `repaso: true`
     * en los dos primeros grupos para que cuenten para el dominio y no para la
     * nota. Se juega COMO LA PRÁCTICA: cada respuesta va al endpoint de
     * intentos de siempre. Abierto: el invitado recibe solo relleno.
     */
    public function repasoDiario(Request $request, RepasoDiario $diario, RachaDeAlumno $racha)
    {
        $data = $request->validate([
            'user_id' => 'prohibited',
            'lengua' => ['required', 'string', Rule::in(\App\Services\Practice\Lenguas::LISTA)],
        ]);
        $quien = Practitioner::fromRequest($request);
        $lengua = $data['lengua'];

        $items = $diario->componer($quien->userId(), $lengua)->map(function (array $e) use ($quien) {
            $item = $e['item'];
            // El mismo número de intento que calcularía `next`: el siguiente al
            // último del alumno para ESE ítem. El invitado no tiene historial.
            $attemptNo = $quien->isGuest() ? 1 : PracticeAttempt::query()
                ->where('item_id', $item->id)->where('user_id', $quien->userId())->count() + 1;
            $seed = $this->engine->seedFor($item->id, $quien->seedKey(), $attemptNo);

            return [
                'item_id' => $item->id,
                'kind' => $item->kind,
                'objective_id' => $item->objective_id,
                'objective_code' => $item->objective?->native_code,
                'objective_statement' => $item->objective?->statement['es'] ?? null,
                'attempt_no' => $attemptNo,
                'prioridad' => $e['prioridad'],
                'repaso' => $e['repaso'],
                'billete' => AttemptTicket::emitir($item->id, $quien->seedKey(), $attemptNo, $seed, $e['repaso']),
                ...Registro::de($item->kind)->payload($item, $this->engine, $seed),
            ];
        })->values();

        return response()->json([
            'lengua' => $lengua,
            'fecha' => now(RachaDeAlumno::ZONA)->toDateString(),
            'total' => $items->count(),
            'items' => $items,
            'racha' => [...$racha->calcular($quien->userId()), 'activo_hoy' => $racha->activoHoy($quien->userId())],
            'se_guarda' => ! $quien->isGuest(),
        ]);
    }

    /** GET /api/v1/practice/racha — la racha del alumno de la sesión (el invitado: cero). */
    public function racha(Request $request, RachaDeAlumno $racha)
    {
        $request->validate(['user_id' => 'prohibited']);
        $quien = Practitioner::fromRequest($request);

        return response()->json([
            ...$racha->calcular($quien->userId()),
            'activo_hoy' => $racha->activoHoy($quien->userId()),
            'se_guarda' => ! $quien->isGuest(),
        ]);
    }

    /**
     * GET /api/v1/practice/mastery — el dominio POR DESTREZA del alumno de la
     * sesión (no existe parámetro para pedir el de otro).
     * Orden estable: última práctica primero, con desempate por objective_id.
     */
    public function mastery(Request $request)
    {
        $request->validate(['user_id' => 'prohibited']);
        $quien = Practitioner::fromRequest($request);

        // Un invitado no tiene dominio guardado: lista vacía. Nunca el de otro
        // —el id de consulta es 0, que no casa con ningún usuario— y nunca un
        // 401 que rompa el bucle de práctica abierta.
        if ($quien->isGuest()) {
            return response()->json([]);
        }

        return ObjectiveMastery::query()
            ->where('user_id', $quien->userId())
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

        $quien = Practitioner::fromRequest($request);

        // El invitado ve la FORMA del trayecto —cuántas destrezas trae cada
        // fase— con su avance a cero, no el de nadie. La consulta ni se lanza:
        // así el «se filtró el dominio de otro» no depende de que un WHERE esté
        // bien escrito, sino de que no haya consulta que filtrar.
        $masteries = $quien->isGuest()
            ? collect()
            : ObjectiveMastery::query()
                ->where('user_id', $quien->userId())
                ->whereIn('objective_id', $links->pluck('objective_id')->unique())
                ->get()
                ->keyBy('objective_id');

        $byPhase = $links->groupBy('phase_id');

        return response()->json([
            'track' => $track->code,
            'se_guarda' => ! $quien->isGuest(),
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
