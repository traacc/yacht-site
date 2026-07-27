<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\TelegramAccount;
use App\Models\User;

/**
 * Отвязывает Telegram: кнопкой в личном кабинете или командой /stop боту.
 * Настройки уведомлений не меняем — канал просто перестаёт быть доставимым.
 */
final class UnlinkTelegramAccountAction
{
    public function handle(User $user): void
    {
        $user->telegramAccount?->delete();
        $user->unsetRelation('telegramAccount');
    }

    /** Отвязка по чату — приходит из команды /stop, пользователя ищем по chat_id. */
    public function handleByChatId(string $chatId): ?User
    {
        $account = TelegramAccount::query()->where('chat_id', $chatId)->first();

        if ($account === null) {
            return null;
        }

        $user = $account->user;
        $account->delete();
        $user?->unsetRelation('telegramAccount');

        return $user;
    }
}
