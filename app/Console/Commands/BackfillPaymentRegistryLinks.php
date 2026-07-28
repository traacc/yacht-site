<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Payment\SyncPaymentRegistryLinksAction;
use App\Models\PaymentRegistry;
use Illuminate\Console\Command;

/**
 * Разовое (и идемпотентное) заполнение денормализованных связей в реестре
 * платежей для записей, созданных до внедрения группировки.
 */
class BackfillPaymentRegistryLinks extends Command
{
    protected $signature = 'payments:backfill-links
                            {--chunk=500 : Размер порции}
                            {--force : Перезаписывать уже заполненные назначение и плательщика}
                            {--dry-run : Только показать, ничего не сохранять}';

    protected $description = 'Заполнить регату, яхту, команду и назначение в реестре платежей по связанному источнику';

    public function handle(SyncPaymentRegistryLinksAction $sync): int
    {
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $processed = 0;
        $updated = 0;

        PaymentRegistry::withTrashed()
            ->whereNotNull('payable_id')
            ->with('payable')
            ->orderBy('id')
            ->chunkById((int) $this->option('chunk'), function ($registries) use ($sync, $force, $dryRun, &$processed, &$updated): void {
                foreach ($registries as $registry) {
                    $processed++;

                    if (! $sync->handle($registry, force: $force)) {
                        continue;
                    }

                    $updated++;

                    if (! $dryRun) {
                        // Тихо: технический прогон не должен засорять журнал
                        // и перетирать «последнего изменившего» (в консоли auth пуст).
                        $registry->saveQuietly();
                    }
                }
            });

        $this->info($dryRun
            ? "Просмотрено: {$processed}, требует обновления: {$updated} (изменения не сохранены)."
            : "Просмотрено: {$processed}, обновлено: {$updated}.");

        return self::SUCCESS;
    }
}
