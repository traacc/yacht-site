<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Actions\Notifications\LinkTelegramAccountAction;
use App\Actions\Notifications\UnlinkTelegramAccountAction;
use App\Services\TelegramService;
use Illuminate\Support\Str;

/**
 * Разбор входящих сообщений бота. Общий для webhook и локального long polling
 * (artisan telegram:poll), чтобы логика привязки жила в одном месте.
 */
class TelegramUpdateHandler
{
    private const HELP_TEXT = 'Этот бот присылает уведомления с сайта Carter PRO.'
        ."\n\n".'Чтобы подключить уведомления, откройте личный кабинет → «Уведомления» → «Привязать Telegram».'
        ."\n".'Отключить уведомления в Telegram — команда /stop.';

    public function __construct(
        private readonly TelegramService $telegram,
        private readonly LinkTelegramAccountAction $link,
        private readonly UnlinkTelegramAccountAction $unlink,
    ) {}

    /**
     * @param  array<string, mixed>  $update
     */
    public function handle(array $update): void
    {
        $message = $update['message'] ?? $update['edited_message'] ?? null;

        if (! is_array($message)) {
            return;
        }

        $chatId = (string) data_get($message, 'chat.id', '');
        $text = trim((string) data_get($message, 'text', ''));

        if ($chatId === '' || $text === '') {
            return;
        }

        match (true) {
            str_starts_with($text, '/start') => $this->start($chatId, $message, trim(Str::after($text, '/start'))),
            $text === '/stop' => $this->stop($chatId),
            default => $this->telegram->sendToChat($chatId, self::HELP_TEXT),
        };
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function start(string $chatId, array $message, string $token): void
    {
        if ($token === '') {
            $this->telegram->sendToChat($chatId, self::HELP_TEXT);

            return;
        }

        $user = $this->link->handle($token, [
            'chat_id' => $chatId,
            'username' => data_get($message, 'chat.username'),
            'first_name' => data_get($message, 'chat.first_name'),
        ]);

        $this->telegram->sendToChat(
            $chatId,
            $user !== null
                ? 'Готово! Уведомления будут приходить сюда. Категории настраиваются в личном кабинете, отключить — команда /stop.'
                : 'Ссылка устарела или уже использована. Откройте личный кабинет и нажмите «Привязать Telegram» ещё раз.',
        );
    }

    private function stop(string $chatId): void
    {
        $user = $this->unlink->handleByChatId($chatId);

        $this->telegram->sendToChat(
            $chatId,
            $user !== null
                ? 'Уведомления в Telegram отключены. Вернуть их можно в личном кабинете.'
                : 'Этот чат не привязан к аккаунту на сайте.',
        );
    }
}
