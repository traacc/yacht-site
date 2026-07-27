<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\TelegramLinkToken;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Выпускает одноразовый токен привязки и собирает deep-link на бота.
 *
 * Токен создаётся по клику пользователя, а не при каждом рендере страницы,
 * иначе таблица заполнялась бы мусором.
 */
final class GenerateTelegramLinkTokenAction
{
    public function __construct(private readonly TelegramService $telegram) {}

    /** Возвращает ссылку вида https://t.me/bot?start=<токен>. */
    public function handle(User $user): string
    {
        $plain = Str::random(32);

        TelegramLinkToken::create([
            'user_id' => $user->getKey(),
            'token_hash' => TelegramLinkToken::hashToken($plain),
            'expires_at' => now()->addMinutes(TelegramLinkToken::TTL_MINUTES),
        ]);

        return 'https://t.me/'.$this->botUsername().'?start='.$plain;
    }

    /**
     * Имя бота для deep-link. Берём из конфига, а если его не задали —
     * узнаём у самого Telegram и кешируем на сутки.
     */
    private function botUsername(): string
    {
        $configured = (string) config('services.telegram.bot_username');

        if ($configured !== '') {
            return ltrim($configured, '@');
        }

        $username = Cache::remember(
            'telegram:bot_username',
            now()->addDay(),
            fn (): ?string => $this->telegram->getMe()['username'] ?? null,
        );

        if (! is_string($username) || $username === '') {
            Cache::forget('telegram:bot_username');

            throw new RuntimeException('Не удалось определить имя Telegram-бота. Задайте TELEGRAM_BOT_USERNAME.');
        }

        return $username;
    }
}
