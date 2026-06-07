<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentOwner: string
{
    case Yacht         = 'yacht';
    case RegattaEntry  = 'regatta_entry';

    public function label(): string
    {
        return match ($this) {
            self::Yacht        => 'Яхта',
            self::RegattaEntry => 'Заявка на регату',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Yacht        => 'blue',
            self::RegattaEntry => 'orange',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_column(
            array_map(fn (self $case) => [$case->value, $case->label()], self::cases()),
            1,
            0,
        );
    }
}
