<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Season;
use App\Services\RatingCalculator;
use Illuminate\Console\Command;

class RecalculateRatings extends Command
{
    protected $signature = 'ratings:recalculate {season? : Год сезона (например, 2026). Если не указан — пересчитываются все сезоны}';

    protected $description = 'Пересчитывает командный рейтинг по результатам регат.';

    public function handle(RatingCalculator $calculator): int
    {
        $year = $this->argument('season');

        $seasons = $year !== null
            ? Season::where('year', $year)->get()
            : Season::orderBy('year')->get();

        if ($seasons->isEmpty()) {
            $this->error($year !== null
                ? "Сезон {$year} не найден."
                : 'Сезоны не найдены.');

            return self::FAILURE;
        }

        foreach ($seasons as $season) {
            $calculator->recalculateForSeason($season);
            $this->info("Пересчитан рейтинг сезона {$season->year}.");
        }

        $this->info('Готово.');

        return self::SUCCESS;
    }
}
