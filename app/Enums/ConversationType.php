<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Тип диалога.
 *
 * Support — обращение в службу поддержки: одна сторона пользователь, вторая —
 * любой оператор с доступом к странице чата.
 * Direct — переписка двух пользователей (биржа шкиперов и матросов, биржа
 * парусов, барахолка): обе стороны обычные участники, поддержка не участвует.
 */
enum ConversationType: string implements HasLabel
{
    case Support = 'support';
    case Direct = 'direct';

    public function getLabel(): string
    {
        return match ($this) {
            self::Support => 'Поддержка',
            self::Direct => 'Личная переписка',
        };
    }
}
