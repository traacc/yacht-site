<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Actions\Notifications\LinkTelegramAccountAction;
use App\Actions\Notifications\UnlinkTelegramAccountAction;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Cache;
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

    /** Одно и то же обновление Telegram повторяет при таймауте вебхука — обрабатываем только первое. */
    private const UPDATE_TTL_HOURS = 24;

    /** Подсказку на непонятное сообщение отправляем не чаще раза в N часов на чат. */
    private const HELP_COOLDOWN_HOURS = 6;

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
        if (! $this->firstDelivery($update)) {
            return;
        }

        // Правки старых сообщений не обрабатываем: deep-link со /start правят
        // не руками, а ответ на каждую правку выглядит как спам.
        $message = $update['message'] ?? null;

        if (! is_array($message)) {
            return;
        }

        // Только личный чат. В группах и каналах бот молчит: там его сообщения
        // видят все участники, включая тех, кто уведомления не подключал.
        if (data_get($message, 'chat.type') !== 'private') {
            return;
        }

        $chatId = (string) data_get($message, 'chat.id', '');
        $text = trim((string) data_get($message, 'text', ''));

        if ($chatId === '' || $text === '') {
            return;
        }

        [$command, $payload] = $this->parse($text);

        match ($command) {
            '/start' => $this->start($chatId, $message, $payload),
            '/stop' => $this->stop($chatId),
            '/help' => $this->telegram->sendToChat($chatId, self::HELP_TEXT),
            default => $this->help($chatId),
        };
    }

    /**
     * Telegram считает вебхук упавшим при медленном ответе и повторяет
     * обновление — без этой отсечки пользователь получает ответ на каждую
     * повторную доставку.
     *
     * @param  array<string, mixed>  $update
     */
    private function firstDelivery(array $update): bool
    {
        $updateId = data_get($update, 'update_id');

        if (! is_numeric($updateId)) {
            return true;
        }

        return Cache::add(
            'telegram:update:'.(int) $updateId,
            true,
            now()->addHours(self::UPDATE_TTL_HOURS),
        );
    }

    /**
     * Команда и её аргумент. В команде отсекаем упоминание бота
     * («/start@carter30bot»), которое Telegram подставляет при автодополнении.
     *
     * @return array{0: string, 1: string}
     */
    private function parse(string $text): array
    {
        $parts = preg_split('/\s+/', $text, 2) ?: [$text];

        return [Str::lower(Str::before($parts[0], '@')), trim($parts[1] ?? '')];
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function start(string $chatId, array $message, string $token): void
    {
        if ($token === '') {
            $this->help($chatId);

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

    /** Подсказка с ограничением частоты: на поток сообщений отвечаем один раз. */
    private function help(string $chatId): void
    {
        $sent = Cache::add(
            'telegram:help-sent:'.$chatId,
            true,
            now()->addHours(self::HELP_COOLDOWN_HOURS),
        );

        if (! $sent) {
            return;
        }

        $this->telegram->sendToChat($chatId, self::HELP_TEXT);
    }
}
