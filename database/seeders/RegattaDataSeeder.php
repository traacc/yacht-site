<?php

namespace Database\Seeders;

use App\Models\Regatta;
use App\Models\RegattaEntry;
use App\Models\RegattaResult;
use App\Models\RaceResult;
use Illuminate\Database\Seeder;

class RegattaDataSeeder extends Seeder
{
    public function run(): void
    {
        Regatta::all()->each(function (Regatta $regatta) {
            RegattaEntry::factory()
                ->count(5)
                ->for($regatta)
                ->create()
                ->each(function (RegattaEntry $entry) use ($regatta) {
                    $result = RegattaResult::factory()
                        ->for($entry)
                        ->for($regatta)
                        ->create();

                    RaceResult::factory()
                        ->count(3)
                        ->for($result)
                        ->create();
                });
        });
    }
}
