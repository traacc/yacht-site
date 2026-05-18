<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TeamMemberFactory>
 */
class TeamMemberFactory extends Factory
{
    protected $model = TeamMember::class;

    public function definition(): array
    {
        // Determine status first to conditionally set joined_at
        $status = $this->faker->randomElement(['invited', 'active', 'declined']);

        return [
            'id'         => (string) Str::uuid(),
            'team_id'    => Team::factory(),
            'user_id'    => User::factory(),
            'role'       => $this->faker->randomElement(['organizer', 'admin', 'member']),
            'status'     => $status,
            'joined_at'  => $status === 'active' ? $this->faker->dateTimeBetween('-1 year', 'now') : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}