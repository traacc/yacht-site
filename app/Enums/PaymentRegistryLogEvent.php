<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Событие журнала изменений реестра платежей.
 */
enum PaymentRegistryLogEvent: string implements HasColor, HasLabel
{
    case Created = 'created';
    case Updated = 'updated';
    case Confirmed = 'confirmed';
    case Unconfirmed = 'unconfirmed';
    case Deleted = 'deleted';
    case Restored = 'restored';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Создание',
            self::Updated => 'Изменение',
            self::Confirmed => 'Подтверждение прихода',
            self::Unconfirmed => 'Снятие подтверждения',
            self::Deleted => 'Удаление',
            self::Restored => 'Восстановление',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Created => 'info',
            self::Updated => 'warning',
            self::Confirmed => 'success',
            self::Unconfirmed => 'danger',
            self::Deleted => 'danger',
            self::Restored => 'gray',
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
