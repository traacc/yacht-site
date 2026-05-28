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

        // 1. Upcoming/Closest → Active: регата уже началась, но ещё не закончилась
        $activated = Regatta::query()
            ->whereIn('regatta_status', [RegattaStatus::Upcoming->value, RegattaStatus::Closest->value])
            ->where('date_start', '<=', $now)
            ->where('date_end', '>=', $now)
            ->update(['regatta_status' => RegattaStatus::Active->value]);

        if ($activated > 0) {
            $this->info("Активировано регат: {$activated}");
        }

        // 2. Active → Finished: регата уже закончилась
        $finished = Regatta::query()
            ->where('regatta_status', RegattaStatus::Active->value)
            ->where('date_end', '<', $now)
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
            ->first();

        if ($closest !== null) {
            $closest->update(['regatta_status' => RegattaStatus::Closest->value]);
            $this->info("Ближайшая регата: «{$closest->name}» (старт: {$closest->date_start->format('d.m.Y')})");
        }

        $this->info('Статусы регат обновлены.');

        return self::SUCCESS;
    }
}
