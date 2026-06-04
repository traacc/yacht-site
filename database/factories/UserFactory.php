<?php

namespace Database\Factories;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

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
        $first_name = fake()->firstName();
        $last_name = fake()->lastName();
        $full_name = $first_name . ' ' . $last_name;
        return [
            'external_id'         => ++static::$externalIdCounter,
            'name'                => $full_name,
            'first_name'          => $first_name,
            'last_name'           => $last_name,
            'birth_date'          => fake()->optional()->dateTimeBetween('-60 years', '-16 years')?->format('Y-m-d'),
            'email'               => fake()->unique()->safeEmail(),
            'phone'               => fake()->unique()->optional()->e164PhoneNumber(),
            'phone_verified_at'   => fake()->optional()->dateTimeBetween('-1 year', 'now'),
            'email_verified_at'   => now(),
            'system_role'         => SystemRole::User,
            'password'            => static::$password ??= 'password',
            'remember_token'      => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    // ── States ────────────────────────────────────────────────────────────────

    /** Phone verified */
    public function phoneVerified(): static
    {
        return $this->state(fn () => [
            'phone'             => fake()->unique()->e164PhoneNumber(),
            'phone_verified_at' => now(),
        ]);
    }

    /** Specific role shortcuts */
    public function admin(): static
    {
        return $this->state(fn () => ['system_role' => SystemRole::Admin]);
    }

    public function judge(): static
    {
        return $this->state(fn () => ['system_role' => SystemRole::Judge]);
    }

    public function secretary(): static
    {
        return $this->state(fn () => ['system_role' => SystemRole::Secretary]);
    }

    public function accountant(): static
    {
        return $this->state(fn () => ['system_role' => SystemRole::Accountant]);
    }
}
