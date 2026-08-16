<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La pertenencia de un usuario a un curso de Moodle, con su rol EN ESE curso
 * (instructor|learner). El rol jamás vive en users: es por contexto.
 */
class LtiContextMembership extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'last_launched_at' => 'datetime',
    ];

    public function context(): BelongsTo
    {
        return $this->belongsTo(LtiContext::class, 'lti_context_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
