<?php

namespace Database\Factories;

use App\Models\Regatta;
use App\Models\RegattaEntry;
use App\Models\Team;
use App\Models\Yacht;
use App\Enums\EntryStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RegattaEntryFactory extends Factory
{
    protected $model = RegattaEntry::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid(),
            'regatta_id' => Regatta::factory(),
            'team_id' => Team::factory(),
            'yacht_id' => null,
            'status' => EntryStatus::Pending->value,
            'submitted_at' => now(),
        ];
    }

    public function pending(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => EntryStatus::Pending->value,
        ]);
    }
    public function approved(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => EntryStatus::Approved->value,
        ]);
    }

    public function rejected(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => EntryStatus::Rejected->value,
        ]);
    }

    public function withdrawn(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => EntryStatus::Withdrawn->value,
        ]);
    }
}
