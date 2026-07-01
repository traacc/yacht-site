<?php

namespace Database\Factories;

use App\Models\Regatta;
use App\Models\RegattaScheduleEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RegattaScheduleEvent>
 */
class RegattaScheduleEventFactory extends Factory
{
    protected $model = RegattaScheduleEvent::class;

    public function definition(): array
    {
        return [
            'id'             => (string) Str::uuid(),
            'regatta_id'     => Regatta::factory(),
            'name'           => $this->faker->sentence(3),
            'description'    => $this->faker->optional()->paragraph(),
            'event_datetime' => $this->faker->optional()->dateTimeBetween('now', '+1 year'),
            'sort_order'     => 0,
        ];
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
