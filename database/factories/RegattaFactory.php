<?php

namespace Database\Factories;

use App\Models\Regatta;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Regatta>
 */
class RegattaFactory extends Factory
{
    protected $model = Regatta::class;

    /**
     * Auto-incrementing counter for external_id.
     */
    protected static int $externalIdCounter = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 month', '+6 months');
        $end = (clone $start)->modify(sprintf('+%d days', fake()->numberBetween(0, 3)));

        return [
            'season_id'         => Season::factory(),
            'series_id'         => null,
            'external_id'       => ++static::$externalIdCounter,
            'name'              => fake()->sentence(3),
            'level_coefficient' => fake()->randomFloat(2, 0.50, 3.00),
            'date_start'        => $start->format('Y-m-d'),
            'date_end'          => $end->format('Y-m-d'),
            'location'          => fake()->country(),
            'water_area'        => fake()->city(),
            'description'       => fake()->paragraph(),
            //'schedule'          => fake()->paragraphs(2, true),
            'race_days_count'   => fake()->numberBetween(1, 4),
            'races_count'       => fake()->numberBetween(1, 8),
            'prizes'            => fake()->sentence(),
        ];
    }
}
