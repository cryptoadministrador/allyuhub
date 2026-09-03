<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Que un alumno completó un diálogo, UNA vez (único por diálogo+alumno). El
 * interlocutor no evalúa: esto es todo lo que registra, y de aquí sube el
 * dominio del descriptor una sola vez.
 */
class DialogoCompletion extends Model
{
    use HasUuids;

    protected $table = 'dialogo_completions';

    protected $guarded = [];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function dialogo(): BelongsTo
    {
        return $this->belongsTo(Dialogo::class, 'dialogo_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
