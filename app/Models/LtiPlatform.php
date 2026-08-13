<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Una Platform LTI 1.3 (Moodle) autorizada a lanzar la Tool.
 * Se administra con `php artisan lti:platform:add`, nunca por API.
 */
class LtiPlatform extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'deployment_ids' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
