<?php

namespace Database\Factories;

use App\Models\Framework;
use App\Models\FrameworkVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FrameworkVersion> */
class FrameworkVersionFactory extends Factory
{
    protected $model = FrameworkVersion::class;

    public function definition(): array
    {
        return [
            'framework_id' => Framework::factory(),
            'label' => 'v'.fake()->unique()->numerify('####'),
        ];
    }
}
