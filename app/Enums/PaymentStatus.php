<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasColor, HasLabel
{
    case Pending  = 'pending';
    case Paid     = 'paid';
    case Partial  = 'partial';
    case Overdue  = 'overdue';
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::Pending  => 'Ожидает оплаты',
            self::Paid     => 'Оплачено',
            self::Partial  => 'Частично оплачено',
            self::Overdue  => 'Просрочено',
            self::Canceled => 'Отменено',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending  => 'gray',
            self::Paid     => 'success',
            self::Partial  => 'warning',
            self::Overdue  => 'danger',
            self::Canceled => 'gray',
        };
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
