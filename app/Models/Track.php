<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Trayecto: una forma de recorrer el grafo de objetivos.
 * ORD (ordinaria, 13 grados) · PCEI-ALFA · PCEI-POST · PCEI-BSI · PCEI-BI · PCEI-VIRTUAL.
 */
class Track extends Model
{
    use HasUuids;

    protected $guarded = [];
    protected $casts = ['label' => 'array', 'attrs' => 'array'];

    public function phases(): HasMany
    {
        return $this->hasMany(TrackPhase::class)->orderBy('seq');
    }
}
