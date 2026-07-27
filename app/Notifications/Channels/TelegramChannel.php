<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Actions\Notifications\MarkTelegramBlockedAction;
use App\Models\User;
use App\Services\Telegram\TelegramMessage;
use App\Services\TelegramService;
use Illuminate\Notifications\Notification;
use RuntimeException;

/**
 * Канал доставки уведомлений в личный чат Telegram.
 *
 * Подключается как класс из NotificationChannel::driver() — отдельный пакет
 * не нужен, Laravel сам резолвит драйвер-класс из via().
 */
final class TelegramChannel
{
    public function __construct(
        private readonly TelegramService $telegram,
        private readonly MarkTelegramBlockedAction $markBlocked,
    ) {}

    public function send(User $notifiable, Notification $notification): void
    {
        $account = $notifiable->telegramAccount;

        if ($account === null || $account->isBlocked() || ! $this->telegram->hasToken()) {
            return;
        }

        if (! method_exists($notification, 'toTelegram')) {
            return;
        }

        /** @var TelegramMessage $message */
        $message = $notification->toTelegram($notifiable);

        $result = $this->telegram->sendToChat(
            $account->chat_id,
            $message->text,
            $message->inlineKeyboard(),
        );

        if ($result->botBlocked()) {
            // Ретраи бессмысленны: пока пользователь не разблокирует бота, не дойдёт.
            $this->markBlocked->handle($account, $result->description);

            return;
        }

        if (! $result->ok) {
            // Временная ошибка — пусть очередь повторит по расписанию $backoff.
            throw new RuntimeException(
                "Не удалось отправить сообщение в Telegram пользователю {$notifiable->getKey()}: {$result->description}"
            );
        }
    }
}
