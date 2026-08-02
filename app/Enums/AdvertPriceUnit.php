<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Единица цены в объявлении.
 *
 * ТЗ бирж: у шкиперов стоимость «руб./час или руб./день», у парусов «руб.
 * (продажа) или руб./день (аренда)». Доски без выбора (@see
 * AdvertType::priceUnits()) хранят null и печатают просто «₽».
 */
enum AdvertPriceUnit: string
{
    case Total = 'total';
    case PerHour = 'per_hour';
    case PerDay = 'per_day';

    public function label(): string
    {
        return match ($this) {
            self::Total => 'За всё, ₽',
            self::PerHour => 'В час, ₽/час',
            self::PerDay => 'В сутки, ₽/день',
        };
    }

    /** Хвост после суммы: «1 500 ₽/час». */
    public function suffix(): string
    {
        return match ($this) {
            self::Total => '',
            self::PerHour => '/час',
            self::PerDay => '/день',
        };
    }

    /** Аренда (а не продажа) — только у неё имеет смысл залог. */
    public function isRental(): bool
    {
        return $this === self::PerDay;
    }

    /** @return array<string, string> value => label, для Select и фильтров. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
