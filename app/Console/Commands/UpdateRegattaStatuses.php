<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RegattaStatus;
use App\Models\Regatta;
use Illuminate\Console\Command;

class UpdateRegattaStatuses extends Command
{
    protected $signature = 'regattas:update-statuses';

    protected $description = 'Автоматически обновляет статусы регат в зависимости от текущего времени.';

    public function handle(): int
    {
        $now = now();
        $today = $now->format('Y-m-d');
        $time = $now->format('H:i:s');

        // 1. Upcoming/Closest → Active: регата уже началась, но ещё не закончилась
        $activated = Regatta::query()
            ->whereIn('regatta_status', [RegattaStatus::Upcoming->value, RegattaStatus::Closest->value])
            ->where(function ($q) use ($today, $time) {
                $q->where('date_start', '<', $today)
                  ->orWhere(function ($q) use ($today, $time) {
                      $q->where('date_start', '=', $today)
                        ->whereRaw("COALESCE(time_start, '12:00:00') <= ?", [$time]);
                  });
            })
            ->where(function ($q) use ($today, $time) {
                $q->where('date_end', '>', $today)
                  ->orWhere(function ($q) use ($today, $time) {
                      $q->where('date_end', '=', $today)
                        ->whereRaw("COALESCE(time_end, '12:00:00') >= ?", [$time]);
                  });
            })
            ->update(['regatta_status' => RegattaStatus::Active->value]);

        if ($activated > 0) {
            $this->info("Активировано регат: {$activated}");
        }

        // 2. Active → Finished: регата уже закончилась
        $finished = Regatta::query()
            ->where('regatta_status', RegattaStatus::Active->value)
            ->where(function ($q) use ($today, $time) {
                $q->where('date_end', '<', $today)
                  ->orWhere(function ($q) use ($today, $time) {
                      $q->where('date_end', '=', $today)
                        ->whereRaw("COALESCE(time_end, '12:00:00') < ?", [$time]);
                  });
            })
            ->update(['regatta_status' => RegattaStatus::Finished->value]);

        if ($finished > 0) {
            $this->info("Завершено регат: {$finished}");
        }

        // 3. Сбросить предыдущий Closest (если он ещё не стал Active/Finished)
        Regatta::query()
            ->where('regatta_status', RegattaStatus::Closest->value)
            ->update(['regatta_status' => RegattaStatus::Upcoming->value]);

        // 4. Назначить Closest ближайшей предстоящей регате
        $closest = Regatta::query()
            ->where('regatta_status', RegattaStatus::Upcoming->value)
            ->orderBy('date_start')
            ->orderBy('time_start')
            ->first();

        if ($closest !== null) {
            $closest->update(['regatta_status' => RegattaStatus::Closest->value]);
            $startInfo = $closest->startDateTime()->format('d.m.Y H:i');
            $this->info("Ближайшая регата: «{$closest->name}» (старт: {$startInfo})");
        }

        $this->info('Статусы регат обновлены.');

        return self::SUCCESS;
    }
}
