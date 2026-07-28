<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Роль участника диалога.
 *
 * Client — обратившийся в поддержку. Member — сторона переписки между
 * пользователями. Операторы поддержки участниками не заводятся: их прочтение
 * общее и хранится в conversations.support_read_at.
 */
enum ConversationRole: string
{
    case Client = 'client';
    case Member = 'member';
}
