<?php

declare(strict_types=1);

namespace App\Services\Telegram;

/**
 * Результат вызова Telegram Bot API.
 *
 * Отличать «бот недоступен пользователю» от временной ошибки принципиально:
 * в первом случае ретраи бессмысленны, во втором — обязательны.
 */
final readonly class TelegramSendResult
{
    /**
     * @param  array<string, mixed>|null  $payload  содержимое поля result из ответа API
     * @param  bool  $connectionFailed  соединение с API не установилось (таймаут, прокси, DNS)
     * @param  int|null  $retryAfter  сколько секунд ждать по требованию Telegram (ошибка 429)
     */
    public function __construct(
        public bool $ok,
        public ?int $status = null,
        public ?string $description = null,
        public ?array $payload = null,
        public bool $connectionFailed = false,
        public ?int $retryAfter = null,
    ) {}

    /**
     * Ошибка временная — есть смысл повторить позже: не установилось
     * соединение, сработал лимит запросов или упал сам Telegram.
     */
    public function shouldRetry(): bool
    {
        if ($this->ok || $this->botBlocked()) {
            return false;
        }

        if ($this->connectionFailed) {
            return true;
        }

        return $this->status === 429 || ($this->status !== null && $this->status >= 500);
    }

    /** Пользователь заблокировал бота, удалил аккаунт или чат недоступен. */
    public function botBlocked(): bool
    {
        if ($this->status !== 403 && $this->status !== 400) {
            return false;
        }

        $description = mb_strtolower((string) $this->description);

        foreach (['bot was blocked by the user', 'user is deactivated', 'chat not found', "bot can't initiate conversation"] as $marker) {
            if (str_contains($description, $marker)) {
                return true;
            }
        }

        return false;
    }
}
