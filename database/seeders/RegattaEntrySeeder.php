<?php

namespace Database\Seeders;

use App\Models\Regatta;
use App\Models\RegattaEntry;
use App\Models\Team;
use Illuminate\Database\Seeder;

class RegattaEntrySeeder extends Seeder
{
    public function run(): void
    {
        $regattas = Regatta::all();
        $teams = Team::all();

        if ($regattas->isEmpty() || $teams->isEmpty()) {
            return;
        }

        foreach ($regattas as $regatta) {
            $teams->random(rand(1, min(5, $teams->count())))->each(function ($team) use ($regatta) {
                $status = ['approved'][array_rand(['approved'])];

                RegattaEntry::factory()->$status()->create([
                    'regatta_id' => $regatta->id,
                    'team_id' => $team->id,
                ]);
            });
        }
    }
}
