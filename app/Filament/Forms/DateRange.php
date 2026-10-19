<?php

namespace App\Filament\Forms;

use Carbon\Carbon;
use Closure;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Связка полей «дата начала — дата окончания» мероприятия.
 */
class DateRange
{
    /**
     * afterStateUpdated для поля начала (с ->live(onBlur: true)): окончание по
     * умолчанию равно началу — однодневное мероприятие не требует второго ввода,
     * многодневное легко поправить. Окончание, введённое вручную и не раньше
     * начала, не трогаем; подставленное от прежней даты начала — двигаем следом.
     */
    public static function syncEnd(string $endField): Closure
    {
        return static function (mixed $state, mixed $old, Get $get, Set $set) use ($endField): void {
            $start = static::toDate($state);

            if ($start === null) {
                return;
            }

            $end = static::toDate($get($endField));

            if ($end === null || $end < $start || $end === static::toDate($old)) {
                $set($endField, $start);
            }
        };
    }

    private static function toDate(mixed $value): ?string
    {
        return blank($value) ? null : Carbon::parse($value)->toDateString();
    }
}
