<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Жизненный цикл транзакции эквайринга (не путать с PaymentStatus —
 * статусом записи бухгалтерского реестра).
 */
enum PaymentTransactionStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Canceled = 'canceled';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Ожидает оплаты',
            self::Succeeded => 'Оплачена',
            self::Canceled => 'Отменена',
            self::Failed => 'Ошибка',
            self::Refunded => 'Возвращена',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Succeeded => 'success',
            self::Canceled => 'warning',
            self::Failed => 'danger',
            self::Refunded => 'info',
        };
    }

    /** Финальный статус больше не меняется (кроме будущего возврата средств). */
    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return $this->color();
    }
}
