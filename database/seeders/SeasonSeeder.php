<?php

namespace Database\Seeders;

use App\Models\Season;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SeasonSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $seasons = [
            [
                'year' => 2024,
                'start_date' => '2024-01-01',
                'end_date' => '2024-12-31',
            ],
            [
                'year' => 2025,
                'start_date' => '2025-01-01',
                'end_date' => '2025-12-31',
            ],
            [
                'year' => 2026,
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ],
        ];

        foreach ($seasons as $season) {
            Season::updateOrCreate(
                ['year' => $season['year']],
                $season
            );
        }
    }
}
