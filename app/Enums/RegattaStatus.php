<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RegattaStatus: string implements HasLabel, HasColor
{
    case Upcoming  = 'upcoming';
    case Closest  = 'closest';
    case Active    = 'active';
    case Finished  = 'finished';
    case Cancelled = 'cancelled';
    case Postponed = 'postponed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Upcoming  => 'Планируемая',
            self::Closest   => 'Ближайшая',
            self::Active    => 'Идёт',
            self::Finished  => 'Завершена',
            self::Cancelled => 'Отменена',
            self::Postponed => 'Перенесена',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Upcoming  => 'primary',
            self::Closest   => 'danger',
            self::Active    => 'success',
            self::Finished  => 'gray',
            self::Cancelled => 'danger',
            self::Postponed => 'warning',
        };
    }
}
