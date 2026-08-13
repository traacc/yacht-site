<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Как подана заявка на регату: экипажем или одним человеком.
 *
 * Индивидуальные заявки возможны на регулярных и выездных регатах — там место
 * продаётся отдельно, а лодку и сборный экипаж собирает ассоциация
 * (@see App\Enums\RegattaType::allowsIndividualEntry()).
 */
enum ParticipationKind: string implements HasLabel
{
    case Crew = 'crew';
    case Individual = 'individual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Crew => 'Экипажем',
            self::Individual => 'Индивидуально',
        };
    }

    /** @return array<string, string> value => label, для Select и фильтров. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->getLabel()])
            ->all();
    }
}
