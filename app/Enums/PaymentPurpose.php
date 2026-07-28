<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\RegattaEntry;
use App\Models\Team;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Model;

/**
 * Назначение платежа — справочник видов приходов из ТЗ.
 * Поле nullable: у платежей, созданных до внедрения справочника,
 * назначение не указано, и они попадают в отдельную группу.
 */
enum PaymentPurpose: string implements HasColor, HasLabel
{
    case MembershipFee = 'membership_fee';
    case EntryFee = 'entry_fee';
    case Dinner = 'dinner';
    case Sponsorship = 'sponsorship';
    case YachtRental = 'yacht_rental';
    case Event = 'event';
    case Advertising = 'advertising';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::MembershipFee => 'Членский взнос',
            self::EntryFee => 'Стартовый взнос',
            self::Dinner => 'Участие в ужине',
            self::Sponsorship => 'Спонсорский взнос',
            self::YachtRental => 'Аренда яхты',
            self::Event => 'Проведение мероприятия',
            self::Advertising => 'Размещение рекламы',
            self::Other => 'Иное',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::MembershipFee => 'primary',
            self::EntryFee => 'success',
            self::Dinner => 'warning',
            self::Sponsorship => 'info',
            self::YachtRental => 'info',
            self::Event => 'warning',
            self::Advertising => 'gray',
            self::Other => 'gray',
        };
    }

    /** Назначение по умолчанию для источника платежа. */
    public static function defaultForPayable(?Model $payable): ?self
    {
        return match (true) {
            $payable instanceof RegattaEntry => self::EntryFee,
            $payable instanceof Team => self::MembershipFee,
            default => null,
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return $this->color();
    }
}
