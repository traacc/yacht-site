<?php

namespace Database\Seeders;

use App\Models\RegattaResult;
use Illuminate\Database\Seeder;

class RegattaResultSeeder extends Seeder
{
    public function run(): void
    {
        RegattaResult::factory()->count(20)->create();
    }
}
