<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\PaymentProviderCode;
use App\Models\PaymentTransaction;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Обрабатывает вебхук провайдера эквайринга: верифицирует запрос,
 * находит транзакцию и применяет результат. Возвращает HTTP-код ответа.
 */
final class HandleWebhookAction
{
    public function __construct(
        private readonly PaymentManager $payments,
        private readonly ApplyPaymentResultAction $apply,
    ) {}

    public function handle(PaymentProviderCode $code, Request $request): int
    {
        $provider = $this->payments->provider($code);

        if (! $provider->isConfigured()) {
            Log::warning('Вебхук для несконфигурированного провайдера', ['provider' => $code->value]);

            return 404;
        }

        $result = $provider->parseWebhook($request);

        if ($result === null || $result->externalId === null) {
            Log::warning('Вебхук не прошёл верификацию', ['provider' => $code->value]);

            return 400;
        }

        $transaction = PaymentTransaction::query()
            ->where('provider', $code->value)
            ->where('external_id', $result->externalId)
            ->first();

        if ($transaction === null) {
            // 200 — чтобы провайдер не ретраил вебхук по незнакомому платежу вечно.
            Log::warning('Вебхук по неизвестной транзакции', [
                'provider' => $code->value,
                'external_id' => $result->externalId,
            ]);

            return 200;
        }

        $this->apply->handle($transaction, $result);

        return 200;
    }
}
