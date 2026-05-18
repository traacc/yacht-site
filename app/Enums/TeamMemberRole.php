<?php

namespace App\Enums;

enum TeamMemberRole: string
{
    case Organizer = 'organizer';
    case Admin     = 'admin';
    case Member    = 'member';

    public function label(): string
    {
        return match($this) {
            self::Organizer => 'Организатор',
            self::Admin     => 'Администратор',
            self::Member    => 'Участник',
        };
    }

    public function canManageTeam(): bool
    {
        return in_array($this, [self::Organizer, self::Admin]);
    }

    public function canSubmitEntry(): bool
    {
        return in_array($this, [self::Organizer, self::Admin]);
    }
}
