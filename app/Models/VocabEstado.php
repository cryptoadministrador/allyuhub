<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lo que un ALUMNO dice de una tarjeta: «la sé» (`conocida_at` con fecha, la
 * última vez que lo dijo) o «todavía no» (nulo). Una fila por (alumno,
 * tarjeta). El invitado no tiene filas: su mazo vive en memoria.
 */
class VocabEstado extends Model
{
    use HasUuids;

    protected $table = 'vocab_estado';

    protected $guarded = [];

    protected $casts = [
        'conocida_at' => 'datetime',
    ];

    public function tarjeta(): BelongsTo
    {
        return $this->belongsTo(Tarjeta::class, 'tarjeta_id');
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
