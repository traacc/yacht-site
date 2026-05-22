<?php

namespace Database\Factories;

use App\Models\Regatta;
use App\Models\RegattaResult;
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
            'regatta_id' => Regatta::query()->inRandomOrder()->first()?->id ?? Regatta::factory(),
            'result_type' => fake()->randomElement(['preliminary', 'final']),
            'source'      => 'manual',
        ];
    }
}

