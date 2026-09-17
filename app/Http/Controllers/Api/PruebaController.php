<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PracticeAttempt;
use App\Models\PracticeItem;
use App\Models\PruebaUnidad;
use App\Services\Curso\CursoDeLenguas;
use App\Services\Practice\AttemptTicket;
use App\Services\Practice\Lenguas;
use App\Services\Practice\PracticeEngine;
use App\Services\Practice\Practitioner;
use App\Services\Practice\RegistroDeIntento;
use App\Services\Practice\Tipos\Registro;
use App\Services\Prueba\PruebaDeUnidad;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * LA PRUEBA DE UNIDAD: diez ítems de corrido, nota al final.
 *
 * La diferencia con la práctica es SOLO cuándo se ve el veredicto —al final,
 * no ítem a ítem— y que al terminar hay una nota con desglose. Todo lo demás
 * es literalmente la misma pieza (`RegistroDeIntento`): el mismo billete por
 * ítem, la misma corrección del tipo, el mismo intento persistido con su
 * dominio, su AGS y su repaso. Si la prueba tuviera su propio camino de
 * corrección, un alumno sacaría dos notas distintas de la misma respuesta.
 *
 * ABIERTA como la práctica: el invitado hace la prueba entera, ve su nota y
 * NO escribe ni una fila (`se_guarda: false`). Con sesión, cada respuesta es
 * un intento de práctica y la prueba deja además su fila en `pruebas_unidad`.
 */
class PruebaController extends Controller
{
    public function __construct(
        private readonly PracticeEngine $engine,
        private readonly RegistroDeIntento $registro,
        private readonly PruebaDeUnidad $prueba,
        private readonly CursoDeLenguas $curso,
    ) {}

    /**
     * GET /api/v1/pruebas/{lengua}/u{n}?intento= — sirve los diez ítems.
     *
     * Cada ítem viaja con su payload de tipo (lista blanca, sin solución) y su
     * BILLETE firmado — el mismo que emitiría `next`—, así que `entregar` los
     * corrige contra lo que se sirvió y no contra otra cosa.
     */
    public function servir(Request $request, string $lengua, int $n)
    {
        $this->exigirUnidad($lengua, $n);

        $data = $request->validate([
            'user_id' => 'prohibited',
            'intento' => 'nullable|integer|min:1|max:500',
        ]);
        $quien = Practitioner::fromRequest($request);
        $intento = $this->intentoDe($quien, $lengua, $n, (int) ($data['intento'] ?? 1));

        $items = $this->prueba->componer($lengua, $n, $quien->seedKey(), $intento);
        abort_if($items->isEmpty(), 404, 'Esta unidad todavía no tiene ejercicios firmados en esta lengua.');

        return response()->json([
            'lengua' => $lengua,
            'unidad' => $n,
            'intento' => $intento,
            'total' => $items->count(),
            'items' => $items->map(function (PracticeItem $item) use ($quien, $intento) {
                $attemptNo = $this->attemptNoDe($item, $quien, $intento);
                $seed = $this->engine->seedFor($item->id, $quien->seedKey(), $attemptNo);

                return [
                    'item_id' => $item->id,
                    'kind' => $item->kind,
                    'objective_id' => $item->objective_id,
                    'objective_code' => $item->objective?->native_code,
                    'objective_statement' => $item->objective?->statement['es'] ?? null,
                    'attempt_no' => $attemptNo,
                    'billete' => AttemptTicket::emitir($item->id, $quien->seedKey(), $attemptNo, $seed),
                    // Lo específico de cada tipo lo declara SU clase: lista
                    // blanca campo a campo, la solución no está porque ningún
                    // tipo la pone.
                    ...Registro::de($item->kind)->payload($item, $this->engine, $seed),
                ];
            })->values(),
            'se_guarda' => ! $quien->isGuest(),
        ]);
    }

