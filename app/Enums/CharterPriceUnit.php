<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * За что назначена цена чартерной яхты зарубежной регаты.
 *
 * Чартер за границей считают по-разному: за неделю, за всю регату или посуточно.
 * Подпись хранится отдельно от суммы, чтобы цены разных лодок сравнивались.
 */
enum CharterPriceUnit: string
{
    case Regatta = 'regatta';
    case Week = 'week';
    case Day = 'day';

    public function label(): string
    {
        return match ($this) {
            self::Regatta => 'за регату',
            self::Week => 'за неделю',
            self::Day => 'в сутки',
        };
    }

    /** @return array<string, string> value => label, для Select. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
