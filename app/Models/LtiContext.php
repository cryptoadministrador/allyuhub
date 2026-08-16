<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un curso de Moodle visto desde la Tool: llega en el claim `context` del
 * launch validado. El mapeo curso→track vive aquí (nullable: lo asigna el
 * docente desde su panel).
 */
class LtiContext extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function platform(): BelongsTo
    {
        return $this->belongsTo(LtiPlatform::class, 'platform_id');
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class, 'track_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(LtiContextMembership::class, 'lti_context_id');
    }

    /** ¿Este usuario es instructor de ESTE contexto? (la autorización del panel). */
    public function esInstructor(int $userId): bool
    {
        return $this->memberships()
            ->where('user_id', $userId)
            ->where('role', 'instructor')
            ->exists();
    }
}
