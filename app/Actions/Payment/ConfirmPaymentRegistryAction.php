<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\PaymentRegistryLogEvent;
use App\Models\PaymentRegistry;
use App\Models\User;
use App\Services\PaymentRegistryLogger;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Отметка бухгалтера о фактическом приходе средств по записи реестра
 * (и снятие такой отметки).
 */
final class ConfirmPaymentRegistryAction
{
    public function __construct(
        private readonly PaymentRegistryLogger $logger,
    ) {}

    /**
     * @param  bool  $confirmed  true — подтвердить приход, false — снять отметку.
     *
     * @throws AuthorizationException
     */
    public function handle(PaymentRegistry $registry, User $actor, bool $confirmed): PaymentRegistry
    {
        // Вторая линия обороны, независимая от видимости кнопки в Filament.
        if (! $actor->system_role->canManagePayments()) {
            throw new AuthorizationException('Подтверждать платежи могут только администратор и бухгалтер.');
        }

        if ($registry->isConfirmed() === $confirmed) {
            return $registry;
        }

        // Глушим авто-лог: событие пишем семантическое, а не «Изменение».
        $this->logger->withoutAutoLog(function () use ($registry, $actor, $confirmed): void {
            $registry->forceFill([
                'confirmed_at' => $confirmed ? now() : null,
                'confirmed_by' => $confirmed ? $actor->getKey() : null,
                'updated_by' => $actor->getKey(),
            ])->save();
        });

        $this->logger->record(
            $registry,
            $confirmed ? PaymentRegistryLogEvent::Confirmed : PaymentRegistryLogEvent::Unconfirmed,
            $this->logger->diff($registry),
        );

        return $registry;
    }
}
