<?php

namespace Database\Factories;

use App\Models\Regatta;
use App\Models\RegattaResult;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegattaResult>
 */
class RegattaResultFactory extends Factory
{
    protected $model = RegattaResult::class;

    public function definition(): array
    {
        return [
            'id' => fake()->uuid(),
            'regatta_id' => Regatta::factory(),
            'team_id' => Team::factory(),
            'total_points' => fake()->randomFloat(3, 0, 100),
            'final_position' => fake()->numberBetween(1, 50),
        ];
    }
}
