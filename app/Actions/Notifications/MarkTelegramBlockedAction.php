<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\TelegramAccount;
use Illuminate\Support\Facades\Log;

/**
 * Отмечает, что бот больше не может писать пользователю.
 *
 * Настройки уведомлений при этом НЕ трогаем: пользователь не отписывался,
 * он просто недоступен. Привязка восстановится сама при повторном /start.
 */
final class MarkTelegramBlockedAction
{
    public function handle(TelegramAccount $account, ?string $reason = null): void
    {
        if ($account->isBlocked()) {
            return;
        }

        $account->forceFill(['blocked_at' => now()])->save();

        Log::info('Telegram-бот заблокирован пользователем', [
            'user_id' => $account->user_id,
            'chat_id' => $account->chat_id,
            'reason' => $reason,
        ]);
    }
}
