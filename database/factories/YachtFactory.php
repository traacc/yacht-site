<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Yacht;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class YachtFactory extends Factory
{
    protected $model = Yacht::class;

    public function definition(): array
    {
        $forRent = $this->faker->boolean(20); // 20% chance of being for rent

        return [
            'id' => $this->faker->uuid(),
            'name' => $this->faker->company() . ' ' . $this->faker->randomElement(['Yacht', 'Sail', 'Boat']),
            'vfps_number' => $this->faker->unique()->bothify('VFPS-#####'),
            'user_id' => $this->faker->boolean(80) ? User::factory() : null,
            'gims_number' => $this->faker->optional(0.7)->bothify('GIMS-####'),
            'orc_cert_url' => $this->faker->optional(0.5)->url(),
            'class' => $this->faker->optional(0.6)->randomElement(['A', 'B', 'C', 'Cruiser', 'Racer']),
            //'sail_type' => $this->faker->optional(0.8)->randomElement(['dacron', 'laminate', 'mixed']),
            'project' => $this->faker->optional(0.7)->bothify('Proj-###'),
            'year' => $this->faker->optional(0.9)->numberBetween(1980, date('Y')),
            'reg_place' => $this->faker->optional(0.8)->city(),
            'current_mass_kg' => $this->faker->optional(0.7)->randomFloat(2, 500, 5000),
            //'for_rent' => $forRent,
            //'rent_price' => $forRent ? $this->faker->randomFloat(2, 50, 2000) : null,
            'approval_status' => $this->faker->randomElement(['pending', 'approved', 'rejected']),
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null, // not trashed by default
        ];
    }

    /**
     * Indicate that the yacht is available for rent.
     */
    public function rental(): static
    {
        return $this->state(fn (array $attributes) => [
            'for_rent' => true,
            'rent_price' => $this->faker->randomFloat(2, 50, 2000),
        ]);
    }

    /**
     * Indicate that the yacht is not for rent.
     */
    public function notForRent(): static
    {
        return $this->state(fn (array $attributes) => [
            'for_rent' => false,
            'rent_price' => null,
        ]);
    }

    /**
     * Set a specific approval status.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_status' => 'approved',
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_status' => 'rejected',
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_status' => 'pending',
        ]);
    }

    /**
     * Indicate that the yacht is soft-deleted.
     */
    public function trashed(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
        ]);
    }
}