<?php

declare(strict_types=1);

namespace App\Enums;

enum RentalRequestStatus: string
{
    case Pending  = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending  => 'На рассмотрении',
            self::Approved => 'Одобрена',
            self::Rejected => 'Отклонена',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending  => 'gray',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }
}
