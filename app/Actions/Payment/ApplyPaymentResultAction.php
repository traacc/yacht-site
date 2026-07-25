<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Mail\PaymentSucceeded;
use App\Models\PaymentTransaction;
use App\Services\Payments\Data\PaymentResult;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Идемпотентно применяет результат платежа к транзакции и реестру.
 * Единственная точка перевода транзакции в финальный статус — сюда сходятся
 * вебхук, return-страница и payments:reconcile, поэтому двойной вебхук
 * и гонки между источниками безопасны.
 */
final class ApplyPaymentResultAction
{
    /** @return bool true — статус применён этим вызовом, false — уже был применён/нефинальный. */
    public function handle(PaymentTransaction $transaction, PaymentResult $result): bool
    {
        if (! $result->status->isFinal()) {
            if ($result->raw !== []) {
                $transaction->update(['payload' => $result->raw]);
            }

            return false;
        }

        // Атомарный захват: только один источник переводит Pending в финальный статус.
        $claimed = PaymentTransaction::query()
            ->whereKey($transaction->id)
            ->where('status', PaymentTransactionStatus::Pending->value)
            ->update([
                'status' => $result->status->value,
                'paid_at' => $result->paidAt,
                'failure_reason' => $result->failureReason,
                'payload' => $result->raw !== [] ? json_encode($result->raw, JSON_UNESCAPED_UNICODE) : null,
                'updated_at' => now(),
            ]);

        if ($claimed === 0) {
            return false;
        }

        $transaction->refresh();

        if ($transaction->status !== PaymentTransactionStatus::Succeeded) {
            return true;
        }

        // Обычный update(), чтобы сработал PaymentRegistryObserver → fee_paid.
        $transaction->registry?->update([
            'status' => PaymentStatus::Paid,
            'payment_method' => PaymentMethod::Online,
            'paid_at' => $transaction->paid_at ?? now(),
        ]);

        $this->notify($transaction);

        return true;
    }

    /** Письма плательщику и администраторам; сбой почты не должен ломать оплату. */
    private function notify(PaymentTransaction $transaction): void
    {
        $recipients = collect([$transaction->user?->email])
            ->merge(app(SettingsService::class)->adminNotificationEmails())
            ->filter()
            ->unique()
            ->values();

        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send(new PaymentSucceeded($transaction));
            } catch (Throwable $e) {
                Log::warning('Не удалось отправить письмо об оплате', [
                    'transaction_id' => $transaction->id,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
