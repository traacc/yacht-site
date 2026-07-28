<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Payment\SyncPaymentRegistryLinksAction;
use App\Models\RegattaEntry;

/**
 * Пересинхронизирует денормализованные связи платежей при изменении заявки:
 * сменили яхту или команду — платежи должны уехать в другую группу, иначе
 * отчёт «по яхте» будет врать.
 *
 * Сохраняем обычным save() (в отличие от RegattaEntryFeeObserver): цикла нет,
 * а изменение должно попасть в журнал реестра.
 *
 * Регистрируется в AppServiceProvider:
 *   RegattaEntry::observe(RegattaEntryPaymentLinksObserver::class);
 */
class RegattaEntryPaymentLinksObserver
{
    public function updated(RegattaEntry $entry): void
    {
        if (! $entry->wasChanged(['regatta_id', 'yacht_id', 'team_id'])) {
            return;
        }

        $sync = app(SyncPaymentRegistryLinksAction::class);

        foreach ($entry->paymentRegistries as $registry) {
            if ($sync->handle($registry, force: true)) {
                $registry->save();
            }
        }
    }
}
