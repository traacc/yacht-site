<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Payment\ApplyPaymentResultAction;
use App\Enums\PaymentTransactionStatus;
use App\Models\PaymentTransaction;
use App\Services\Payments\Data\PaymentResult;
use App\Services\Payments\PaymentManager;
use Illuminate\Console\Command;

/**
 * Сверка «зависших» pending-транзакций эквайринга: запрашивает актуальный
 * статус у провайдера (страховка от потерянных вебхуков), а безнадёжно
 * старые транзакции отменяет.
 */
class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile {--max-age-hours=48 : Отменять pending-транзакции старше этого срока}';

    protected $description = 'Сверить статусы незавершённых онлайн-платежей с провайдерами эквайринга';

    /** Не трогаем совсем свежие транзакции — плательщик ещё на странице оплаты. */
    private const MIN_AGE_MINUTES = 10;

    public function handle(PaymentManager $payments, ApplyPaymentResultAction $apply): int
    {
        $maxAgeHours = (int) $this->option('max-age-hours');

        $transactions = PaymentTransaction::query()
            ->where('status', PaymentTransactionStatus::Pending->value)
            ->where('created_at', '<=', now()->subMinutes(self::MIN_AGE_MINUTES))
            ->orderBy('created_at')
            ->get();

        $checked = 0;
        $expired = 0;

        foreach ($transactions as $transaction) {
            // Просроченные и «безымянные» (провайдер не ответил) — отменяем.
            if ($transaction->created_at <= now()->subHours($maxAgeHours) || $transaction->external_id === null) {
                if ($transaction->external_id !== null || $transaction->created_at <= now()->subHours(1)) {
                    $apply->handle($transaction, new PaymentResult(
                        externalId: $transaction->external_id,
                        status: PaymentTransactionStatus::Canceled,
                        failureReason: 'Истёк срок оплаты',
                    ));
                    $expired++;
                }

                continue;
            }

            $provider = $payments->provider($transaction->provider);

            if (! $provider->isConfigured()) {
                continue;
            }

            $result = $provider->getPayment($transaction->external_id);

            if ($result !== null) {
                $apply->handle($transaction, $result);
                $checked++;
            }
        }

        $this->info("Проверено: {$checked}, отменено по сроку: {$expired}.");

        return self::SUCCESS;
    }
}