    /**
     * POST /api/v1/pruebas/{lengua}/u{n} — entrega las respuestas, devuelve la nota.
     *
     * DOS PASADAS. Primero se valida TODO (reglas del tipo, billete de cada
     * ítem, que llegue respuesta a cada ítem servido) sin tocar nada; después
     * se registra todo dentro de una transacción. Sin esto, una séptima
     * respuesta mal formada dejaría seis intentos ya guardados y una prueba a
     * medias que no existe.
     */
    public function entregar(Request $request, string $lengua, int $n)
    {
        $this->exigirUnidad($lengua, $n);

        $data = $request->validate([
            'user_id' => 'prohibited',
            'intento' => 'required|integer|min:1|max:500',
            'respuestas' => 'required|array|min:1|max:'.PruebaUnidad::TAMANO,
            'respuestas.*.item_id' => 'required|uuid',
            'respuestas.*.billete' => 'required|string',
        ]);
        $quien = Practitioner::fromRequest($request);
        $intento = (int) $data['intento'];

        // Con sesión, el intento que se entrega es el SIGUIENTE: entregar dos
        // veces el mismo, o uno viejo, no es una prueba nueva.
        if (! $quien->isGuest() && $intento !== $this->intentoDe($quien, $lengua, $n, $intento)) {
            throw ValidationException::withMessages(['intento' => 'Esta prueba ya está entregada. Pide una nueva.']);
        }

        // Se RECOMPONE con la misma semilla: lo que se corrige es lo que se
        // sirvió, ni un ítem más ni uno menos.
        $items = $this->prueba->componer($lengua, $n, $quien->seedKey(), $intento);
        abort_if($items->isEmpty(), 404);
        // Del INPUT crudo, no de `$data`: `validate()` devuelve solo las claves
        // con regla, y aquí las reglas de la respuesta las pone cada tipo en la
        // pasada siguiente. Con `$data` llegaría solo item_id y billete.
        $porItem = collect($request->input('respuestas', []))->keyBy('item_id');

        // ---- pasada 1: validar sin efectos ----
        $preparadas = [];
        foreach ($items as $item) {
            $respuesta = $porItem->get($item->id);
            if ($respuesta === null) {
                throw ValidationException::withMessages([
                    'respuestas' => "Falta la respuesta del ejercicio {$item->id}.",
                ]);
            }

            $validada = Validator::make($respuesta, [
                'user_id' => 'prohibited',
                'intento' => 'prohibited',
                ...$this->registro->reglasDe($item),
                'time_ms' => 'nullable|integer|min:0',
            ])->validate();

            $preparadas[] = [
                'item' => $item,
                'data' => $validada,
                'ticket' => $this->registro->abrirBillete($respuesta['billete'], $item, $quien),
            ];
        }

        // Y nada que no se haya servido: un ítem de más es un cliente que no
        // manda lo que el servidor emitió.
        $servidos = $items->pluck('id')->all();
        $extra = $porItem->keys()->diff($servidos);
        if ($extra->isNotEmpty()) {
            throw ValidationException::withMessages(['respuestas' => 'Hay respuestas de ejercicios que no son de esta prueba.']);
        }

        // ---- pasada 2: registrar, todo o nada ----
        try {
            $resultado = DB::transaction(function () use ($preparadas, $quien, $lengua, $n, $intento) {
                $veredictos = [];
                $desglose = [];
                foreach ($preparadas as $p) {
                    ['veredicto' => $veredicto] = $this->registro->procesar($p['item'], $p['data'], $p['ticket'], $quien);
                    $code = $p['item']->objective?->native_code ?? '—';
                    $desglose[$code] ??= ['aciertos' => 0, 'total' => 0];
                    $desglose[$code]['total']++;
                    if ($veredicto['is_correct']) {
                        $desglose[$code]['aciertos']++;
                    }
                    $veredictos[] = ['item_id' => $p['item']->id, ...$veredicto];
                }

                $nota = collect($veredictos)->where('is_correct', true)->count();
                $total = count($veredictos);
                // El listón es 8 de 10. Si el banco solo dio para menos, la misma
                // proporción: aprobar no puede depender de cuántos ítems haya.
                $aprobada = $nota >= (int) ceil($total * PruebaUnidad::APROBADO / PruebaUnidad::TAMANO);

                if (! $quien->isGuest()) {
                    PruebaUnidad::create([
                        'user_id' => $quien->userId(),
                        'lengua' => $lengua,
                        'unidad' => $n,
                        'intento' => $intento,
                        'nota' => $nota,
                        'total' => $total,
                        'desglose' => $desglose,
                        'aprobada' => $aprobada,
                        'completed_at' => now(),
                    ]);
                }

                return compact('veredictos', 'desglose', 'nota', 'total', 'aprobada');
            });
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'Otra petición registró parte de esta prueba primero. Pide la prueba de nuevo y reintenta.',
            ], 409);
        }

        return response()->json([
            ...$resultado,
            'intento' => $intento,
            'se_guarda' => ! $quien->isGuest(),
        ], $quien->isGuest() ? 200 : 201);
    }

    private function exigirUnidad(string $lengua, int $n): void
    {
        abort_unless(in_array($lengua, Lenguas::LISTA, true), 404);
        abort_unless($this->curso->existeUnidad($lengua, $n), 404);
    }

    /**
     * El número de intento de la prueba. Con sesión, el SIGUIENTE al último
     * entregado; el invitado no tiene historia y lo lleva él (como en práctica).
     */
    private function intentoDe(Practitioner $quien, string $lengua, int $n, int $pedido): int
    {
        if ($quien->isGuest()) {
            return $pedido;
        }

        return PruebaUnidad::query()
            ->where('user_id', $quien->userId())
            ->where('lengua', $lengua)
            ->where('unidad', $n)
            ->count() + 1;
    }

    /**
     * El número de intento de ESTE ítem para ESTE practicante: el siguiente al
     * último que tenga, igual que calcula `next`. Es lo que va firmado en el
     * billete y lo que hace único el intento al persistirlo.
     */
    private function attemptNoDe(PracticeItem $item, Practitioner $quien, int $intento): int
    {
        // El invitado no tiene historial: su número de intento es el de la
        // prueba, como en práctica lleva el suyo. Así repetir la prueba trae
        // otra semilla también para él.
        if ($quien->isGuest()) {
            return $intento;
        }

        return PracticeAttempt::query()
            ->where('item_id', $item->id)
            ->where('user_id', $quien->userId())
            ->count() + 1;
    }
}
