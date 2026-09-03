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
}
