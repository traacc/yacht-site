<?php

declare(strict_types=1);

namespace App\Services\Sms;

/**
 * Результат отправки SMS через i-digital direct.
 *
 * Отличать отказ провайдера (неверный ключ, пустой баланс, кривой номер)
 * от временного сбоя связи важно так же, как в TelegramSendResult:
 * в первом случае повтор не поможет, во втором — имеет смысл.
 */
final readonly class SmsSendResult
{
    /**
     * @param  string|null  $messageUuid  идентификатор сообщения у провайдера
     * @param  int|null  $errorCode  код ошибки провайдера (error.code, напр. 4012, 402)
     * @param  bool  $connectionFailed  соединение с API не установилось (таймаут, DNS)
     */
    public function __construct(
        public bool $ok,
        public ?int $status = null,
        public ?string $description = null,
        public ?string $messageUuid = null,
        public ?int $errorCode = null,
        public bool $connectionFailed = false,
    ) {}

    /** Ошибка временная — есть смысл повторить позже. */
    public function shouldRetry(): bool
    {
        if ($this->ok || $this->insufficientFunds()) {
            return false;
        }

        return $this->connectionFailed
            || $this->status === 429
            || ($this->status !== null && $this->status >= 500);
    }

    /** Кончились деньги на счету — повторять бессмысленно, нужен администратор. */
    public function insufficientFunds(): bool
    {
        return $this->status === 402 || $this->errorCode === 402;
    }

    /** Провайдер не принял ключ авторизации (4010/4012 — 401, 4030 — 403). */
    public function unauthorized(): bool
    {
        return $this->status === 401 || $this->status === 403;
    }

    /** Текст ошибки для лога и для администратора. */
    public function message(): string
    {
        return match (true) {
            $this->ok => 'Сообщение принято провайдером.',
            $this->insufficientFunds() => 'Недостаточно средств на счету SMS-провайдера.',
            $this->unauthorized() => 'SMS-провайдер отклонил API-ключ (проверьте IDGTL_API_KEY и тип ключа).',
            $this->connectionFailed => 'Не удалось связаться с SMS-провайдером: '.(string) $this->description,
            default => (string) ($this->description ?? 'Неизвестная ошибка SMS-провайдера.'),
        };
    }
}
