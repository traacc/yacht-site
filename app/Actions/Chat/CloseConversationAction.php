<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Enums\ConversationStatus;
use App\Enums\MessageAuthorRole;
use App\Models\Conversation;

/**
 * Оператор закрывает обращение.
 *
 * Системная запись остаётся в ленте, чтобы пользователю было понятно, почему
 * диалог помечен закрытым. Любое новое сообщение открывает его снова —
 * см. SendChatMessageAction.
 */
class CloseConversationAction
{
    public function __construct(
        private readonly SendChatMessageAction $sendMessage,
    ) {}

    public function handle(Conversation $conversation): void
    {
        if ($conversation->isClosed()) {
            return;
        }

        $this->sendMessage->handle(
            conversation: $conversation,
            author: null,
            role: MessageAuthorRole::System,
            body: 'Обращение закрыто. Чтобы продолжить, просто напишите новое сообщение.',
        );

        $conversation->forceFill(['status' => ConversationStatus::Closed])->save();
    }
}
