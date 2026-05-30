<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TeamFactory>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    /**
     * Auto-incrementing counter for external_id.
     */
    protected static int $externalIdCounter = 0;

    public function definition(): array
    {
        return [
            'id' => Str::uuid(),
            'external_id' => ++static::$externalIdCounter,
            'name' => $this->faker->regattaTeamName(),
            'description' => $this->faker->optional()->text(200),
            'organizer_id' => User::factory(), // Creates a new user or can be null
            'is_archived' => false,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null, // SoftDeletes
        ];
    }

    /**
     * Indicate that the team is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_archived' => true,
        ]);
    }

    /**
     * Indicate that the team has no organizer.
     */
    public function withoutOrganizer(): static
    {
        return $this->state(fn (array $attributes) => [
            'organizer_id' => null,
        ]);
    }
}