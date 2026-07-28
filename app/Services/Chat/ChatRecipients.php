<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Enums\SystemRole;
use App\Filament\Pages\SupportChat;
use App\Models\Conversation;
use App\Models\User;
use App\Support\AccessControl;
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
    /**
     * Операторы поддержки: те, кому реально открыта страница чата.
     *
     * Права берём из общей матрицы, а не из захардкоженного списка ролей —
     * иначе судья, которому чат закрыли в настройках доступа, всё равно
     * получал бы письма о каждом обращении.
     *
     * @return Collection<int, User>
     */
    public function forSupport(): Collection
    {
        $panelRoles = array_filter(
            SystemRole::cases(),
            static fn (SystemRole $role): bool => $role !== SystemRole::User,
        );

        return User::query()
            ->whereIn('system_role', array_map(static fn (SystemRole $r): string => $r->value, $panelRoles))
            ->with(['notificationPreferences', 'telegramAccount'])
            ->get()
            ->filter(static fn (User $user): bool => AccessControl::allows(SupportChat::class, $user))
            ->values();
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
