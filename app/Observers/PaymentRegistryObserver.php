<?php

namespace App\Observers;

use App\Actions\Payment\SyncPaymentRegistryLinksAction;
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
    /**
     * Заполняет денормализованные связи (регата/яхта/команда/назначение/плательщик)
     * при создании платежа и при смене источника — в том же INSERT/UPDATE.
     *
     * ВАЖНО: этот обсервер зарегистрирован в AppServiceProvider ДО
     * PaymentRegistryLogObserver, и порядок менять нельзя — иначе журнал
     * и updated_by не увидят автозаполненные поля.
     */
    public function saving(PaymentRegistry $registry): void
    {
        if ($registry->exists && ! $registry->isDirty(['payable_type', 'payable_id'])) {
            return;
        }

        app(SyncPaymentRegistryLinksAction::class)->handle($registry);
    }

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
