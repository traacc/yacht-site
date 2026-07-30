<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Filament\Pages\SupportChat;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Notifications\AdminRecipients;
use Illuminate\Database\Eloquent\Collection;

/**
 * Кому уходит уведомление о новом сообщении в диалоге.
 *
 * Получатели всегда загружаются вместе с настройками уведомлений и привязкой
 * Telegram: UserNotification::via() дёргает их на каждом адресате, без
 * предзагрузки это N+1.
 */
class ChatRecipients
{
    public function __construct(
        private readonly AdminRecipients $admins,
    ) {}

    /**
     * Операторы поддержки: те, кому реально открыта страница чата.
     *
     * @return Collection<int, User>
     */
    public function forSupport(): Collection
    {
        $recipients = $this->admins->forSection(SupportChat::class);

        // UserNotification::via() дёргает настройки и привязку Telegram на каждом
        // адресате — без предзагрузки это N+1.
        $recipients->loadMissing(['notificationPreferences', 'telegramAccount']);

        return $recipients;
    }

    /**
     * Участники диалога, кроме автора сообщения.
     *
     * @return Collection<int, User>
     */
    public function participants(Conversation $conversation, ?User $except = null): Collection
    {
        return User::query()
            ->whereIn('id', $conversation->otherParticipants($except)->pluck('user_id'))
            ->with(['notificationPreferences', 'telegramAccount'])
            ->get();
    }
}
