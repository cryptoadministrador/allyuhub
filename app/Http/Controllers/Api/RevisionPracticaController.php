<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PracticeItem;
use App\Services\Docente\Docencia;
use App\Services\Practice\AdaptiveSelector;
use App\Services\Practice\AttemptTicket;
use App\Services\Practice\PracticeEngine;
use App\Services\Practice\Tipos\Registro;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * VER UN ÍTEM SIN FIRMAR, TAL COMO LO VERÁ EL ALUMNO.
 *
 * La pantalla de revisión reutiliza `Practicar.jsx` —no hay un visor de
 * revisión aparte— y `Practicar.jsx` pide su ejercicio a la API. Pero la API de
 * práctica solo sirve lo FIRMADO (`practiceItems()` filtra por `reviewed_at`),
 * que es justo lo que el docente todavía no ha hecho. De ahí estas dos rutas:
 * el mismo payload, construido con las MISMAS piezas (`Tipos\Registro`,
 * `PracticeEngine`, `AttemptTicket`), pero para un ítem concreto y solo para un
 * docente.
 *
 * DOS PROPIEDADES QUE NO SE NEGOCIAN:
 *
 *  1. **No persiste nada.** Revisar no es practicar: no se crea intento, ni
 *     dominio, ni nota AGS. El docente corrige de verdad —mismo motor— y no
 *     deja rastro en su expediente. Es el camino del invitado, con otra puerta.
 *  2. **La identidad de la semilla es OTRA** (`revision:<id>`), así que el
 *     billete que emite esta ruta NO vale en `/api/v1/practice/...` ni al revés:
 *     `AttemptTicket` ata el billete a quién, y aquí «quién» no es el usuario.
 *     Un billete de revisión no puede escribirle un intento a nadie.
 */
class RevisionPracticaController extends Controller
{
    public function __construct(private readonly PracticeEngine $engine) {}

    /** GET /api/v1/revision/items/{item}/next */
    public function next(Request $request, PracticeItem $item)
    {
        $quien = $this->quien($request);

        $data = $request->validate([
            'user_id' => 'prohibited',
            'intento' => 'nullable|integer|min:1|max:500',
        ]);
        $attemptNo = (int) ($data['intento'] ?? 1);

        $seed = $this->engine->seedFor($item->id, $quien, $attemptNo);
        $propio = Registro::de($item->kind)->payload($item, $this->engine, $seed);

        return response()->json([
            'item_id' => $item->id,
            'kind' => $item->kind,
            'objective_id' => $item->objective_id,
            'objective_code' => $item->objective?->native_code,
            'objective_statement' => $item->objective?->statement['es'] ?? null,
            'attempt_no' => $attemptNo,
            'billete' => AttemptTicket::emitir($item->id, $quien, $attemptNo, $seed),
            ...$propio,
            'reason' => AdaptiveSelector::REASON_NORMAL,
            // `se_guarda` NO viaja a propósito: la página lo lee para avisar de
            // una sesión caducada, y aquí no se guarda nada por diseño, no por
            // haber perdido la sesión. `revision` lo dice sin ambigüedad.
            'revision' => true,
        ]);
    }

    /** POST /api/v1/revision/items/{item}/attempts — corrige y NO guarda. */
    public function attempt(Request $request, PracticeItem $item)
    {
        $quien = $this->quien($request);

        // Las reglas del tipo, y la exclusión mutua DERIVADA — igual que en la
        // práctica real: el tipo declara qué campos usa y se prohíben los demás.
        $tipo = Registro::de($item->kind);
        $reglas = $tipo->reglas($item);
        foreach (['answer', 'answer_key', 'respuesta'] as $campo) {
            if (! in_array($campo, $tipo->camposDeRespuesta(), true)) {
                $reglas[$campo] = 'prohibited';
            }
        }

        $data = $request->validate([
            'user_id' => 'prohibited',
            ...$reglas,
            'time_ms' => 'nullable|integer|min:0',
            'intento' => 'prohibited',
            'billete' => 'required|string',
        ]);

        try {
            $ticket = AttemptTicket::abrir($data['billete'], $item->id, $quien);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['billete' => $e->getMessage()]);
        }

        // MISMA corrección que la del alumno: la resuelve el tipo.
        $veredicto = $tipo->corregir($item, $data, $this->engine, $ticket['seed']);

        // Y aquí se acaba: ni intento, ni dominio, ni AGS.
        return response()->json([
            'attempt_no' => $ticket['attempt_no'],
            ...$veredicto,
            'revision' => true,
        ]);
    }

    /**
     * La identidad de revisión: un docente, o 403. Un alumno y un invitado
     * reciben 403 —no una redirección— también aquí.
     */
    private function quien(Request $request): string
    {
        $user = $request->user();
        abort_unless(Docencia::es($user), 403, 'Solo un docente puede revisar contenido.');

        return 'revision:'.$user->id;
    }
}
