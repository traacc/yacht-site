<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Actions\Chat\SendChatMessageAction;
use App\Enums\ChatNotificationAudience;
use App\Enums\NotificationCategory;
use App\Filament\Pages\SupportChat as AdminSupportChat;
use App\Filament\User\Pages\Messages as UserMessages;
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
        public readonly ChatNotificationAudience $audience,
        /** Нужен только личным перепискам: их у пользователя много, ссылка должна вести в нужную. */
        public readonly ?string $conversationId = null,
        /** Заголовок диалога — у личных переписок это название объявления. */
        public readonly ?string $subject = null,
    ) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::ChatMessages;
    }

    public function title(): string
    {
        return match ($this->audience) {
            ChatNotificationAudience::Support => 'Новое обращение в поддержку',
            ChatNotificationAudience::Client => 'Новое сообщение от службы поддержки',
            ChatNotificationAudience::Direct => $this->subject !== null && $this->subject !== ''
                ? 'Новое сообщение по объявлению «'.$this->subject.'»'
                : 'Новое сообщение',
        };
    }

    public function body(): string
    {
        return $this->authorName.': '.$this->excerpt;
    }

    public function url(): ?string
    {
        // Панель указываем явно: уведомление отправляется из очереди, где текущей
        // панели нет, и getUrl() собрал бы несуществующий маршрут.
        return match ($this->audience) {
            ChatNotificationAudience::Support => AdminSupportChat::getUrl(panel: 'admin'),
            ChatNotificationAudience::Client => UserSupportChat::getUrl(panel: 'user'),
            ChatNotificationAudience::Direct => UserMessages::getUrl(
                panel: 'user',
                parameters: $this->conversationId !== null ? ['conversation' => $this->conversationId] : [],
            ),
        };
    }

    public function icon(): string
    {
        return 'heroicon-o-chat-bubble-left-right';
    }
}
