<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Занятость лодки в таблице флота на витрине: «свободна», «есть места»,
 * «занята».
 *
 * В отличие от CharterYachtStatus это не поле, а вывод из статуса чартера
 * целиком и счётчиков мест (@see App\Models\ForeignRegattaYacht::availability()):
 * лодку целиком могли забрать, а в экипаж ещё набирают.
 */
enum FleetYachtAvailability: string
{
    case Free = 'free';
    case SeatsLeft = 'seats_left';
    case Taken = 'taken';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Свободна',
            self::SeatsLeft => 'Есть места',
            self::Taken => 'Занята',
        };
    }

    /** Открывается ли по клику карточка лодки: занятой предложить нечего. */
    public function opensDetails(): bool
    {
        return $this !== self::Taken;
    }
}
