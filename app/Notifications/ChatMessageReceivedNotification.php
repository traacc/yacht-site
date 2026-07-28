<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Actions\Chat\SendChatMessageAction;
use App\Enums\NotificationCategory;
use App\Filament\Pages\SupportChat as AdminSupportChat;
use App\Filament\User\Pages\SupportChat as UserSupportChat;

/**
 * Новое сообщение в диалоге.
 *
 * Отправляется только тогда, когда у получателя не было непрочитанных в этом
 * диалоге — иначе живая переписка превратилась бы в поток писем.
 *
 * @see SendChatMessageAction
 */
final class ChatMessageReceivedNotification extends UserNotification
{
    public function __construct(
        public readonly string $authorName,
        public readonly string $excerpt,
        public readonly bool $forSupport,
    ) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::ChatMessages;
    }

    public function title(): string
    {
        return $this->forSupport
            ? 'Новое обращение в поддержку'
            : 'Новое сообщение от службы поддержки';
    }

    public function body(): string
    {
        return $this->authorName.': '.$this->excerpt;
    }

    public function url(): ?string
    {
        // Панель указываем явно: уведомление отправляется из очереди, где текущей
        // панели нет, и getUrl() собрал бы несуществующий маршрут.
        return $this->forSupport
            ? AdminSupportChat::getUrl(panel: 'admin')
            : UserSupportChat::getUrl(panel: 'user');
    }

    public function icon(): string
    {
        return 'heroicon-o-chat-bubble-left-right';
    }
}
