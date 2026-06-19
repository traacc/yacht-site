<?php

declare(strict_types=1);

namespace App\Enums;

enum TeamMemberInvitationStatus: string
{
    case Pending   = 'pending';
    case Approved  = 'approved';
    case Rejected  = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Pending   => 'На рассмотрении',
            self::Approved  => 'Одобрено',
            self::Rejected  => 'Отклонено',
            self::Withdrawn => 'Отозвано',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending   => 'warning',
            self::Approved  => 'success',
            self::Rejected  => 'danger',
            self::Withdrawn => 'gray',
        };
    }
}
