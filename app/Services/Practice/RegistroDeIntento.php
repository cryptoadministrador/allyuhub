<?php

namespace App\Services\Practice;

use App\Jobs\PushLtiScore;
use App\Models\LtiResourceLink;
use App\Models\PracticeAttempt;
use App\Models\PracticeItem;
use App\Services\Practice\Tipos\Registro;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * QUÉ PASA CUANDO UN ALUMNO RESPONDE UN ÍTEM — escrito una vez.
 *
 * Vivía dentro de `PracticeController::submitAttempt`. La PRUEBA DE UNIDAD
 * (PR 8) responde diez ítems de golpe y cada uno tiene que contar EXACTAMENTE
 * igual que en práctica: mismo billete, misma corrección, mismo intento
 * persistido, mismo dominio, mismo AGS, mismo repaso. Con el bucle copiado en
 * un segundo controlador, el día que la práctica cambie una regla (el `repaso`
 * del billete, el listón de `califica`) la prueba se queda con la vieja y
 * nadie se entera — y la nota del alumno sale de dos aritméticas distintas.
 *
 * Así que la práctica y la prueba llaman a las MISMAS tres piezas de aquí:
 *
 *  1. `reglasDe()`: lo que el tipo exige y lo que prohíbe (derivado, no
 *     escrito por tipo).
 *  2. `abrirBillete()`: el billete es de ESTE ítem y de ESTE practicante.
 *  3. `procesar()`: corregir por encima de la bifurcación, y debajo —solo con
 *     alumno— persistir intento + dominio en una transacción, AGS si califica
 *     y no es repaso, y reprogramar el repaso.
 *
 * Lo que NO vive aquí es la FORMA HTTP (200 para el invitado, 201 para el
 * alumno, 409 al intento duplicado): eso lo decide cada controlador, porque la
 * prueba responde con diez veredictos y una nota, no con uno.
 */
final class RegistroDeIntento
{
    public function __construct(
        private readonly PracticeEngine $engine,
        private readonly MasteryTracker $tracker,
        private readonly RepasoService $repaso,
    ) {}

    /**
     * Las reglas de validación de la respuesta a este ítem: las del tipo, más
     * la PROHIBICIÓN de los campos de los demás tipos. La exclusión mutua se
     * deriva de `camposDeRespuesta()`, así que el tipo que llegue mañana no
     * puede olvidarse de prohibir nada.
     *
     * @return array<string, mixed>
     */
    public function reglasDe(PracticeItem $item): array
    {
        $tipo = Registro::de($item->kind);
        $reglas = $tipo->reglas($item);
        foreach (['answer', 'answer_key', 'respuesta'] as $campo) {
            if (! in_array($campo, $tipo->camposDeRespuesta(), true)) {
                $reglas[$campo] = 'prohibited';
            }
        }

        return $reglas;
    }

    /**
     * Abre el billete y comprueba que es de ESTE ítem y de ESTE practicante.
     * Un 422 —no un «incorrecto»— porque el alumno no ha fallado nada: su
     * cliente está mandando algo que el servidor no emitió.
     *
     * @return array{attempt_no: int, seed: string, repaso: bool}
     *
     * @throws ValidationException
     */
    public function abrirBillete(string $billete, PracticeItem $item, Practitioner $quien): array
    {
        try {
            return AttemptTicket::abrir($billete, $item->id, $quien->seedKey());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['billete' => $e->getMessage()]);
        }
    }

    /**
     * Corrige, y si hay alumno, REGISTRA.
     *
     * LA CORRECCIÓN ES LA MISMA para el invitado y para el alumno: la resuelve
     * el TIPO, por encima de la bifurcación. Lo único que cambia debajo es si
     * el resultado se guarda y si califica. Lo que el veredicto revela lo
     * decide cada tipo, y siempre DESPUÉS de responder.
     *
     * @param  array<string, mixed>  $data  Ya validada con `reglasDe()`.
     * @param  array{attempt_no: int, seed: string, repaso: bool}  $ticket
     * @return array{veredicto: array<string, mixed>, attempt: PracticeAttempt|null}
     *
     * @throws \Illuminate\Database\UniqueConstraintViolationException si otra
     *         petición registró este intento primero (el controlador responde 409).
     */
    public function procesar(PracticeItem $item, array $data, array $ticket, Practitioner $quien): array
    {
        $tipo = Registro::de($item->kind);
        $seed = $ticket['seed'];

        $veredicto = $tipo->corregir($item, $data, $this->engine, $seed);

        if ($quien->isGuest()) {
            // Ni intento, ni dominio, ni AGS — la regla de oro del contenido
            // abierto. Hizo el ejercicio; no se guarda.
            return ['veredicto' => $veredicto, 'attempt' => null];
        }

        $userId = $quien->userId();

        // Intento + actualización de mastery en la MISMA transacción: o quedan
        // los dos, o ninguno. Si dos peticiones simultáneas calcularon el mismo
        // attempt_no (unique por ítem+usuario), la perdedora lanza y el
        // controlador responde 409 — nunca un 500.
        [$attempt, $itemsAcertados] = DB::transaction(function () use ($item, $userId, $ticket, $seed, $tipo, $data, $veredicto) {
            $attempt = $item->attempts()->create([
                'user_id' => $userId,
                'attempt_no' => $ticket['attempt_no'],
                'seed' => $seed,
                // Qué columnas puebla el intento lo declara el TIPO, con la
                // invariante de una vía por kind. Nada de rellenar las otras
                // con '' o 0.0 — eso escondería un bug de bifurcación.
                ...$tipo->columnas($item, $veredicto, $data, $this->engine, $seed),
                'is_correct' => $veredicto['is_correct'],
                'time_ms' => $data['time_ms'] ?? null,
            ]);

            // Se cuenta DESPUÉS de guardar el intento —para que el acierto que
            // acaba de ocurrir cuente— y una sola vez: sirve para sellar el
            // dominio y para decidir si la nota viaja al aula.
            $itemsAcertados = $this->tracker->itemsAcertados($userId, $item->objective_id);

            $this->tracker->apply(
                $userId, $item->objective_id, $veredicto['is_correct'],
                $itemsAcertados, $attempt->created_at,
            );

            return [$attempt, $itemsAcertados];
        });

        // Con un solo ítem acertado la nota no sale: mismo listón que el
        // dominio. El REPASO cuenta para el dominio (ya aplicado) pero NO para
        // la nota: una nota que sube repasando lo sabido está inflada. El flag
        // viene FIRMADO en el billete, así que no se puede forjar para inflar.
        if (! $ticket['repaso'] && $this->tracker->califica($itemsAcertados)) {
            $this->queueLtiScore($userId, $item->objective_id);
        }

        // Se reprograma el repaso del descriptor SIEMPRE que practica un
        // alumno (repaso o no): tocar una destreza reprograma su próxima cita.
        $this->repaso->programar($userId, $item->objective_id, $veredicto['is_correct']);

        return ['veredicto' => $veredicto, 'attempt' => $attempt];
    }

    /**
     * Despacha el push AGS si el alumno tiene un resource link LTI para esta
     * destreza. Mientras el selector desvía al alumno a un prerrequisito, el
     * ítem pertenece a OTRA destreza sin resource link y la nota del gradebook
     * se queda quieta — es lo correcto y está documentado en docs/lti-moodle.md.
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
}
