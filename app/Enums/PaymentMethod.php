<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasColor, HasLabel
{
    case Cash = 'cash';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case Online = 'online';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Наличные',
            self::Card => 'Банковская карта',
            self::BankTransfer => 'Банковский перевод',
            self::Online => 'Онлайн-оплата',
            self::Other => 'Другое',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Cash => 'warning',
            self::Card => 'info',
            self::BankTransfer => 'primary',
            self::Online => 'success',
            self::Other => 'gray',
        };
    }

    /** Платёж внесён наличными (в кассу), а не на счёт. */
    public function isCash(): bool
    {
        return $this === self::Cash;
    }

    /** Форма расчёта: наличные или безналичные. */
    public function settlement(): PaymentSettlement
    {
        return $this->isCash()
            ? PaymentSettlement::Cash
            : PaymentSettlement::Cashless;
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
