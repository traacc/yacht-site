<?php

namespace Database\Factories;

use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Season>
 */
class SeasonFactory extends Factory
{
    protected $model = Season::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->unique()->numberBetween(2025, 2028);

        return [
            'year'       => $year,
            'start_date' => now()->setYear($year)->startOfYear()->toDateString(),
            'end_date'   => now()->setYear($year)->endOfYear()->toDateString(),
        ];
    }
}
