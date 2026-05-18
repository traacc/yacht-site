<?php

namespace Database\Factories;

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
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'                => fake()->firstName(),
            'first_name'          => fake()->firstName(),
            'last_name'           => fake()->lastName(),
            'birth_date'          => fake()->optional()->dateTimeBetween('-60 years', '-16 years')?->format('Y-m-d'),
            'email'               => fake()->unique()->safeEmail(),
            'phone'               => fake()->unique()->optional()->e164PhoneNumber(),
            'phone_verified_at'   => fake()->optional()->dateTimeBetween('-1 year', 'now'),
            'email_verified_at'   => now(),
            'system_role'         => 'user',
            'password'            => static::$password ??= Hash::make('password'),
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
        return $this->state(fn () => ['system_role' => 'admin']);
    }

    public function judge(): static
    {
        return $this->state(fn () => ['system_role' => 'judge']);
    }

    public function secretary(): static
    {
        return $this->state(fn () => ['system_role' => 'secretary']);
    }

    public function accountant(): static
    {
        return $this->state(fn () => ['system_role' => 'accountant']);
    }
}
