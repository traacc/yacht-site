<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\FlashCall\FlashCallResult;
use App\Support\PhoneNumber;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Клиент сервиса «Звонок» (zvonok.com) — подтверждение телефона звонком.
 *
 * Документация: https://api-docs.zvonok.com/
 *
 * Flash Call: пользователю поступает звонок, отвечать на который не нужно —
 * кодом служат последние цифры номера звонящего. Код придумывает провайдер
 * и возвращает его в поле pincode ответа на POST /phones/flashcall/, поэтому
 * сайт код не генерирует, а только хранит его хеш и сверяет ввод
 * (см. App\Actions\Auth\RequestPhoneVerificationCallAction).
 *
 * Параметр phone_suffix (заказать конкретные цифры) сознательно не используется:
 * провайдер предупреждает, что выбранный номер может оказаться недоступен.
 */
class FlashCallService
{
    public function __construct(
        private readonly ?string $publicKey = null,
        private readonly ?string $campaignId = null,
    ) {
        // Значения по умолчанию берём из config/services.php.
    }

    private function publicKey(): ?string
    {
        return $this->publicKey ?? config('services.zvonok.public_key');
    }

    private function campaignId(): ?string
    {
        return $this->campaignId ?? config('services.zvonok.campaign_id');
    }

    /** Готов ли провайдер к работе: есть ключ доступа и кампания Flash Call. */
    public function isConfigured(): bool
    {
        return ! empty($this->publicKey()) && ! empty($this->campaignId());
    }

    /**
     * Ставит звонок-подтверждение на номер и возвращает код (pincode) —
     * последние цифры номера, с которого поступит звонок.
     *
     * @param  string  $phone  номер в любом виде — приводится к +7XXXXXXXXXX
     */
    public function call(string $phone): FlashCallResult
    {
        if (! $this->isConfigured()) {
            Log::warning('Flash call provider is not configured, skipping call', [
                'phone' => PhoneNumber::mask($phone),
            ]);

            return new FlashCallResult(
                ok: false,
                description: 'Подтверждение звонком не настроено (нет ZVONOK_PUBLIC_KEY и/или ZVONOK_CAMPAIGN_ID).',
            );
        }

        $destination = PhoneNumber::international($phone);

        if ($destination === null) {
            return new FlashCallResult(ok: false, description: 'Некорректный номер телефона.');
        }

        // Полный номер в логи не пишем — это персональные данные.
        $context = ['phone' => PhoneNumber::mask($destination)];

        $result = $this->execute('phones/flashcall/', $context, fn (PendingRequest $request): Response => $request
            ->asForm()
            ->post($this->endpoint('phones/flashcall/'), [
                'public_key' => $this->publicKey(),
                'campaign_id' => $this->campaignId(),
                'phone' => $destination,
            ]));

        if (! $result->ok) {
            return $result;
        }

        if ($result->pincode() === null) {
            Log::warning('Flash call response has no pincode', [...$context, 'payload' => $result->payload]);

            return new FlashCallResult(
                ok: false,
                status: $result->status,
                description: 'Провайдер не вернул код подтверждения. Проверьте, что кампания имеет тип Flash Call.',
            );
        }

        return $result;
    }

    /**
     * Остаток на счету — заодно проверяет ключ доступа.
     * null — провайдер недоступен или ключ не принят.
     */
    public function balance(): ?string
    {
        if (empty($this->publicKey())) {
            return null;
        }

        $result = $this->execute('users/balance/', [], fn (PendingRequest $request): Response => $request
            ->get($this->endpoint('users/balance/'), ['public_key' => $this->publicKey()]));

        return $result->ok ? $result->balance() : null;
    }

    /**
     * Выполняет запрос и приводит ответ к единому виду.
     *
     * У API «Звонка» свой конверт: HTTP 200 с телом
     * {"status":"ok","data":{…}} — успех, {"status":"error","data":"текст"} —
     * отказ (обычно вместе с HTTP 400).
     *
     * @param  array<string, mixed>  $context  что писать в лог при ошибке
     * @param  callable(PendingRequest): Response  $send
     */
    private function execute(string $method, array $context, callable $send): FlashCallResult
    {
        try {
            $response = $send($this->baseRequest());
        } catch (ConnectionException|RequestException $e) {
            Log::warning('Flash call provider connection failed', [
                ...$context,
                'method' => $method,
                'error' => $e->getMessage(),
            ]);

            return new FlashCallResult(
                ok: false,
                description: $e->getMessage(),
                connectionFailed: $e instanceof ConnectionException,
            );
        }

        $payload = $response->json();

        if ($response->successful() && is_array($payload) && ($payload['status'] ?? null) === 'ok') {
            return new FlashCallResult(
                ok: true,
                status: $response->status(),
                payload: is_array($payload['data'] ?? null) ? $payload['data'] : [],
            );
        }

        Log::warning('Flash call provider responded with error', [
            ...$context,
            'method' => $method,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        // При ошибке в data лежит строка с описанием.
        $error = is_array($payload) ? ($payload['data'] ?? null) : null;

        return new FlashCallResult(
            ok: false,
            status: $response->status(),
            description: is_string($error) && $error !== '' ? $error : $response->body(),
        );
    }

    /**
     * Базовый запрос: таймауты и повторы только на том, что имеет шанс
     * пройти со второй попытки (обрыв связи, 5xx, 429 при превышении rps).
     */
    private function baseRequest(): PendingRequest
    {
        return Http::acceptJson()
            ->connectTimeout(max(1, (int) config('services.zvonok.connect_timeout', 8)))
            ->timeout(max(1, (int) config('services.zvonok.timeout', 30)))
            ->retry(
                max(1, (int) config('services.zvonok.retry_times', 2)),
                max(0, (int) config('services.zvonok.retry_delay', 1000)),
                static fn (\Throwable $e): bool => $e instanceof ConnectionException
                    || ($e instanceof RequestException
                        && ($e->response->serverError() || $e->response->status() === 429)),
                throw: false,
            );
    }

    private function endpoint(string $method): string
    {
        return rtrim((string) config('services.zvonok.base_url'), '/').'/'.ltrim($method, '/');
    }
}
