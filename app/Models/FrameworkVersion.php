<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FrameworkVersion extends Model
{
    use HasUuids;

    protected $guarded = [];
    protected $casts = ['valid_from' => 'date', 'valid_to' => 'date'];

    public function framework(): BelongsTo
    {
        return $this->belongsTo(Framework::class);
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(CurNode::class, 'version_id');
    }

    public function roots(): HasMany
    {
        return $this->nodes()->whereNull('parent_id')->orderBy('seq');
    }
}
