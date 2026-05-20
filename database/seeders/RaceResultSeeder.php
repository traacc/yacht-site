<?php

namespace Database\Seeders;

use App\Models\RaceResult;
use Illuminate\Database\Seeder;

class RaceResultSeeder extends Seeder
{
    public function run(): void
    {
        RaceResult::factory()->count(15)->create();
    }
}
