<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\Curso\CursoDeLenguas;
use App\Services\Practice\Lenguas;
use App\Services\Practice\RachaDeAlumno;
use App\Services\Practice\RepasoService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * EL CASCARÓN DEL CURSO — abierto como el resto del contenido (modelo Khan).
 *
 * Un alumno de italiano entra por `/corso/it`; un invitado ve la misma forma
 * con su avance a cero y sin escribir una fila. La LENGUA es de lista cerrada:
 * `/corso/klingon` es 404, no un curso inventado.
 */
class CursoController extends Controller
{
    public function __construct(
        private readonly CursoDeLenguas $curso,
        private readonly RachaDeAlumno $racha,
        private readonly RepasoService $repaso,
    ) {}

    public function portada(Request $request, string $lengua)
    {
        abort_unless(in_array($lengua, Lenguas::LISTA, true), 404);

        $userId = $request->user()?->id;

        return Inertia::render('corso', [
            ...$this->curso->portada($lengua, $userId),
            'racha' => $this->racha->calcular($userId),
            'repasos' => $this->repaso->cola($userId, $lengua),
            'se_guarda' => $userId !== null,
        ]);
    }

    public function unidad(Request $request, string $lengua, int $n)
    {
        abort_unless(in_array($lengua, Lenguas::LISTA, true), 404);
        abort_unless($this->curso->existeUnidad($n), 404);

        $userId = $request->user()?->id;

        return Inertia::render('corso-unidad', [
            ...$this->curso->unidad($lengua, $n, $userId),
            'se_guarda' => $userId !== null,
        ]);
    }

    /**
     * GET /corso/{lengua}/u{n}/producir — la tarea de producción de la unidad.
     *
     * ABIERTA como el resto del curso: el invitado ve la tarea (y el aviso de
     * que hace falta entrar para enviarla); enviar es lo único cerrado. Se
     * ofrecen solo las destrezas PRODUCTIVAS de la unidad (EE→escritura,
     * PO→voz); una unidad sin ellas no tiene esta página (404).
     */
    public function producir(Request $request, string $lengua, int $n)
    {
        abort_unless(in_array($lengua, Lenguas::LISTA, true), 404);
        abort_unless($this->curso->existeUnidad($n), 404);

        $userId = $request->user()?->id;
        $detalle = $this->curso->unidad($lengua, $n, $userId);

        $productivos = collect($detalle['puedo'])
            ->map(function (array $p) {
                $tipo = match (true) {
                    str_contains($p['code'], '.EE.') => 'escritura',
                    str_contains($p['code'], '.PO.') => 'voz',
                    default => null,
                };

                return $tipo === null ? null : [
                    'descriptor_id' => $p['descriptor_id'],
                    'code' => $p['code'],
                    'statement' => $p['statement'],
                    'tipo' => $tipo,
                ];
            })
            ->filter()
            ->values();

        abort_if($productivos->isEmpty(), 404, 'Esta unidad no tiene tarea de producción.');

        return Inertia::render('producir', [
            'lengua' => $lengua,
            'nombre' => $this->curso->nombre($lengua),
            'unidad' => ['n' => $n, 'titulo' => $detalle['unidad']['titulo']],
            'productivos' => $productivos->all(),
            'se_guarda' => $userId !== null,
        ]);
    }
}
