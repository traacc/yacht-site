<?php

declare(strict_types=1);

namespace App\Services\Payments\Data;

/**
 * Данные фискального чека (54-ФЗ). Задел на будущее: структура передаётся
 * в адаптер провайдера, но формирование чеков пока не реализовано —
 * онлайн-касса не выбрана.
 */
final readonly class ReceiptData
{
    /**
     * @param  list<array{description: string, amount: string, quantity: int, vat_code?: int}>  $items
     */
    public function __construct(
        public ?string $customerEmail = null,
        public ?string $customerPhone = null,
        public array $items = [],
    ) {}
}
