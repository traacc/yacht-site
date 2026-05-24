<?php

namespace App\Enums;

enum TeamMemberRole: string
{
    case Organizer = 'organizer';
    case TeamAdmin     = 'team_admin';
    case Member    = 'member';

    public function label(): string
    {
        return match($this) {
            self::Organizer => 'Организатор',
            self::TeamAdmin     => 'Администратор',
            self::Member    => 'Участник',
        };
    }

    public function canManageTeam(): bool
    {
        return in_array($this, [self::Organizer, self::TeamAdmin]);
    }

    public function canSubmitEntry(): bool
    {
        return in_array($this, [self::Organizer, self::TeamAdmin]);
    }
}
