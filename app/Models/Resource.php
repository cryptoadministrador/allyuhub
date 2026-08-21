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
     * La firma se le EXIGE A LA LECCIÓN, no a todo recurso, y la diferencia no
     * es de comodidad: una lección la produce un sembrador a decenas, y el daño
     * que la puerta evita es que ese lote llegue al alumno sin que nadie lo
     * haya leído. Un simulador es otra cosa — un puntero a un bundle de un CDN
     * que un operador da de alta uno a uno—, y meterlo en la misma puerta
     * habría dejado sin recursos, en silencio, al catálogo y al Deep Linking de
     * Moodle en cuanto alguien registrara el siguiente. Ampliar la puerta a los
     * simuladores es una decisión aparte y hay que tomarla mirándolos a ellos.
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->where(fn ($q) => $q
                ->where('kind', '!=', self::LECTURA)
                ->orWhereHas('currentVersion', fn ($v) => $v->whereNotNull('reviewed_at')));
    }

    /** ¿Es una lección de texto? */
    public function esLeccion(): bool
    {
        return $this->kind === self::LECTURA;
    }
}
