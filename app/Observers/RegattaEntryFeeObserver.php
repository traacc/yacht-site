<?php

namespace App\Observers;

use App\Enums\PaymentStatus;
use App\Models\RegattaEntry;
use App\Services\PaymentRegistryLogger;

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

            // Тихое сохранение разрывает цикл с PaymentRegistryObserver, но не
            // порождает событий модели — поэтому журнал изменений пишем явно,
            // передавая снимок значений до изменения.
            $before = ['status' => $registry->status];

            $registry->forceFill([
                'status' => $status,
                'updated_by' => auth()->id(),
            ])->saveQuietly();

            app(PaymentRegistryLogger::class)->updatedQuietly($registry, $before);
        }
    }
}
