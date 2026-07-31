<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Вариант участия в зарубежной регате (ТЗ 3-го этапа, п. 7).
 *
 * Регата объявляет, какие варианты предлагает (`participation_options`), а
 * заявка выбирает один из объявленных — поэтому подписи живут в одном месте,
 * а не отдельно в форме и отдельно в админке.
 */
enum ParticipationOption: string
{
    case Seat = 'seat';
    case Cabin = 'cabin';
    case Yacht = 'yacht';

    public function label(): string
    {
        return match ($this) {
            self::Seat => 'Место в двухместной каюте',
            self::Cabin => 'Двухместная каюта',
            self::Yacht => 'Яхта целиком',
        };
    }

    /** @return array<string, string> value => label, для Select и чекбоксов. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
