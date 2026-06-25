<?php

namespace App\Observers;

use App\Enums\PaymentStatus;
use App\Models\PaymentRegistry;
use App\Models\RegattaEntry;

/**
 * Синхронизирует статус записи реестра платежей с отметкой об оплате
 * на связанной заявке (RegattaEntry::fee_paid).
 *
 * Обновление заявки выполняется через updateQuietly(), чтобы не запускать
 * RegattaEntryFeeObserver и не создавать циклов.
 *
 * Регистрируется в AppServiceProvider:
 *   PaymentRegistry::observe(PaymentRegistryObserver::class);
 */
class PaymentRegistryObserver
{
    public function updated(PaymentRegistry $registry): void
    {
        if (! $registry->wasChanged('status')) {
            return;
        }

        $payable = $registry->payable;

        if (! $payable instanceof RegattaEntry) {
            return;
        }

        $feePaid = $registry->status === PaymentStatus::Paid;

        if ((bool) $payable->fee_paid === $feePaid) {
            return;
        }

        $payable->updateQuietly(['fee_paid' => $feePaid]);
    }
}
