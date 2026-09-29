<?php

declare(strict_types=1);

namespace App\Services\LastBot;

use App\Support\DisplayTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Клиент LastBot ONE (lastbot.com) — серверная сторона их веб-виджета.
 *
 * ★ Официального серверного API у LastBot нет. Клиент повторяет то, что делает
 * их виджет assets.lastbot.com/lastbot-chat.js (api version 3), и может
 * сломаться при любом обновлении виджета:
 *
 *   POST {base}/api/v1/widget_tokens                         → {token} (JWT, выдаётся только
 *        с Origin домена, разрешённого в настройках виджета)
 *   GET  {base}/api/v1/widgets/{widget}?uuid=&email=          → настройки виджета (contact —
 *        null, пока у посетителя нет ни одного диалога)
 *   POST {base}/api/v1/widgets/{widget}/message_threads       → новый диалог с первым сообщением;
 *        с пустым uuid LastBot заводит контакт и возвращает его в contact.uuid
 *   GET  {base}/api/v1/widgets/{widget}/message_threads/{id}  → диалог с сообщениями
 *   WS   {base}/cable?token=…  Chat::MessageThreadChannel     → perform create_message — реплика
 *        в существующий диалог (по REST продолжить диалог нельзя)
 *
 * Сообщение диалога: {id, role: user|assistant|event, contents, is_finished,
 * sender (null — ИИ, объект — живой оператор), metadata, attachment, updated_at}.
 * Диалог: {id, contact: {uuid}, messages, task: {closed_at}, assigned_user}.
 * К диалогу клиент добавляет SERVER_TIME — время LastBot из заголовка Date.
 */
class LastBotClient
{
    /** Их виджет берёт язык из <html lang>; у сайта он один. */
    private const LANG = 'ru';

    /** Ключ, под которым thread() кладёт время сервера LastBot (ISO 8601). */
    public const SERVER_TIME = '_server_time';

    public function isEnabled(): bool
    {
        return (bool) config('services.lastbot.enabled')
            && $this->baseUrl() !== ''
            && $this->widgetId() !== '';
    }

    /**
     * Настройки виджета — заодно проверка, что widget_id и токен приняты.
     *
     * @return array<string, mixed>
     */
    public function widget(): array
    {
        return $this->call('GET', 'widgets/'.$this->widgetId(), query: [
            'uuid' => '',
            'email' => '',
            'lang' => self::LANG,
        ]);
    }

    /**
     * Новый диалог с первым сообщением. Без uuid LastBot заводит новый контакт.
     *
     * @return array<string, mixed> в т.ч. id и contact.uuid
     */
    public function createThread(?string $contactUuid, string $contents, string $sessionId, ?string $email = null): array
    {
        $thread = $this->call('POST', 'widgets/'.$this->widgetId().'/message_threads', query: [
            'lang' => self::LANG,
        ], body: [
            'attachmentId' => null,
            'contents' => $contents,
            'session_id' => $sessionId,
            'uuid' => $contactUuid ?? '',
            'email' => $email ?? '',
            'homeUrl' => $this->origin(),
            'current_url' => $this->origin(),
            'visitor_time_zone' => DisplayTime::timezone(),
        ]);

        if (! isset($thread['id']) || empty($thread['contact']['uuid'])) {
            throw new LastBotException('LastBot не вернул id диалога или uuid контакта.');
        }

        return $thread;
    }

    /**
     * Диалог со всеми сообщениями.
     *
     * @return array<string, mixed>
     */
    public function thread(string $threadId, string $contactUuid, string $sessionId): array
    {
        return $this->call('GET', 'widgets/'.$this->widgetId().'/message_threads/'.rawurlencode($threadId), query: [
            'session_id' => $sessionId,
            'uuid' => $contactUuid,
        ], withServerTime: true);
    }

    /**
     * Реплика в существующий диалог — только через ActionCable.
     *
     * Возвращает, увидели ли мы эхо своего сообщения (message_created с role=user).
     * false не значит «не доставлено»: эхо могло просто не успеть прийти, поэтому
     * повторять отправку по false нельзя — получится дубль.
     */
    public function sendToThread(string $threadId, string $contactUuid, string $contents): bool
    {
        $cable = new ActionCableClient($this->timeout());

        try {
            $cable->connect($this->cableUrl($this->token()), $this->origin());

            $identifier = $cable->subscribe([
                'channel' => 'Chat::MessageThreadChannel',
                'client_type' => 'widget',
                'uuid' => $contactUuid,
                // Виджет передаёт id так, как его вернул REST (число).
                'id' => ctype_digit($threadId) ? (int) $threadId : $threadId,
            ]);

            $cable->perform($identifier, 'create_message', [
                'contents' => $contents,
                'attachmentId' => null,
                'current_url' => $this->origin(),
            ]);

            $echo = $cable->receive(
                $identifier,
                static fn (array $message): bool => ($message['action'] ?? null) === 'message_created'
                    && ($message['body']['role'] ?? null) === 'user',
                $this->timeout(),
            );

            if ($echo !== null && ($echo['ok'] ?? true) === false) {
                throw new LastBotException('LastBot отклонил сообщение (status '.($echo['status'] ?? '?').').');
            }

            return $echo !== null;
        } finally {
            $cable->close();
        }
    }

