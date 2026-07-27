<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\PaymentRegistry;
use App\Services\PaymentRegistryLogger;

/**
 * Пишет журнал изменений реестра платежей и проставляет «последнего изменившего».
 *
 * Отдельный обсервер от PaymentRegistryObserver: тот отвечает за бизнес-синхронизацию
 * fee_paid на заявке, здесь — только аудит.
 *
 * ВНИМАНИЕ: изменения через updateQuietly()/saveQuietly() и Query Builder событий
 * модели не порождают — в таких местах логгер нужно вызывать явно
 * (@see \App\Observers\RegattaEntryFeeObserver).
 *
 * Регистрируется в AppServiceProvider:
 *   PaymentRegistry::observe(PaymentRegistryLogObserver::class);
 */
class PaymentRegistryLogObserver
{
    public function __construct(
        private readonly PaymentRegistryLogger $logger,
    ) {}

    /** Проставляем «кто изменил» в том же UPDATE — без лишнего запроса. */
    public function saving(PaymentRegistry $registry): void
    {
        if (! $registry->exists) {
            // При создании достаточно события Created.
            return;
        }

        if (! $registry->isDirty(array_keys(PaymentRegistryLogger::TRACKED))) {
            return;
        }

        // null — изменение выполнила система (вебхук, консоль, публичная форма).
        $registry->updated_by = auth()->id();
    }

    public function created(PaymentRegistry $registry): void
    {
        if ($this->logger->isMuted()) {
            return;
        }

        $this->logger->created($registry);
    }

    public function updated(PaymentRegistry $registry): void
    {
        if ($this->logger->isMuted()) {
            return;
        }

        $this->logger->updated($registry);
    }

    /**
     * Пишем в deleting, а не в deleted: при forceDelete() запись реестра
     * к моменту deleted уже удалена и вставка лога упала бы по внешнему ключу.
     */
    public function deleting(PaymentRegistry $registry): void
    {
        if ($this->logger->isMuted()) {
            return;
        }

        $this->logger->deleting($registry);
    }

    public function restored(PaymentRegistry $registry): void
    {
        if ($this->logger->isMuted()) {
            return;
        }

        $this->logger->restored($registry);
    }
}
