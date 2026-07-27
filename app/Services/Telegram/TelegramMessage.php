<?php

declare(strict_types=1);

namespace App\Services\Telegram;

/**
 * Сообщение для личного чата в Telegram. Текст — в разметке HTML
 * (parse_mode=HTML), кнопка при наличии уходит как inline_keyboard.
 */
final readonly class TelegramMessage
{
    public function __construct(
        public string $text,
        public ?string $buttonUrl = null,
        public ?string $buttonText = null,
    ) {}

    /** @return array<string, mixed>|null */
    public function inlineKeyboard(): ?array
    {
        if ($this->buttonUrl === null) {
            return null;
        }

        return [
            'inline_keyboard' => [
                [['text' => $this->buttonText ?? 'Открыть', 'url' => $this->buttonUrl]],
            ],
        ];
    }
}
