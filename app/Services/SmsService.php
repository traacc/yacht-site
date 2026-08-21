<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Sms\SmsSendResult;
use App\Support\PhoneNumber;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Клиент SMS-провайдера «i-digital direct» (direct.i-dgtl.ru).
 *
 * Документация: https://api.docs.direct.i-dgtl.ru/
 * Используется эндпоинт одиночных сообщений POST /message: провайдер принимает
 * массив из 1..1000 сообщений и отвечает списком messageUuid.
 *
 * Коды подтверждения телефона генерирует и хранит сайт
 * (App\Actions\Auth\SendPhoneVerificationCodeAction), а не «модуль верификации»
 * провайдера: так лимиты, срок жизни кода и число попыток остаются нашими,
 * а провайдер заменяется без миграции данных.
 */
class SmsService
{
    private const CHANNEL_SMS = 'SMS';

    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly ?string $senderName = null,
    ) {
        // Значения по умолчанию берём из config/services.php.
    }

    private function apiKey(): ?string
    {
        return $this->apiKey ?? config('services.i_dgtl.api_key');
    }

    private function senderName(): ?string
    {
        return $this->senderName ?? config('services.i_dgtl.sender_name');
    }

    /** Готов ли провайдер к отправке: есть ключ и имя отправителя. */
    public function isConfigured(): bool
    {
        return ! empty($this->apiKey()) && ! empty($this->senderName());
    }

    /**
     * Отправляет одно SMS.
     *
     * @param  string  $phone  номер в любом виде — нормализуется в 7XXXXXXXXXX
     * @param  string|null  $externalId  наш идентификатор сообщения (до 100 символов)
     */
    public function send(string $phone, string $text, ?string $externalId = null): SmsSendResult
    {
        if (! $this->isConfigured()) {
            Log::warning('SMS provider is not configured, skipping send', [
                'phone' => PhoneNumber::mask($phone),
            ]);

            return new SmsSendResult(
                ok: false,
                description: 'SMS-провайдер не настроен (нет IDGTL_API_KEY и/или IDGTL_SENDER_NAME).',
            );
        }

        $destination = PhoneNumber::normalize($phone);

        if ($destination === null) {
            return new SmsSendResult(ok: false, description: 'Некорректный номер телефона.');
        }

        $message = array_filter([
            'channelType' => self::CHANNEL_SMS,
            'senderName' => $this->senderName(),
            'destination' => $destination,
            'content' => $text,
            'ttl' => $this->ttl(),
            'externalMessageId' => $externalId,
        ], static fn (mixed $value): bool => $value !== null);

        // Лог без текста сообщения и без полного номера: в тексте — одноразовый код.
        $context = ['phone' => PhoneNumber::mask($destination), 'external_id' => $externalId];

        try {
            $response = $this->baseRequest()->post($this->endpoint('message'), [$message]);
        } catch (ConnectionException|RequestException $e) {
            Log::warning('SMS provider connection failed', [...$context, 'error' => $e->getMessage()]);

            return new SmsSendResult(
                ok: false,
                description: $e->getMessage(),
                connectionFailed: $e instanceof ConnectionException,
            );
        }

        if ($response->successful() && $response->json('errors') === false) {
            return new SmsSendResult(
                ok: true,
                status: $response->status(),
                messageUuid: $response->json('items.0.messageUuid'),
            );
        }

        $errorCode = $response->json('error.code');

        Log::warning('SMS provider responded with error', [
            ...$context,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return new SmsSendResult(
            ok: false,
            status: $response->status(),
            description: (string) $response->json('error.msg', $response->body()),
            errorCode: is_numeric($errorCode) ? (int) $errorCode : null,
        );
    }

    /** Проверка связи с API (GET /ping → PONG), авторизация не требуется. */
    public function ping(): bool
    {
        try {
            $response = $this->baseRequest()->get($this->endpoint('ping'));
        } catch (ConnectionException|RequestException $e) {
            Log::warning('SMS provider ping failed', ['error' => $e->getMessage()]);

            return false;
        }

        return $response->successful() && str_contains(mb_strtoupper($response->body()), 'PONG');
    }

    /**
     * Базовый запрос: Basic-авторизация готовой строкой из ЛК провайдера,
     * длинный таймаут по рекомендации вендора и повторы только на том,
     * что имеет шанс пройти со второй попытки (обрыв связи, 5xx).
     */
    private function baseRequest(): PendingRequest
    {
        return Http::withHeaders(['Authorization' => 'Basic '.(string) $this->apiKey()])
            ->asJson()
            ->connectTimeout(max(1, (int) config('services.i_dgtl.connect_timeout', 8)))
            ->timeout(max(1, (int) config('services.i_dgtl.timeout', 70)))
            ->retry(
                max(1, (int) config('services.i_dgtl.retry_times', 2)),
                max(0, (int) config('services.i_dgtl.retry_delay', 1000)),
                static fn (\Throwable $e): bool => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError()),
                throw: false,
            );
    }

    private function endpoint(string $method): string
    {
        return rtrim((string) config('services.i_dgtl.base_url'), '/').'/'.$method;
    }

    /** Время жизни сообщения у провайдера, допустимый диапазон 60..86400 секунд. */
    private function ttl(): int
    {
        return max(60, min(86400, (int) config('services.i_dgtl.ttl', 600)));
    }
}
