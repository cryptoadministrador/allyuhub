<?php

namespace Database\Factories;

use App\Models\CurNode;
use App\Models\FrameworkVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CurNode> */
class CurNodeFactory extends Factory
{
    protected $model = CurNode::class;

    public function definition(): array
    {
        return [
            'version_id' => FrameworkVersion::factory(),
            'node_type' => 'grado',
            'native_code' => 'g'.fake()->unique()->numerify('###'),
            'title' => ['es' => 'Nodo de prueba'],
            'seq' => 0,
            // Alfanumérico con puntos: válido también como ltree si algún test corre en pgsql.
            'path' => 'test.n'.fake()->unique()->numerify('#####'),
        ];
    }
}
