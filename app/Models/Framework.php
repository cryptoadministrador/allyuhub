<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Framework extends Model
{
    use HasUuids;

    protected $guarded = [];
    protected $casts = ['label' => 'array'];

    public function versions(): HasMany
    {
        return $this->hasMany(FrameworkVersion::class);
    }
}
