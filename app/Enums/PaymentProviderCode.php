<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Код провайдера эквайринга. Новые провайдеры (ЮKassa, Т-Банк и т.д.)
 * добавляются кейсом сюда + адаптером в app/Services/Payments/Providers.
 */
enum PaymentProviderCode: string implements HasLabel
{
    case Test = 'test';

    public function label(): string
    {
        return match ($this) {
            self::Test => 'Тестовый провайдер',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