    /**
     * Токен виджета. Это JWT с полем exp — кэшируем до истечения с запасом,
     * чтобы не запрашивать его на каждое сообщение.
     */
    public function token(bool $fresh = false): string
    {
        $key = 'lastbot:widget-token:'.md5($this->baseUrl().'|'.$this->widgetId().'|'.$this->origin());

        if (! $fresh && is_string($cached = Cache::get($key))) {
            return $cached;
        }

        try {
            $response = $this->http()->post('widget_tokens');
        } catch (ConnectionException $e) {
            throw new LastBotException('LastBot недоступен: '.$e->getMessage(), previous: $e);
        }

        $token = $response->json('token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new LastBotException('LastBot не выдал токен виджета: '.$this->describe($response)
                .' Проверьте, что LASTBOT_ORIGIN добавлен в разрешённые домены виджета.');
        }

        Cache::put($key, $token, now()->addSeconds($this->tokenTtl($token)));

        return $token;
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    private function call(string $method, string $path, array $query = [], ?array $body = null, bool $withServerTime = false): array
    {
        $response = $this->send($method, $path, $query, $body, $this->token());

        // Токен мог истечь раньше, чем мы думали, — одна попытка со свежим.
        if ($response->status() === 401) {
            $response = $this->send($method, $path, $query, $body, $this->token(fresh: true));
        }

        if (! $response->successful()) {
            // Код ответа — в code исключения: по 404 пересылка открывает новый диалог.
            throw new LastBotException("LastBot: {$method} {$path} — ".$this->describe($response), $response->status());
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new LastBotException("LastBot: {$method} {$path} вернул не JSON.");
        }

        // «Сколько секунд назад менялся ответ» считаем по часам LastBot, а не по
        // своим: часы сервера сайта могут заметно расходиться с их часами.
        if ($withServerTime) {
            $json[self::SERVER_TIME] = $this->serverTime($response)->toIso8601String();
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    private function send(string $method, string $path, array $query, ?array $body, string $token): Response
    {
        $url = $path.($query === [] ? '' : '?'.http_build_query($query));

        try {
            return $this->http()
                ->withToken($token)
                ->send($method, $url, $body === null ? [] : ['json' => $body]);
        } catch (ConnectionException $e) {
            throw new LastBotException('LastBot недоступен: '.$e->getMessage(), previous: $e);
        }
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl().'/api/v1/')
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'Origin' => $this->origin(),
                'Referer' => $this->origin().'/',
            ])
            ->timeout($this->timeout())
            ->connectTimeout(min(10, $this->timeout()));
    }

    /** Время жизни токена по полю exp (минус 5 минут), иначе — час. */
    private function tokenTtl(string $token): int
    {
        $parts = explode('.', $token);
        $payload = isset($parts[1])
            ? json_decode((string) base64_decode(strtr($parts[1], '-_', '+/'), true), true)
            : null;

        $exp = is_array($payload) && is_int($payload['exp'] ?? null) ? $payload['exp'] : null;

        if ($exp === null) {
            return 3600;
        }

        return max(60, $exp - time() - 300);
    }

    private function cableUrl(string $token): string
    {
        return preg_replace('~^http~', 'ws', $this->baseUrl()).'/cable?token='.rawurlencode($token);
    }

    private function serverTime(Response $response): CarbonImmutable
    {
        $date = $response->header('Date');

        try {
            return $date !== '' ? CarbonImmutable::parse($date) : CarbonImmutable::now();
        } catch (\Throwable) {
            return CarbonImmutable::now();
        }
    }

    private function describe(Response $response): string
    {
        return 'HTTP '.$response->status().' '.mb_substr(trim($response->body()), 0, 300);
    }

    private function baseUrl(): string
    {
        return (string) config('services.lastbot.base_url');
    }

    private function widgetId(): string
    {
        return (string) config('services.lastbot.widget_id');
    }

    private function origin(): string
    {
        return (string) config('services.lastbot.origin');
    }

    private function timeout(): int
    {
        return max(5, (int) config('services.lastbot.timeout', 20));
    }
}
