<?php

namespace Database\Factories;

use App\Models\Framework;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Framework> */
class FrameworkFactory extends Factory
{
    protected $model = Framework::class;

    public function definition(): array
    {
        return [
            'code' => 'FW-'.fake()->unique()->numerify('#####'),
            'authority' => 'MINEDEC',
            'kind' => 'national',
            'country' => 'EC',
            'label' => ['es' => 'Marco de prueba'],
        ];
    }
}
