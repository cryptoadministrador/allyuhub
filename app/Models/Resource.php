<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un recurso se publica UNA vez y se alinea con N objetivos de N marcos:
 * sirve al niño de ordinaria, al adulto PCEI y al colegio Cambridge/IB sin duplicarse.
 */
class Resource extends Model
{
    use HasUuids;

    /** `resources.kind` admite más, pero la lección de texto es esta. */
    public const LECTURA = 'reading';

    /**
     * El vocabulario de `kind` lo declara la migración de `resources`:
     * simulation|lab|video|reading|practice_set|project. Estas constantes
     * existen porque la portada llevaba escrito `'simulator'` —que no está en
     * esa lista y no lo escribe nadie— y contaba cero simuladores para
     * siempre. Una cadena suelta no se puede equivocar en voz alta.
     */
    public const SIMULACION = 'simulation';

    public const LABORATORIO = 'lab';

    /** Los dos kinds que un alumno reconoce como «laboratorio». */
    public const INTERACTIVOS = [self::SIMULACION, self::LABORATORIO];

    /**
     * PROCEDENCIA. Espejo de `practice_items.origen`, y lo que decide si un
     * recurso necesita firma docente para salir (ver `scopePublished`).
     */
    public const CURADO = 'curado';

    public const GENERADO = 'generado';

    protected $guarded = [];
    protected $casts = ['title' => 'array', 'summary' => 'array', 'a11y' => 'array'];

    public function versions(): HasMany
    {
        return $this->hasMany(ResourceVersion::class);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ResourceVersion::class, 'current_version_id');
    }

    public function objectives(): BelongsToMany
    {
        return $this->belongsToMany(LearningObjective::class, 'resource_objectives',
            'resource_id', 'objective_id')->withPivot('role');
    }

    /**
     * Lo que un alumno puede ver: publicado Y con su versión vigente FIRMADA.
     *
     * Las dos condiciones viven aquí, en el scope, y no en cada consulta,
     * porque de `published()` cuelgan la ficha de la destreza, la API de
     * recursos, el Deep Linking de Moodle y el propio `/recurso`. Con la puerta
     * repartida, una de esas rutas se quedaría sin ella y publicaría una
     * lección sin revisar — que es exactamente el daño que la puerta evita.
     *
     * Un contenido mal escrito hace más daño que una pregunta mal escrita: la
     * pregunta se falla y se corrige; el texto se cree.
     *
     * La firma se le exige a lo GENERADO, no a un `kind` concreto. Esta línea
     * decía `kind != 'reading'` y era cierta por una circunstancia —hoy lo
     * único que se produce a escala son lecciones—, no por naturaleza. La Fase
     * 2 son simuladores DECLARATIVOS generados por un pipeline de IA, y con la
     * regla atada al tipo habrían entrado por `kind = 'simulation'` y salido al
     * alumno sin que nadie tocara este método: el agujero se abría solo.
     *
     * Lo que de verdad distingue a los dos casos es de dónde vienen. Un lote
     * que produce una máquina necesita que alguien lo lea antes; un simulador
     * que un operador da de alta uno a uno ya pasó por unos ojos al registrarse
     * — y meterlo en la misma puerta habría vaciado en silencio el catálogo y
     * el Deep Linking en cuanto se registrara el siguiente.
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->where(fn ($q) => $q
                ->where('origen', '!=', self::GENERADO)
                ->orWhereHas('currentVersion', fn ($v) => $v->whereNotNull('reviewed_at')));
    }

    /** ¿Es una lección de texto? */
    public function esLeccion(): bool
    {
        return $this->kind === self::LECTURA;
    }

    /** ¿Lo produjo una máquina? Entonces necesita firma para salir. */
    public function esGenerado(): bool
    {
        return $this->origen === self::GENERADO;
    }
}
