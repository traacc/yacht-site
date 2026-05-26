<?php

namespace Database\Factories;

use App\Models\Regatta;
use App\Models\RegattaEvents;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RegattaEventsFactory>
 */
class RegattaEventsFactory extends Factory
{
    protected $model = RegattaEvents::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'regatta_id' => Regatta::factory(),
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->optional()->paragraph(),
            'event_datetime' => $this->faker->optional()->dateTimeBetween('now', '+1 year'),
            'event_type' => $this->faker->randomElement(['schedule', 'race']),
        ];
    }

    /**
     * Indicate that the event is a race.
     */
    public function race(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'race',
        ]);
    }

    /**
     * Indicate that the event is a schedule (non-race).
     */
    public function schedule(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'schedule',
        ]);
    }

    /**
     * Assign a specific regatta.
     */
    public function forRegatta(Regatta $regatta): static
    {
        return $this->state(fn (array $attributes) => [
            'regatta_id' => $regatta->id,
        ]);
    }
}