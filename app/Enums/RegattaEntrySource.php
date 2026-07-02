<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RegattaEntrySource: string implements HasLabel
{
    case PersonalCabinet = 'personal_cabinet';
    case QuickRequest    = 'quick_request';
    case Admin           = 'admin';
    case Unknown         = 'unknown';

    public function label(): string
    {
        return match($this) {
            self::PersonalCabinet => 'Из личного кабинета',
            self::QuickRequest    => 'Быстрая заявка',
            self::Admin           => 'Создана администратором',
            self::Unknown         => 'Неизвестно',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
