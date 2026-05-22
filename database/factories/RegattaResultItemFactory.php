<?php

namespace Database\Factories;

use App\Models\RegattaResult;
use App\Models\RegattaResultItem;
use App\Models\Team;
use App\Models\Yacht;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegattaResultItem>
 */
class RegattaResultItemFactory extends Factory
{
    protected $model = RegattaResultItem::class;

    public function definition(): array
    {
        return [
            'id' => fake()->uuid(),
            'regatta_result_id' => RegattaResult::query()->inRandomOrder()->first()?->id ?? RegattaResult::factory(),
            'team_id' => Team::query()->inRandomOrder()->first()?->id ?? Team::factory(),
            'yacht_id' => Yacht::query()->inRandomOrder()->first()?->id ?? Yacht::factory(),
            'total_points' => fake()->randomFloat(3, 0, 100),
            'final_position' => fake()->numberBetween(1, 50),
        ];
    }
}
