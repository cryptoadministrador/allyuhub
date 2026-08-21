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
use App\Services\Practice\Practitioner;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
        ]);
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
            $items = $this->selector->itemsOf($objective);
            abort_if($items->isEmpty(), 404, 'El objetivo no tiene ítems de práctica');

            $attemptNo = (int) ($data['intento'] ?? 1);
            $item = $items[($attemptNo - 1) % $items->count()];
            $shown = $objective;
            $reason = AdaptiveSelector::REASON_NORMAL;
        } else {
            $selection = $this->selector->next($objective, $quien->queryId());
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

        // Lo específico de cada tipo se arma aparte y se FUSIONA al final: así
        // el payload de un `choice` no arrastra campos numéricos vacíos, y
        // sobre todo se ve de un vistazo que la marca de «correcta» no está en
        // ninguna de las dos ramas.
        if ($item->esChoice()) {
            $opciones = $item->shuffle
                ? $this->engine->shuffleOptions($item->opciones(), $seed)
                : $item->opciones();

            $propio = [
                'statement' => $item->statement,
                // LISTA BLANCA campo a campo: solo clave y texto. Nada de
                // volcar la opción entera —hoy no hay más campos, pero el día
                // que alguien añada uno no se publicará por descuido.
                'options' => array_map(fn (array $o) => [
                    'key' => (string) $o['key'],
                    'text' => $o['text'],
                ], $opciones),
            ];
        } else {
            $params = $this->engine->sampleParams($item->params, $seed);
            $propio = [
                'statement' => $this->engine->renderStatement($item->statement, $params),
                'params' => $params,
                'answer_unit' => $item->answer_unit,
                'tolerance' => $item->tolerance,
                'tolerance_kind' => $item->tolerance_kind,
            ];
        }

        return response()->json([
            'item_id' => $item->id,
            'kind' => $item->kind,
            'objective_id' => $shown->id,
            'objective_code' => $shown->native_code,
            'objective_statement' => $shown->statement['es'] ?? null,
            'attempt_no' => $attemptNo,
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
        // Cada tipo exige LO SUYO y prohíbe lo del otro: responder un texto con
        // un número (o al revés) es un cliente equivocado, y vale más que grite
        // un 422 a que se registre un fallo que el alumno no cometió.
        $esChoice = $item->esChoice();

        $data = $request->validate([
            'user_id' => 'prohibited',
            'answer' => $esChoice ? 'prohibited' : 'required|numeric',
            // La clave tiene que ser UNA DE LAS DEL ÍTEM. Una inventada es un
            // 422, no un falso «incorrecto»: el alumno no ha fallado nada.
            'answer_key' => $esChoice
                ? ['required', 'string', Rule::in($item->clavesValidas())]
                : 'prohibited',
            'time_ms' => 'nullable|integer|min:0',
            'intento' => 'nullable|integer|min:1|max:500',
        ]);
        $quien = Practitioner::fromRequest($request);

        $attemptNo = $quien->isGuest()
            ? (int) ($data['intento'] ?? 1)
            : $item->attempts()->where('user_id', $quien->userId())->count() + 1;
        $seed = $this->engine->seedFor($item->id, $quien->seedKey(), $attemptNo);

        // LA CORRECCIÓN ES LA MISMA para el invitado y para el alumno: se
        // resuelve aquí, por encima de la bifurcación. Lo único que cambia más
        // abajo es si el resultado se guarda y si califica.
        if ($esChoice) {
            // Ni semilla ni orden: se compara la clave elegida con la clave
            // correcta del ítem. Por eso el camino `choice` es inmune a que
            // `next` y `submitAttempt` calculen distinto el número de intento
            // —y por tanto la semilla—, que es un problema real del camino
            // numérico (ver la nota del PR sobre el 409 y el reintento).
            $result = $this->engine->verifyChoice(
                (string) $item->answer_key, (string) $data['answer_key'],
            );
            // Nada que instanciar: en un choice no se sortea ningún número.
            $params = [];
            $veredicto = [
                'is_correct' => $result['is_correct'],
                'expected_key' => $result['expected_key'],
                'answer_key' => (string) $data['answer_key'],
            ];
        } else {
            $params = $this->engine->sampleParams($item->params, $seed);
            $result = $this->engine->verify(
                $item->solution_expr, $params, (float) $data['answer'],
                $item->tolerance, $item->tolerance_kind,
            );
            $veredicto = [
                'is_correct' => $result['is_correct'],
                'expected' => $result['expected'],
                'answer' => (float) $data['answer'],
            ];
        }

        if ($quien->isGuest()) {
            // 200 y no 201: no se creó nada. Ni intento, ni dominio, ni AGS —
            // la regla de oro del contenido abierto. `se_guarda` viaja para que
            // la interfaz no tenga que adivinarlo por ausencia de sesión.
            return response()->json([
                'attempt_no' => $attemptNo,
                ...$veredicto,
                'se_guarda' => false,
            ]);
        }

        $userId = $quien->userId();

        // Intento + actualización de mastery en la MISMA transacción:
        // o quedan los dos, o ninguno. Si dos peticiones simultáneas calcularon el
        // mismo attempt_no (unique por ítem+usuario), la perdedora responde 409 y el
        // cliente reintenta — nunca un 500.
        try {
            $attempt = $this->persistAttempt($item, $userId, $attemptNo, $seed, $params, $data, $veredicto);
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

        // La explicación —`expected` o `expected_key`— se revela solo DESPUÉS
        // de responder; el siguiente intento trae números nuevos (o una
        // barajada nueva), así que no regala nada.
        return response()->json([
            'id' => $attempt->id,
            'attempt_no' => $attemptNo,
            ...$veredicto,
            'se_guarda' => true,
        ], 201);
    }

    /**
     * Despacha el push AGS si el alumno tiene un resource link LTI para esta destreza.
     *
     * DECISIÓN DELIBERADA: mientras el selector desvía al alumno a un
     * prerrequisito (o al escalón siguiente), el ítem pertenece a OTRA destreza
     * y esa no tiene resource link, así que la nota del gradebook de Moodle se
     * queda quieta. Es lo correcto —la nota de «coeficiente de rozamiento» no
     * debe subir por practicar el plano inclinado— pero desde que existen las
     * aristas intra-MINEDEC el docente lo va a ver: un alumno trabajando sin
     * que se mueva la nota. Está documentado en docs/lti-moodle.md.
     */
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

    /**
     * @param  array<string, mixed>  $veredicto  Lo mismo que se le devuelve al
     *                                           cliente: es también lo que se guarda, para que no puedan
     *                                           divergir la respuesta y el registro.
     */
    private function persistAttempt($item, int $userId, int $attemptNo, string $seed, array $params, array $data, array $veredicto)
    {
        return DB::transaction(function () use ($item, $userId, $attemptNo, $seed, $params, $data, $veredicto) {
            $attempt = $item->attempts()->create([
                'user_id' => $userId,
                'attempt_no' => $attemptNo,
                'seed' => $seed,
                'params' => $params,
                // Un choice deja `answer`/`expected` en null y llena
                // `answer_key`; un numérico, al revés. Nada de rellenar la
                // columna del otro tipo con un valor inventado.
                'answer' => $veredicto['answer'] ?? null,
                'expected' => $veredicto['expected'] ?? null,
                'answer_key' => $veredicto['answer_key'] ?? null,
                'is_correct' => $veredicto['is_correct'],
                'time_ms' => $data['time_ms'] ?? null,
            ]);

            $this->tracker->apply($userId, $item->objective_id, $veredicto['is_correct'], $attempt->created_at);

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
