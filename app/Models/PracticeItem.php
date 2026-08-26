<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * Plantilla de ítem de práctica parametrizada, anclada a una destreza.
 * Se instancia con semilla determinista: nunca guarda números concretos,
 * solo rangos (`params`) y la expresión de solución (`solution_expr`).
 *
 * Dos tipos (`kind`):
 *  - `numeric`: el de siempre. Rangos en `params`, expresión en `solution_expr`.
 *  - `choice`:  opción múltiple. Las opciones viven en `options` (sin marca de
 *    correcta) y la clave buena en su propia columna `answer_key`, que no se
 *    serializa jamás. La semilla baraja el orden de PINTADO y nada más: se
 *    responde por clave, así que un barajado distinto no puede calificar mal.
 *  - `escucha`: la mecánica de `choice` con un clip delante (`audio_src`).
 *    La `transcripcion` es columna propia y oculta, como la clave: se revela
 *    en el veredicto, nunca en `next` — leerla antes mataría el ejercicio.
 */
class PracticeItem extends Model
{
    use HasFactory, HasUuids;

    public const NUMERIC = 'numeric';

    public const CHOICE = 'choice';

    public const ESCUCHA = 'escucha';

    public const HUECO = 'hueco';

    public const ORDEN = 'orden';

    public const PARES = 'pares';

    public const DICTADO = 'dictado';

    protected $guarded = [];

    protected $casts = [
        'statement' => 'array',
        'params' => 'array',
        'options' => 'array',
        'solucion' => 'array',
        'attrs' => 'array',
        'tolerance' => 'float',
        'shuffle' => 'boolean',
    ];

    /**
     * `answer_key` NUNCA viaja al cliente. Ocultarlo aquí no es la defensa —la
     * defensa es que el payload de `next` se arma por lista blanca— pero cierra
     * el descuido de un `toArray()` en cualquier respuesta futura.
     */
    protected $hidden = ['answer_key', 'solution_expr', 'transcripcion', 'solucion'];

    /**
     * EL GUARDIÁN, al sembrar y no al pintar (#26), ahora DELEGADO EN EL TIPO.
     *
     * Cada kind declara su forma en su clase (`Tipos\*::alGuardar`): un hueco
     * sin lengua, un orden cuya secuencia no es permutación de sus palabras o
     * un escucha sin transcripción revientan donde los ve quien los escribe.
     * Y el vocabulario de kinds queda CERRADO de paso: `Registro::de` lanza
     * con un kind desconocido, así que un typo no se guarda — la lección del
     * 'simulator' de la portada (#25), esta vez en código que falla.
     *
     * La forma de `audio_src` se comprueba aquí para CUALQUIER kind que lo
     * lleve: es un invariante transversal (la ruta es del almacén o no es),
     * no una regla de un tipo.
     */
    protected static function booted(): void
    {
        static::saving(function (self $item) {
            if ($item->audio_src !== null
                && ! \App\Services\Audio\AlmacenDeAudio::esRutaPublicada($item->audio_src)) {
                throw new InvalidArgumentException(
                    'practice_items.audio_src tiene que ser una ruta del almacén '.
                    "(/audio/<hash>.<mp3|ogg|m4a>), no «{$item->audio_src}».",
                );
            }

            \App\Services\Practice\Tipos\Registro::de($item->kind ?? self::NUMERIC)
                ->alGuardar($item);
        });
    }

    public function esChoice(): bool
    {
        return $this->kind === self::CHOICE;
    }

    public function esEscucha(): bool
    {
        return $this->kind === self::ESCUCHA;
    }

    /**
     * ¿Se responde eligiendo una CLAVE inmutable? `choice` y `escucha`
     * comparten la mecánica entera —opciones sin marca, corrección por clave,
     * barajado solo de pintado— y por eso el motor pregunta esto y no el kind:
     * el siguiente tipo que responda por clave entra aquí y hereda todas las
     * garantías sin tocar una línea del controlador.
     */
    public function respondePorClave(): bool
    {
        return in_array($this->kind, [self::CHOICE, self::ESCUCHA], true);
    }

    /** @return list<array{key: string, text: array<string, string>}> */
    public function opciones(): array
    {
        return array_values($this->options ?? []);
    }

    /** Las claves válidas de este ítem: el conjunto que admite una respuesta. */
    public function clavesValidas(): array
    {
        return array_map(fn (array $o) => (string) $o['key'], $this->opciones());
    }

    public function objective(): BelongsTo
    {
        return $this->belongsTo(LearningObjective::class, 'objective_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PracticeAttempt::class, 'item_id');
    }
}
