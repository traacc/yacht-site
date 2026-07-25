<?php

declare(strict_types=1);

namespace App\Services\Payments\Data;

/**
 * Запрос на создание платежа у провайдера эквайринга.
 *
 * Сумма — строка в рублях с двумя знаками («1500.00»), как хранится в БД.
 * Конвертация в копейки для провайдеров, ожидающих minor units, —
 * только через amountMinor().
 */
final readonly class CreatePaymentRequest
{
    /**
     * @param  array<string, string>  $metadata  Обязателен ключ transaction_id —
     *                                           UUID нашей PaymentTransaction.
     */
    public function __construct(
        public string $amount,
        public string $currency,
        public string $description,
        public string $returnUrl,
        public array $metadata,
        public ?ReceiptData $receipt = null,
    ) {}

    /** Сумма в копейках (minor units). */
    public function amountMinor(): int
    {
        return (int) round((float) $this->amount * 100);
    }
}
