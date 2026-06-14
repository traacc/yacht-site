<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum VotingStatus: string implements HasLabel, HasColor
{
    case Draft  = 'draft';
    case Active  = 'active';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft  => 'Черновик',
            self::Active => 'Активно',
            self::Closed => 'Завершено',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft  => 'gray',
            self::Active => 'success',
            self::Closed => 'danger',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->getLabel()])
            ->all();
    }
}
