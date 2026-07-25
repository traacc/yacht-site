<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\PaymentRegistry;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Payments\Data\CreatePaymentRequest;
use App\Services\Payments\PaymentManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Инициирует онлайн-оплату записи реестра платежей: создаёт транзакцию,
 * регистрирует платёж у активного провайдера и возвращает транзакцию
 * с confirmation_url для редиректа плательщика.
 */
final class StartOnlinePaymentAction
{
    /** Свежая pending-транзакция переиспользуется (защита от двойных кликов). */
    private const REUSE_WINDOW_MINUTES = 30;

    public function __construct(
        private readonly PaymentManager $payments,
    ) {}

    public function handle(PaymentRegistry $registry, User $actor): PaymentTransaction
    {
        $provider = $this->payments->isEnabled() ? $this->payments->activeProvider() : null;

        if ($provider === null) {
            throw ValidationException::withMessages([
                'payment' => 'Онлайн-оплата временно недоступна. Попробуйте позже или свяжитесь с администрацией.',
            ]);
        }

        if ($registry->status === PaymentStatus::Paid) {
            throw ValidationException::withMessages([
                'payment' => 'Этот платёж уже оплачен.',
            ]);
        }

        if ((float) $registry->amount <= 0) {
            throw ValidationException::withMessages([
                'payment' => 'Сумма платежа не указана. Свяжитесь с администрацией.',
            ]);
        }

        // Переиспользуем недавнюю незавершённую попытку вместо создания дубля.
        $existing = $registry->transactions()
            ->where('status', PaymentTransactionStatus::Pending->value)
            ->where('provider', $provider->code()->value)
            ->whereNotNull('confirmation_url')
            ->where('created_at', '>=', now()->subMinutes(self::REUSE_WINDOW_MINUTES))
            ->latest()
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $transaction = $registry->transactions()->create([
            'user_id' => $actor->id,
            'provider' => $provider->code(),
            'status' => PaymentTransactionStatus::Pending,
            'amount' => $registry->amount,
            'currency' => 'RUB',
            'description' => Str::limit($registry->name, 480),
            'idempotence_key' => (string) Str::uuid(),
        ]);

        try {
            $result = $provider->createPayment(
                new CreatePaymentRequest(
                    amount: (string) $registry->amount,
                    currency: 'RUB',
                    description: $transaction->description,
                    returnUrl: route('payments.return', $transaction),
                    metadata: ['transaction_id' => $transaction->id],
                ),
                $transaction->idempotence_key,
            );
        } catch (Throwable $e) {
            $transaction->update([
                'status' => PaymentTransactionStatus::Failed,
                'failure_reason' => Str::limit($e->getMessage(), 480),
            ]);

            Log::warning('Не удалось создать платёж у провайдера', [
                'transaction_id' => $transaction->id,
                'provider' => $provider->code()->value,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'payment' => 'Не удалось создать платёж. Попробуйте позже.',
            ]);
        }

        $transaction->update([
            'external_id' => $result->externalId,
            'confirmation_url' => $result->confirmationUrl,
            'payload' => $result->raw ?: null,
        ]);

        return $transaction;
    }
}
