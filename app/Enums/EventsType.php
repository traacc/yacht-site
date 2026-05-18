<?php

namespace App\Enums;

enum EventsType: string
{
    case Schedule = 'schedule';
    case Race     = 'race';

    public function label(): string
    {
        return match($this) {
            self::Schedule => 'Расписания',
            self::Race     => 'Гонка',
        };
    }

    public function isRace(): bool
    {
        return in_array($this, [self::Race]);
    }
}
