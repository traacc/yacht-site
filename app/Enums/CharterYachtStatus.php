<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Занятость чартерной яхты зарубежной регаты (ТЗ 3-го этапа, п. 7).
 *
 * Ведётся вручную из админки: регата идёт на фиксированные даты, поэтому
 * календарь занятости здесь избыточен — лодка либо взята под эту регату,
 * либо нет. Так же вручную ведутся места в походах (`Tour::seats_left`).
 */
enum CharterYachtStatus: string
{
    case Free = 'free';
    case Reserved = 'reserved';
    case Booked = 'booked';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Свободна',
            self::Reserved => 'Бронь',
            self::Booked => 'Занята',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Free => 'success',
            self::Reserved => 'warning',
            self::Booked => 'gray',
        };
    }

    /** Можно ли выбрать яхту в заявке на участие. */
    public function isAvailable(): bool
    {
        return $this === self::Free;
    }

    /** @return array<string, string> value => label, для Select и фильтров. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
