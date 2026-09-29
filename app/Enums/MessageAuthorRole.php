<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * От чьего лица написано сообщение.
 *
 * System — служебная запись без автора («обращение закрыто»), показывается
 * в ленте отдельным стилем и не порождает уведомлений.
 * Bot — ответ ИИ-агента LastBot, импортированный из их диалога
 * (@see \App\Actions\LastBot\SyncLastBotThreadAction).
 */
enum MessageAuthorRole: string
{
    case Client = 'client';
    case Support = 'support';
    case System = 'system';
    case Bot = 'bot';

    /** Сообщение написано стороной поддержки. */
    public function isSupport(): bool
    {
        return $this === self::Support;
    }

    public function isSystem(): bool
    {
        return $this === self::System;
    }
}
