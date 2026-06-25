<?php

namespace App\Observers;

use App\Enums\PaymentStatus;
use App\Models\RegattaEntry;

/**
 * Синхронизирует отметку об оплате сбора на заявке (fee_paid)
 * со статусом связанных записей в реестре платежей.
 *
 * Обновление реестра выполняется через updateQuietly(), чтобы не запускать
 * PaymentRegistryObserver и не создавать циклов.
 *
 * Регистрируется в AppServiceProvider:
 *   RegattaEntry::observe(RegattaEntryFeeObserver::class);
 */
class RegattaEntryFeeObserver
{
    public function updated(RegattaEntry $entry): void
    {
        if (! $entry->wasChanged('fee_paid')) {
            return;
        }

        $status = $entry->fee_paid ? PaymentStatus::Paid : PaymentStatus::Pending;

        foreach ($entry->paymentRegistries as $registry) {
            // Уже в нужном состоянии — не трогаем (в т.ч. ручные Partial/Overdue/Canceled
            // перетираем только при явной смене галочки оплаты).
            if ($registry->status === $status) {
                continue;
            }

            $registry->updateQuietly(['status' => $status]);
        }
    }
}
