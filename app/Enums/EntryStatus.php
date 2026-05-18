<?php

namespace App\Enums;

enum EntryStatus: string
{
    case Pending   = 'pending';
    case Approved  = 'approved';
    case Rejected  = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match($this) {
            self::Pending   => 'На рассмотрении',
            self::Approved  => 'Одобрена',
            self::Rejected  => 'Отклонена',
            self::Withdrawn => 'Отозвана',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Pending   => 'yellow',
            self::Approved  => 'green',
            self::Rejected  => 'red',
            self::Withdrawn => 'gray',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Approved;
    }

    public function canBeWithdrawn(): bool
    {
        return in_array($this, [self::Pending, self::Approved]);
    }
}
