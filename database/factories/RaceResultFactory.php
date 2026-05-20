<?php

namespace Database\Factories;

use App\Models\RaceResult;
use App\Models\RegattaEntry;
use App\Models\RegattaEvents;
use App\Enums\PenaltyCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RaceResultFactory extends Factory
{
    protected $model = RaceResult::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid(),
            'event_id' => RegattaEvents::factory(),
            'regatta_entry_id' => RegattaEntry::factory(),
            'position' => $this->faker->numberBetween(1, 20),
            'points' => $this->faker->randomFloat(1, 0, 50),
            'penalty_code' => $this->faker->optional()->randomElement(array_column(PenaltyCode::cases(), 'value')),
        ];
    }
}
