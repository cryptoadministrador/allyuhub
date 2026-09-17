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
        abort_unless($this->curso->existeUnidad($lengua, $n), 404);

        $userId = $request->user()?->id;

        return Inertia::render('corso-unidad', [
            ...$this->curso->unidad($lengua, $n, $userId),
            // ¿Hay un diálogo FIRMADO para esta unidad? La unidad enlaza a
            // «hablar» solo si lo hay — nunca un enlace muerto.
            'tiene_dialogo' => \App\Models\Dialogo::published()
                ->where('lengua', $lengua)->where('unidad', $n)->exists(),
            'se_guarda' => $userId !== null,
        ]);
    }

    /**
     * GET /corso/{lengua}/u{n}/prueba — la prueba de la unidad (PR 8).
     *
     * ABIERTA como la práctica: el invitado la hace entera y ve su nota, sin
     * escribir nada. La página pide los diez ítems a la API de pruebas y los
     * entrega de golpe; aquí solo viaja lo que necesita para arrancar.
     */
    public function prueba(Request $request, string $lengua, int $n)
    {
        abort_unless(in_array($lengua, Lenguas::LISTA, true), 404);
        abort_unless($this->curso->existeUnidad($lengua, $n), 404);

        $userId = $request->user()?->id;
        $detalle = $this->curso->unidad($lengua, $n, $userId);
        abort_unless($detalle['tiene_prueba'], 404, 'Esta unidad todavía no tiene ejercicios firmados.');

        // Lo que el alumno ya hizo en esta unidad: su mejor nota y cuántas
        // veces. El invitado no tiene historia.
        $historial = $userId === null ? [] : \App\Models\PruebaUnidad::query()
            ->where('user_id', $userId)->where('lengua', $lengua)->where('unidad', $n)
            ->orderBy('intento')
            ->get(['intento', 'nota', 'total', 'aprobada'])
            ->map(fn ($p) => ['intento' => $p->intento, 'nota' => $p->nota, 'total' => $p->total, 'aprobada' => $p->aprobada])
            ->all();

        return Inertia::render('prueba', [
            'lengua' => $lengua,
            'nombre' => $this->curso->nombre($lengua),
            'unidad' => ['n' => $n, 'titulo' => $detalle['unidad']['titulo']],
            'historial' => $historial,
            'aprobado' => \App\Models\PruebaUnidad::APROBADO,
            'tamano' => \App\Models\PruebaUnidad::TAMANO,
            'se_guarda' => $userId !== null,
        ]);
    }

    /**
     * GET /corso/{lengua}/u{n}/hablar — el interlocutor guionizado de la unidad.
     *
     * ABIERTA como el resto del curso. Solo se sirve el diálogo FIRMADO; si no
     * hay, la página lo dice («próximamente»), nunca un enlace muerto.
     */
    public function hablar(Request $request, string $lengua, int $n)
    {
        abort_unless(in_array($lengua, Lenguas::LISTA, true), 404);
        abort_unless($this->curso->existeUnidad($lengua, $n), 404);

        $userId = $request->user()?->id;
        $detalle = $this->curso->unidad($lengua, $n, $userId);

        $dialogo = \App\Models\Dialogo::published()
            ->where('lengua', $lengua)->where('unidad', $n)
            ->with('objective:id,native_code')
            ->orderBy('slug')   // nullable-safe: slug es NOT NULL, orden estable
            ->first();

        return Inertia::render('dialogo', [
            'lengua' => $lengua,
            'nombre' => $this->curso->nombre($lengua),
            'unidad' => ['n' => $n, 'titulo' => $detalle['unidad']['titulo']],
            'dialogo' => $dialogo === null ? null : [
                'id' => $dialogo->id,
                'titulo' => $dialogo->titulo,
                'objective_code' => $dialogo->objective?->native_code,
                // El grafo entero: no hay «solución» oculta en un diálogo, todas
                // las ramas son contenido. Sí se sirve para que React lo pinte.
                'nodos' => $dialogo->nodos,
            ],
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
        abort_unless($this->curso->existeUnidad($lengua, $n), 404);

        $userId = $request->user()?->id;
        $detalle = $this->curso->unidad($lengua, $n, $userId);

        // QUÉ DESTREZA ES PRODUCTIVA lo declara el CURSO, no este controlador.
        // Aquí ponía `str_contains($code, '.EE.')`, cierto solo mientras todos
        // los cursos fueran del MCER: el inglés de Cambridge no tiene códigos
        // `A1.*` y no declara ninguna, así que su página de tarea no existe.
        $productivas = $this->curso->productivas($lengua);

        $productivos = collect($detalle['puedo'])
            ->map(function (array $p) use ($productivas) {
                $tipo = null;
                foreach ($productivas as $cual => $marca) {
                    if (str_contains($p['code'], $marca)) {
                        $tipo = $cual;
                        break;
                    }
                }

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
