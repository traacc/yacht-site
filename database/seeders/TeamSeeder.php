<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        foreach (range(1, 5) as $i) {
            Team::factory()
                ->create();

            gc_collect_cycles();
        }

        // Create one archived team
        Team::factory()
            ->archived()
            ->create();

        // Create one team without organizer
        Team::factory()
            ->withoutOrganizer()
            ->create();
    }
}
