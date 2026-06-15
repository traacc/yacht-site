<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SystemRole: string implements HasLabel
{
    case User       = 'user';
    case Admin      = 'admin';
    case Judge      = 'judge';
    case Secretary  = 'secretary';
    case Accountant = 'accountant';

    public function getLabel(): string
    {
        return match($this) {
            self::User       => 'Участник',
            self::Admin      => 'Администратор',
            self::Judge      => 'Судья',
            self::Secretary  => 'Секретарь',
            self::Accountant => 'Бухгалтер',
        };
    }

    public function canManageRegattas(): bool
    {
        return in_array($this, [self::Admin, self::Secretary]);
    }

    public function canEnterResults(): bool
    {
        return in_array($this, [self::Admin, self::Judge, self::Secretary]);
    }

    public function canPublishNews(): bool
    {
        return in_array($this, [self::Admin, self::Secretary]);
    }

    public function canApproveEntries(): bool
    {
        return in_array($this, [self::Admin, self::Secretary]);
    }
}
