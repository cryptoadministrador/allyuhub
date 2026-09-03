<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Produccion;
use App\Services\Practice\Lenguas;
use App\Services\Produccion\Rubricas;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * LA COLA DEL DOCENTE — lo pendiente de SUS alumnos, con la rúbrica al lado.
 *
 * Sin esto la producción es un buzón sin fondo: el alumno graba y nadie
 * responde. El docente ve solo lo de los alumnos de sus cursos (scope
 * `pendientesDeDocente`, misma regla que la policy), corrige con la rúbrica del
 * CONTENIDO (no hardcodeada) y devuelve dos frases.
 *
 * El motor NO corrige: corregir aquí NO toca dominio ni AGS. Es la nota de una
 * persona, guardada tal cual.
 */
class ProduccionDocenteController extends Controller
{
    /** GET /docente/producciones?lengua=it — la cola pendiente del docente. */
    public function cola(Request $request)
    {
        // Lengua cerrada TAMBIÉN al servir: `?lengua=it` trae solo italiano;
        // sin lengua, toda la cola. Cerrada en las dos direcciones. Se valida a
        // mano con abort(422) y NO con $request->validate(): en una ruta `web`
        // (fuera de api/*) un validate fallido REDIRIGE (302) en vez de dar 422
        // —misma lección que PageController::destreza—.
        $lengua = $request->query('lengua');
        abort_unless($lengua === null || in_array($lengua, Lenguas::LISTA, true), 422,
            'Lengua fuera de la lista.');

        $docenteId = (int) $request->user()->id;

        $cola = Produccion::query()
            ->pendientesDeDocente($docenteId)
            ->when($lengua, fn ($q, $l) => $q->where('lengua', $l))
            ->with(['alumno:id,name', 'objective:id,native_code'])
            ->get()
            ->map(fn (Produccion $p) => [
                'id' => $p->id,
                'tipo' => $p->tipo,
                'unidad' => $p->unidad,
                'lengua' => $p->lengua,
                'alumno' => $p->alumno?->name,
                'code' => $p->objective?->native_code,
                // El texto del alumno solo para el docente (que está autorizado);
                // la voz va por su ruta con policy, jamás inline ni en el JSON.
                'texto' => $p->tipo === Produccion::ESCRITURA ? $p->texto : null,
                'audio_url' => $p->tipo === Produccion::VOZ ? route('produccion.audio', $p) : null,
                'rubrica' => Rubricas::para($p->tipo, $p->unidad),
                'creada' => $p->created_at->toDateString(),
            ]);

        return Inertia::render('docente-producciones', [
            'producciones' => $cola->all(),
            'lengua' => $lengua,
        ]);
    }

    /** POST /docente/producciones/{produccion} — la corrección con rúbrica y comentario. */
    public function corregir(Request $request, Produccion $produccion)
    {
        // Solo un docente del curso del alumno (nunca el propio alumno).
        abort_unless($request->user()->can('corregir', $produccion), 403);

        $claves = Rubricas::claves($produccion->tipo, $produccion->unidad);

        $data = $request->validate([
            'rubrica' => ['required', 'array', 'size:'.count($claves)],
            // Cada criterio de la rúbrica, con un nivel válido 0..NIVELES-1.
            ...collect($claves)->mapWithKeys(fn (string $c) => [
                "rubrica.{$c}" => ['required', 'integer', 'min:0', 'max:'.(Rubricas::NIVELES - 1)],
            ])->all(),
            'comentario' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $produccion->update([
            'rubrica' => $data['rubrica'],
            'comentario' => trim($data['comentario']),
            'estado' => Produccion::CORREGIDA,
            'corregida_por' => $request->user()->id,
            'corregida_en' => now(),
        ]);

        return redirect()->route('docente.producciones');
    }
}
