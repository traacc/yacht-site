<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CreationSource: string implements HasLabel
{
    case Registration = 'registration';
    case Admin        = 'admin';
    case QuickRequest = 'quick_request';
    case Unknown      = 'unknown';

    public function label(): string
    {
        return match($this) {
            self::Registration => 'Регистрация',
            self::Admin        => 'Создан из админки',
            self::QuickRequest => 'Быстрая заявка',
            self::Unknown      => 'Неизвестно',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
