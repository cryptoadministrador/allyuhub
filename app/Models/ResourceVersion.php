<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceVersion extends Model
{
    use HasUuids;

    protected $guarded = [];
    protected $casts = ['config' => 'array', 'published_at' => 'datetime'];

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }
}
