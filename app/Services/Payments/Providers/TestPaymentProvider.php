<?php

declare(strict_types=1);

namespace App\Services\Payments\Providers;

use App\Enums\PaymentProviderCode;
use App\Enums\PaymentTransactionStatus;
use App\Models\PaymentTransaction;
use App\Services\Payments\Data\CreatePaymentRequest;
use App\Services\Payments\Data\PaymentResult;
use App\Services\Payments\PaymentGateway;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Тестовый провайдер-заглушка: полный цикл оплаты без внешних вызовов.
 * «Страница оплаты» — внутренний симулятор (route payments.test.pay),
 * колбэк которого проходит тот же путь верификации, что и настоящий вебхук.
 *
 * Защита от случайного использования в проде — двойная: нужен settings-флаг
 * payments.test_enabled, а вне local/staging — ещё и явный
 * payments.test_allow_production.
 */
class TestPaymentProvider implements PaymentGateway
{
    private const SIGNATURE_HEADER = 'X-Test-Signature';

    private const CONFIRMATION_TTL_MINUTES = 60;

    public function __construct(
        private ?SettingsService $settings = null,
    ) {}

    public function code(): PaymentProviderCode
    {
        return PaymentProviderCode::Test;
    }

    public function isConfigured(): bool
    {
        if (! (bool) $this->settings()->get('payments.test_enabled', false)) {
            return false;
        }

        return app()->environment('local', 'staging')
            || (bool) $this->settings()->get('payments.test_allow_production', false);
    }

    public function createPayment(CreatePaymentRequest $request, string $idempotenceKey): PaymentResult
    {
        $confirmationUrl = URL::temporarySignedRoute(
            'payments.test.pay',
            now()->addMinutes(self::CONFIRMATION_TTL_MINUTES),
            ['transaction' => $request->metadata['transaction_id']],
        );

        return new PaymentResult(
            externalId: 'test_'.Str::uuid()->toString(),
            status: PaymentTransactionStatus::Pending,
            confirmationUrl: $confirmationUrl,
            raw: [
                'provider' => $this->code()->value,
                'idempotence_key' => $idempotenceKey,
                'amount' => $request->amount,
                'currency' => $request->currency,
            ],
        );
    }

    public function getPayment(string $externalId): ?PaymentResult
    {
        // Внешней системы нет: источник статуса — собственная транзакция.
        $transaction = PaymentTransaction::query()
            ->where('provider', $this->code()->value)
            ->where('external_id', $externalId)
            ->first();

        if ($transaction === null) {
            return null;
        }

        return new PaymentResult(
            externalId: $externalId,
            status: $transaction->status,
            paidAt: $transaction->paid_at,
            failureReason: $transaction->failure_reason,
        );
    }

    public function parseWebhook(Request $request): ?PaymentResult
    {
        $signature = (string) $request->header(self::SIGNATURE_HEADER, '');
        $expected = static::sign($request->getContent());

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return null;
        }

        $data = $request->json()->all();
        $externalId = $data['external_id'] ?? null;
        $status = PaymentTransactionStatus::tryFrom((string) ($data['status'] ?? ''));

        if (! is_string($externalId) || $externalId === '' || $status === null) {
            return null;
        }

        return new PaymentResult(
            externalId: $externalId,
            status: $status,
            paidAt: $status === PaymentTransactionStatus::Succeeded ? now() : null,
            failureReason: $data['failure_reason'] ?? null,
            raw: $data,
        );
    }

    public function cancelPayment(string $externalId): ?PaymentResult
    {
        return new PaymentResult(
            externalId: $externalId,
            status: PaymentTransactionStatus::Canceled,
            failureReason: 'Отменено вручную',
        );
    }

    public function refundPayment(string $externalId, ?string $amount = null): ?PaymentResult
    {
        // Возвраты тестовым провайдером не поддерживаются.
        return null;
    }

    /** HMAC-подпись payload'а «вебхука» симулятора (общая с роутом confirm). */
    public static function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }

    private function settings(): SettingsService
    {
        return $this->settings ??= app(SettingsService::class);
    }
}
