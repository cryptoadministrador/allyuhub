<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LearningObjective;
use App\Models\Produccion;
use App\Services\Practice\Lenguas;
use App\Services\Produccion\AlmacenDeProducciones;
use App\Services\Produccion\AnioLectivo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * LO QUE EL ALUMNO PRODUCE — guardar, borrar, y servir su voz.
 *
 * CERRADO por diseño (al revés que la práctica, que es abierta): esto ESCRIBE
 * contenido de un menor, así que exige sesión. Un invitado que intente producir
 * recibe 401 y no deja fila — la regla de oro. La identidad es SIEMPRE la
 * sesión; un `user_id` en el request es 422.
 *
 * El motor NO corrige producción: aquí solo se guarda y se encola para el
 * docente (que es quien pone la nota, con la rúbrica del contenido).
 */
class ProduccionController extends Controller
{
    /** POST /api/v1/producciones — crea una producción de escritura o voz. */
    public function store(Request $request, AlmacenDeProducciones $almacen)
    {
        $user = $request->user();

        $data = $request->validate([
            'user_id' => 'prohibited',
            'objective_id' => ['required', 'uuid', 'exists:learning_objectives,id'],
            'unidad' => ['required', 'integer', 'min:1', 'max:9'],
            'lengua' => ['required', 'string', Rule::in(Lenguas::LISTA)],
            'tipo' => ['required', Rule::in([Produccion::ESCRITURA, Produccion::VOZ])],
            'texto' => ['nullable', 'string', 'min:20', 'max:2000'],
            'archivo' => ['nullable', 'file', 'max:5120',
                'mimetypes:audio/webm,audio/ogg,audio/mpeg,audio/mp4,audio/wav'],
        ]);

        $objetivo = LearningObjective::findOrFail($data['objective_id']);

        // La tarea productiva se corrige contra una destreza productiva:
        // Expresión Escrita (EE) para escritura, Producción Oral (PO) para voz.
        // Producir escritura contra una destreza de comprensión no es una tarea.
        $esperada = $data['tipo'] === Produccion::ESCRITURA ? '.EE.' : '.PO.';
        if (! str_contains((string) $objetivo->native_code, $esperada)) {
            throw ValidationException::withMessages([
                'objective_id' => "Una producción de {$data['tipo']} se hace contra una destreza {$esperada}.",
            ]);
        }

        // Exactamente una vía según el tipo — la MISMA invariante que impone el
        // modelo al guardar, comprobada aquí para devolver 422 y no 500.
        if ($data['tipo'] === Produccion::ESCRITURA && empty($data['texto'])) {
            throw ValidationException::withMessages(['texto' => 'La escritura necesita texto.']);
        }
        if ($data['tipo'] === Produccion::VOZ && ! $request->hasFile('archivo')) {
            throw ValidationException::withMessages(['archivo' => 'La voz necesita una grabación.']);
        }

        $anio = AnioLectivo::actual();

        $archivo = $data['tipo'] === Produccion::VOZ
            ? $almacen->guardar($request->file('archivo'), $anio)
            : null;

        $produccion = Produccion::create([
            'user_id' => $user->id,
            'objective_id' => $objetivo->id,
            'unidad' => $data['unidad'],
            'lengua' => $data['lengua'],
            'tipo' => $data['tipo'],
            'texto' => $data['tipo'] === Produccion::ESCRITURA ? trim($data['texto']) : null,
            'archivo' => $archivo,
            'anio_lectivo' => $anio,
            'estado' => Produccion::PENDIENTE,
        ]);

        return response()->json([
            'id' => $produccion->id,
            'estado' => $produccion->estado,
        ], 201);
    }

    /** DELETE /api/v1/producciones/{produccion} — el alumno borra la suya (sin corregir). */
    public function destroy(Request $request, Produccion $produccion, AlmacenDeProducciones $almacen)
    {
        // La policy: dueño Y sin corregir. Un docente no borra; un dueño con la
        // suya ya corregida, tampoco (es un registro de evaluación).
        abort_unless($request->user()->can('borrar', $produccion), 403);

        if ($produccion->archivo !== null) {
            $almacen->borrar($produccion->archivo);
        }
        $produccion->delete();

        return response()->json(['borrada' => true]);
    }

    /**
     * GET /produccion/{produccion}/audio — sirve la grabación.
     *
     * NUNCA por `/audio/*` (público, cacheado inmutable): por aquí, con sesión y
     * policy. Un invitado es 401 (middleware); un docente de otro curso, 403
     * (policy `ver`); el dueño o su docente, 200. `no-store` para que ni el
     * navegador la deje en caché.
     */
    public function audio(Request $request, Produccion $produccion)
    {
        abort_unless($request->user()->can('ver', $produccion), 403);
        abort_if($produccion->archivo === null, 404);

        $ruta = AlmacenDeProducciones::resolver($produccion->archivo);
        abort_if($ruta === null, 404);

        return response()->file($ruta, [
            'Content-Type' => AlmacenDeProducciones::contentType($produccion->archivo),
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
