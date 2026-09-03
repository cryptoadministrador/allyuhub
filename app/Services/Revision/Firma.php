<?php

namespace App\Services\Revision;

use App\Models\Revision;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * LA FIRMA: el ÚNICO sitio donde está escrito qué significa firmar contenido.
 *
 * Antes vivía copiada en `practica:firmar` y `lecciones:firmar` (los dos
 * escribían `reviewed_at` + `reviewed_by` a mano) y la pantalla de revisión iba
 * a ser el tercero. Ahora los tres llaman aquí: `columnas()` dice QUÉ escribe
 * una firma, y los comandos lo usan en su `update()` masivo sin cambiar de
 * comportamiento ni de coste.
 *
 * La pantalla usa además `firmar()`/`devolver()`/`desfirmar()`, que hacen lo
 * mismo Y dejan rastro en `revisiones` con el docente real. La diferencia no es
 * caprichosa:
 *
 *  - El comando corre por SSH y admite no declarar autor (`--docente` es
 *    opcional): ahí no hay a quién apuntar, y por eso no escribe rastro.
 *  - La pantalla SIEMPRE tiene sesión, así que siempre hay autor y siempre hay
 *    rastro. Y **des-firmar solo existe en la pantalla**, de modo que la regla
 *    «nada se des-firma sin nota» se cumple por construcción: no hay otra vía.
 */
final class Firma
{
    /**
     * QUÉ ESCRIBE UNA FIRMA. Un solo sitio, usado por los dos comandos y por la
     * pantalla. Sin `--docente` queda `reviewed_by` nulo: inventar una autoría
     * sería peor que dejarla vacía (misma regla que el crosswalk).
     *
     * @return array{reviewed_at: \Illuminate\Support\Carbon, reviewed_by: int|null}
     */
    public static function columnas(?User $docente): array
    {
        return [
            'reviewed_at' => now(),
            'reviewed_by' => $docente?->id,
        ];
    }

    /** Lo que escribe RETIRAR una firma: la pieza vuelve a estar fuera. */
    public static function columnasAlRetirar(): array
    {
        return [
            'reviewed_at' => null,
            'reviewed_by' => null,
        ];
    }

    /** Firma una pieza y lo apunta. La pieza sale al alumno. */
    public function firmar(Pieza $pieza, User $docente, ?string $nota = null): void
    {
        DB::transaction(function () use ($pieza, $docente, $nota) {
            $pieza->modelo->forceFill(self::columnas($docente))->save();
            $this->apuntar($pieza, $docente, Revision::FIRMAR, $nota);
        });
    }

    /**
     * Devuelve una pieza con una nota: NO se firma y la nota queda para quien la
     * corrija. La nota es obligatoria (la impone también el modelo al guardar).
     */
    public function devolver(Pieza $pieza, User $docente, string $nota): void
    {
        // No toca la firma: devolver es "esto no sale, y por esto".
        $this->apuntar($pieza, $docente, Revision::DEVOLVER, $nota);
    }

    /** Retira una firma con nota: lo publicado sale de pantalla en un clic. */
    public function desfirmar(Pieza $pieza, User $docente, string $nota): void
    {
        DB::transaction(function () use ($pieza, $docente, $nota) {
            $pieza->modelo->forceFill(self::columnasAlRetirar())->save();
            $this->apuntar($pieza, $docente, Revision::DESFIRMAR, $nota);
        });
    }

    private function apuntar(Pieza $pieza, User $docente, string $accion, ?string $nota): void
    {
        Revision::create([
            'practice_item_id' => $pieza->tipo === Pieza::ITEM ? $pieza->id() : null,
            'resource_version_id' => $pieza->tipo === Pieza::LECCION ? $pieza->id() : null,
            'user_id' => $docente->id,
            'accion' => $accion,
            'nota' => $nota === null ? null : trim($nota),
        ]);
    }
}
