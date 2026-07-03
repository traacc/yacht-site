<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Scopes\OwnedScope;
use App\Models\Yacht;
use Illuminate\Console\Command;

class PruneOrphanYachts extends Command
{
    protected $signature = 'yachts:prune-orphans
                            {--force : Удалить безвозвратно (forceDelete), минуя мягкое удаление}';

    protected $description = 'Показывает яхты без владельца (user_id IS NULL) и без каких-либо связей, затем удаляет их после подтверждения.';

    /**
     * Связи, наличие любой из которых означает, что яхту трогать нельзя.
     */
    private const RELATIONS = [
        'regattaEntries',
        'rentals',
        'rentalRequests',
        'documents',
        'optionValues',
    ];

    public function handle(): int
    {
        $query = Yacht::query()
            ->withoutGlobalScope(OwnedScope::class)
            ->whereNull('user_id');

        foreach (self::RELATIONS as $relation) {
            $query->whereDoesntHave($relation);
        }

        $yachts = $query->orderBy('name')->get();

        if ($yachts->isEmpty()) {
            $this->info('Яхт без владельца и без связей не найдено.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Название', 'ВФПС №', 'Создана'],
            $yachts->map(fn (Yacht $yacht) => [
                $yacht->id,
                $yacht->name,
                $yacht->vfps_number,
                $yacht->created_at?->format('Y-m-d'),
            ])->all()
        );

        $count = $yachts->count();
        $force = (bool) $this->option('force');
        $mode  = $force ? 'безвозвратно (forceDelete)' : 'мягко (soft delete)';

        if (! $this->confirm("Удалить {$mode} {$count} шт.?", false)) {
            $this->info('Отменено, ничего не удалено.');

            return self::SUCCESS;
        }

        foreach ($yachts as $yacht) {
            $force ? $yacht->forceDelete() : $yacht->delete();
        }

        $this->info("Удалено: {$count}.");

        return self::SUCCESS;
    }
}
