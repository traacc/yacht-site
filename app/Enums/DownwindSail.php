<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Дополнительный парус полных курсов на чартерной лодке.
 *
 * Для регаты это вопрос комплектации, который задают первым после кают:
 * без спинакера или геннакера тактика на полных курсах другая.
 */
enum DownwindSail: string
{
    case None = 'none';
    case Spinnaker = 'spinnaker';
    case Gennaker = 'gennaker';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Нет',
            self::Spinnaker => 'Спинакер',
            self::Gennaker => 'Геннакер',
            self::Both => 'Спинакер и геннакер',
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
