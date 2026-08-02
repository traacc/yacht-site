<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Позиция в экипаже — биржа шкиперов и матросов (ТЗ 3-го этапа, п. 8.2).
 */
enum AdvertPosition: string
{
    case Helmsman = 'helmsman';
    case Crew = 'crew';
    case Any = 'any';

    public function label(): string
    {
        return match ($this) {
            self::Helmsman => 'Рулевой',
            self::Crew => 'Матрос',
            self::Any => 'Любая',
        };
    }

    /** @return array<string, string> value => label, для Select и фильтров. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
