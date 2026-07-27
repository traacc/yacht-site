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
     */
    public function __construct(
        public bool $ok,
        public ?int $status = null,
        public ?string $description = null,
        public ?array $payload = null,
    ) {}

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
