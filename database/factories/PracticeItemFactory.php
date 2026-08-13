<?php

namespace Database\Factories;

use App\Models\LearningObjective;
use App\Models\PracticeItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PracticeItem> */
class PracticeItemFactory extends Factory
{
    protected $model = PracticeItem::class;

    public function definition(): array
    {
        return [
            'objective_id' => LearningObjective::factory(),
            'statement' => ['es' => 'Peso paralelo al plano: m={m} kg, θ={theta}°, g={g} m/s².'],
            'params' => [
                'm' => ['min' => 1, 'max' => 20, 'step' => 0.5],
                'theta' => ['min' => 10, 'max' => 45, 'step' => 1],
                'g' => ['const' => 9.8],
            ],
            'solution_expr' => 'm * g * sin(deg2rad(theta))',
            'tolerance' => 0.02,
            'tolerance_kind' => 'rel',
            'answer_unit' => 'N',
            'seq' => 0,
        ];
    }
}
