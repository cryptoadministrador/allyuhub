<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Una acción de revisión sobre una pieza de contenido: firmarla, devolverla con
 * una nota, o retirarla de pantalla. El RASTRO, no el estado — el estado (si
 * está firmada o no) sigue viviendo en `reviewed_at` de la pieza.
 *
 * Dos invariantes, las dos impuestas al guardar y no solo documentadas:
 *  - UNA VÍA: o ítem o versión de lección, nunca las dos ni ninguna.
 *  - DEVOLVER Y DESFIRMAR EXIGEN NOTA. Retirar algo que ya vio un alumno sin
 *    decir por qué es exactamente lo que no puede pasar.
 */
class Revision extends Model
{
    use HasUuids;

    protected $table = 'revisiones';

    protected $guarded = [];

    public const FIRMAR = 'firmar';

    public const DEVOLVER = 'devolver';

    public const DESFIRMAR = 'desfirmar';

    /** Las acciones que EXIGEN nota: las que dejan (o mantienen) algo fuera. */
    public const EXIGEN_NOTA = [self::DEVOLVER, self::DESFIRMAR];

    public const ACCIONES = [self::FIRMAR, self::DEVOLVER, self::DESFIRMAR];

    protected static function booted(): void
    {
        static::saving(function (Revision $r) {
            $item = $r->practice_item_id !== null;
            $version = $r->resource_version_id !== null;

            if ($item === $version) {
                throw new RuntimeException(
                    'Una revisión es de un ítem O de una versión de lección, nunca de las dos ni de ninguna.',
                );
            }

            if (! in_array($r->accion, self::ACCIONES, true)) {
                throw new RuntimeException("Acción de revisión desconocida: «{$r->accion}».");
            }

            if (in_array($r->accion, self::EXIGEN_NOTA, true) && trim((string) $r->nota) === '') {
                throw new RuntimeException("La acción «{$r->accion}» exige una nota.");
            }
        });
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(PracticeItem::class, 'practice_item_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ResourceVersion::class, 'resource_version_id');
    }
}
