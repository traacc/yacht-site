<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Форма расчёта: наличные или безналичные.
 *
 * Отдельного поля в БД нет — категория выводится из способа оплаты
 * (@see PaymentMethod::settlement()). Здесь же хранится обратный маппинг
 * для фильтрации выборок по payment_method.
 */
enum PaymentSettlement: string implements HasColor, HasLabel
{
    case Cash = 'cash';
    case Cashless = 'cashless';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Наличные',
            self::Cashless => 'Безналичные',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Cash => 'warning',
            self::Cashless => 'success',
        };
    }

    /**
     * Значения payment_method, попадающие в категорию (для whereIn).
     *
     * @return list<string>
     */
    public function methodValues(): array
    {
        return array_values(array_map(
            fn (PaymentMethod $method): string => $method->value,
            array_filter(
                PaymentMethod::cases(),
                fn (PaymentMethod $method): bool => $method->settlement() === $this,
            ),
        ));
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
