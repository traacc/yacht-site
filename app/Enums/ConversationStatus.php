<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Состояние диалога. Закрытый диалог переоткрывается автоматически,
 * как только в него приходит новое сообщение.
 */
enum ConversationStatus: string implements HasLabel
{
    case Open = 'open';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Открыт',
            self::Closed => 'Закрыт',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'success',
            self::Closed => 'gray',
        };
    }
}
