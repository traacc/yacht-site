<?php

declare(strict_types=1);

namespace App\Services\Payments\Data;

use App\Enums\PaymentTransactionStatus;
use Carbon\CarbonInterface;

/**
 * Унифицированный ответ провайдера: результат создания платежа,
 * запроса статуса или разбора вебхука.
 */
final readonly class PaymentResult
{
    /**
     * @param  array<string, mixed>  $raw  Исходный ответ провайдера (для payload).
     */
    public function __construct(
        public ?string $externalId,
        public PaymentTransactionStatus $status,
        public ?string $confirmationUrl = null,
        public ?CarbonInterface $paidAt = null,
        public ?string $failureReason = null,
        public array $raw = [],
    ) {}
}
