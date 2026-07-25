<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\PaymentProviderCode;
use App\Services\Payments\Data\CreatePaymentRequest;
use App\Services\Payments\Data\PaymentResult;
use Illuminate\Http\Request;

/**
 * Контракт провайдера эквайринга. Каждый банк/агрегатор — свой адаптер
 * в app/Services/Payments/Providers, резолвится через PaymentManager.
 */
interface PaymentGateway
{
    public function code(): PaymentProviderCode;

    /** Готов ли провайдер к работе (креденшелы заданы, окружение допустимо). */
    public function isConfigured(): bool;

    /**
     * Создать платёж у провайдера. Ключ идемпотентности защищает
     * от дублей при повторе запроса.
     */
    public function createPayment(CreatePaymentRequest $request, string $idempotenceKey): PaymentResult;

    /** Актуальный статус платежа у провайдера; null — недоступен/не найден. */
    public function getPayment(string $externalId): ?PaymentResult;

    /**
     * Разобрать и верифицировать вебхук (подпись/IP).
     * null — запрос не прошёл верификацию.
     */
    public function parseWebhook(Request $request): ?PaymentResult;

    /** Отменить неоплаченный платёж; null — операция недоступна. */
    public function cancelPayment(string $externalId): ?PaymentResult;

    /**
     * Возврат средств (задел, пока нигде не вызывается).
     * null — операция недоступна у провайдера.
     */
    public function refundPayment(string $externalId, ?string $amount = null): ?PaymentResult;
}
