<?php

namespace Database\Seeders;

use App\Models\RegattaResult;
use App\Models\RegattaResultItem;
use Illuminate\Database\Seeder;

class RegattaResultSeeder extends Seeder
{
    public function run(): void
    {
        // Создаём заголовки результатов
        RegattaResult::factory()->count(5)->create()->each(function (RegattaResult $result) {
            // К каждому заголовку — 3-8 позиций команд
            RegattaResultItem::factory()
                ->count(fake()->numberBetween(3, 8))
                ->sequence(fn ($sequence) => ['final_position' => $sequence->index + 1])
                ->create(['regatta_result_id' => $result->id]);
        });
    }
}
