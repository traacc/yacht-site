<?php

declare(strict_types=1);

namespace App\Services\FlashCall;

/**
 * Результат вызова API сервиса «Звонок» (zvonok.com).
 *
 * Код подтверждения придумывает провайдер: это последние цифры номера,
 * с которого поступит звонок, и приходят они в поле pincode ответа.
 * Отказ провайдера (неверный ключ, чужая кампания, пустой баланс) отличается
 * от временного сбоя связи так же, как в TelegramSendResult: в первом случае
 * повтор не поможет, во втором — имеет смысл.
 */
final readonly class FlashCallResult
{
    /**
     * @param  array<string, mixed>|null  $payload  содержимое поля data из ответа API
     * @param  bool  $connectionFailed  соединение с API не установилось (таймаут, DNS)
     */
    public function __construct(
        public bool $ok,
        public ?int $status = null,
        public ?string $description = null,
        public ?array $payload = null,
        public bool $connectionFailed = false,
    ) {}

    /** Код подтверждения — последние цифры номера, с которого поступит звонок. */
    public function pincode(): ?string
    {
        return $this->stringField('pincode');
    }

    /** Идентификатор звонка у провайдера (для разбора в ЛК zvonok.com). */
    public function callId(): ?string
    {
        return $this->stringField('call_id');
    }

    /** Остаток на счету на момент запроса. */
    public function balance(): ?string
    {
        return $this->stringField('balance');
    }

    /** Ошибка временная — есть смысл повторить позже. */
    public function shouldRetry(): bool
    {
        if ($this->ok || $this->insufficientFunds()) {
            return false;
        }

        return $this->connectionFailed
            || $this->status === 429 // лимит 20 запросов в секунду
            || ($this->status !== null && $this->status >= 500);
    }

    /** Кончились деньги на счету — повторять бессмысленно, нужен администратор. */
    public function insufficientFunds(): bool
    {
        $description = mb_strtolower((string) $this->description);

        foreach (['balance', 'баланс', 'недостаточно', 'not enough', 'no money'] as $marker) {
            if (str_contains($description, $marker)) {
                return true;
            }
        }

        return false;
    }

    /** Текст ошибки для лога и для администратора. */
    public function message(): string
    {
        return match (true) {
            $this->ok => 'Звонок принят провайдером.',
            $this->insufficientFunds() => 'Недостаточно средств на счету сервиса «Звонок».',
            $this->connectionFailed => 'Не удалось связаться с сервисом «Звонок»: '.(string) $this->description,
            default => (string) ($this->description ?? 'Неизвестная ошибка сервиса «Звонок».'),
        };
    }

    private function stringField(string $key): ?string
    {
        $value = $this->payload[$key] ?? null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
