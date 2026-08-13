<?php

namespace Database\Factories;

use App\Models\CurNode;
use App\Models\LearningObjective;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LearningObjective> */
class LearningObjectiveFactory extends Factory
{
    protected $model = LearningObjective::class;

    public function definition(): array
    {
        return [
            'node_id' => CurNode::factory(),
            // La versión DEBE ser la del nodo (regla 2 de CLAUDE.md: la clave es
            // framework+versión+código): se deriva del nodo ya creado.
            'version_id' => fn (array $attributes) => CurNode::find($attributes['node_id'])->version_id,
            'native_code' => 'CN.T.'.fake()->unique()->numerify('#.#.##'),
            'statement' => ['es' => 'Destreza de prueba'],
            'is_verified' => true,
        ];
    }
}
