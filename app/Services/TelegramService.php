<?php

namespace App\Services;

use App\Models\News;
use App\Services\Telegram\TelegramSendResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TelegramService
{
    // Через прокси и при загрузке картинки соединение бывает медленным.
    private const TIMEOUT_SECONDS = 25;

    private const CONNECT_TIMEOUT_SECONDS = 8;

    // Лимиты Telegram Bot API на длину текста.
    private const CAPTION_LIMIT = 1024;

    private const MESSAGE_LIMIT = 4096;

    public function __construct(
        private readonly ?string $botToken = null,
        private readonly ?string $chatId = null,
        private readonly ?string $proxy = null,
    ) {
        // Значения по умолчанию берём из config/services.php.
    }

    private function token(): ?string
    {
        return $this->botToken ?? config('services.telegram.bot_token');
    }

    private function chat(): ?string
    {
        return $this->chatId ?? config('services.telegram.chat_id');
    }

    private function proxy(): ?string
    {
        return $this->proxy ?? config('services.telegram.proxy');
    }

    /** Публикация в канал: нужны и токен, и общий chat_id канала. */
    public function isConfigured(): bool
    {
        return ! empty($this->token()) && ! empty($this->chat());
    }

    /**
     * Личные сообщения: достаточно токена, общий chat_id канала для них не нужен.
     */
    public function hasToken(): bool
    {
        return ! empty($this->token());
    }

    /**
     * Публикует новость в Telegram-канал/группу.
     * Если есть обложка — отправляет фото с подписью, иначе текстовое сообщение.
     *
     * Возвращает результат целиком, а не bool: вызывающей стороне нужно
     * отличать временный сбой связи (повторяем) от отказа API (не повторяем).
     */
    public function publishNews(News $news): TelegramSendResult
    {
        if (! $this->isConfigured()) {
            Log::warning('Telegram is not configured, skipping news publication', [
                'news_id' => $news->id,
            ]);

            return new TelegramSendResult(ok: false, description: 'Telegram не настроен (нет токена и/или chat_id).');
        }

        $coverPath = $this->coverPath($news);
        $context = ['news_id' => $news->id];

        return $coverPath !== null
            ? $this->sendPhoto((string) $this->chat(), $coverPath, $this->caption($news, self::CAPTION_LIMIT), $context)
            : $this->sendMessage((string) $this->chat(), $this->caption($news, self::MESSAGE_LIMIT), $context);
    }

    /**
     * Отправляет сообщение в произвольный чат (личный чат пользователя с ботом).
     *
     * @param  array<string, mixed>|null  $inlineKeyboard  reply_markup, если нужна кнопка
     */
    public function sendToChat(string $chatId, string $text, ?array $inlineKeyboard = null): TelegramSendResult
    {
        if (! $this->hasToken()) {
            return new TelegramSendResult(ok: false, description: 'Токен Telegram-бота не настроен.');
        }

        return $this->sendMessage($chatId, $text, ['chat_id' => $chatId], $inlineKeyboard);
    }

    /**
     * Отправляет обложку как загружаемый файл (multipart), а не ссылкой:
     * это не зависит от публичной доступности сайта и идёт через наш прокси.
     *
     * @param  array<string, mixed>  $context
     */
    private function sendPhoto(string $chatId, string $coverPath, string $caption, array $context): TelegramSendResult
    {
        $contents = Storage::disk('public')->get($coverPath);

        return $this->execute('sendPhoto', $context, fn (PendingRequest $request): Response => $request
            ->attach('photo', $contents, basename($coverPath))
            ->post($this->endpoint('sendPhoto'), [
                'chat_id' => $chatId,
                'caption' => $caption,
                'parse_mode' => 'HTML',
            ]));
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>|null  $inlineKeyboard
     */
    private function sendMessage(string $chatId, string $text, array $context, ?array $inlineKeyboard = null): TelegramSendResult
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => false,
        ];

        if ($inlineKeyboard !== null) {
            $payload['reply_markup'] = $inlineKeyboard;
        }

        return $this->execute('sendMessage', $context, fn (PendingRequest $request): Response => $request
            ->asJson()
            ->post($this->endpoint('sendMessage'), $payload));
    }

    /**
     * Базовый запрос с таймаутами, ретраями и прокси (если задан).
     *
     * Повторяем только то, что имеет шанс пройти со второй попытки:
     * обрыв соединения и 5xx/429 на стороне Telegram. Ошибки вида
     * «chat not found» повторять бессмысленно.
     */
    private function baseRequest(?int $timeoutSeconds = null): PendingRequest
    {
        $request = Http::connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
            ->timeout($timeoutSeconds ?? self::TIMEOUT_SECONDS)
            ->retry(
                max(1, (int) config('services.telegram.retry_times', 3)),
                max(0, (int) config('services.telegram.retry_delay', 2000)),
                static fn (\Throwable $e): bool => $e instanceof ConnectionException
                    || ($e instanceof RequestException && ($e->response->serverError() || $e->response->status() === 429)),
                throw: false,
            );

        if (! empty($this->proxy())) {
            $request->withOptions(['proxy' => $this->proxy()]);
        }

        return $request;
    }

    private function endpoint(string $method): string
    {
        return "https://api.telegram.org/bot{$this->token()}/{$method}";
    }

    /**
     * Выполняет запрос к API и обрабатывает ответ/ошибки единообразно.
     *
     * @param  array<string, mixed>  $context  что писать в лог при ошибке
     * @param  callable(PendingRequest): Response  $send
     */
    private function execute(string $method, array $context, callable $send, ?int $timeoutSeconds = null): TelegramSendResult
    {
        try {
            $response = $send($this->baseRequest($timeoutSeconds));

            if ($response->successful() && $response->json('ok') === true) {
                $payload = $response->json('result');

                return new TelegramSendResult(
                    ok: true,
                    status: $response->status(),
                    payload: is_array($payload) ? $payload : null,
                );
            }

            Log::warning('Telegram API responded with error', [
                ...$context,
                'method' => $method,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $retryAfter = $response->json('parameters.retry_after');

            return new TelegramSendResult(
                ok: false,
                status: $response->status(),
                description: (string) $response->json('description', $response->body()),
                retryAfter: is_numeric($retryAfter) ? (int) $retryAfter : null,
            );
        } catch (ConnectionException|RequestException $e) {
            Log::warning('Telegram API connection failed', [
                ...$context,
                'method' => $method,
                'error' => $e->getMessage(),
            ]);

            // Соединение так и не установилось после ретраев внутри запроса —
            // помечаем ошибку как временную, чтобы очередь повторила позже.
            return new TelegramSendResult(
                ok: false,
                description: $e->getMessage(),
                connectionFailed: $e instanceof ConnectionException,
            );
        }
    }

    // ──────────────────────────────────────────────
    // Управление ботом: webhook и получение обновлений
    // ──────────────────────────────────────────────

    /** Устанавливает webhook; secret приходит обратно в заголовке X-Telegram-Bot-Api-Secret-Token. */
    public function setWebhook(string $url, string $secret, bool $dropPendingUpdates = false): TelegramSendResult
    {
        return $this->execute('setWebhook', ['url' => $url], fn (PendingRequest $request): Response => $request
            ->asJson()
            ->post($this->endpoint('setWebhook'), [
                'url' => $url,
                'secret_token' => $secret,
                // Нужны только сообщения: /start с токеном привязки и /stop.
                'allowed_updates' => ['message'],
                'drop_pending_updates' => $dropPendingUpdates,
            ]));
    }

    public function deleteWebhook(): TelegramSendResult
    {
        return $this->execute('deleteWebhook', [], fn (PendingRequest $request): Response => $request
            ->asJson()
            ->post($this->endpoint('deleteWebhook')));
    }

    public function getWebhookInfo(): TelegramSendResult
    {
        return $this->execute('getWebhookInfo', [], fn (PendingRequest $request): Response => $request
            ->get($this->endpoint('getWebhookInfo')));
    }

    /** @return array<string, mixed>|null */
    public function getMe(): ?array
    {
        if (! $this->hasToken()) {
            return null;
        }

        return $this->execute('getMe', [], fn (PendingRequest $request): Response => $request
            ->get($this->endpoint('getMe')))->payload;
    }

    /**
     * Long polling — запасной вариант для локальной разработки, когда Telegram
     * не может достучаться до сайта по webhook.
     *
     * @return list<array<string, mixed>>
     */
    public function getUpdates(?int $offset = null, int $timeout = 30): array
    {
        $result = $this->execute(
            'getUpdates',
            [],
            fn (PendingRequest $request): Response => $request->get($this->endpoint('getUpdates'), array_filter([
                'offset' => $offset,
                'timeout' => $timeout,
                'allowed_updates' => ['message'],
            ], static fn (mixed $value): bool => $value !== null)),
            // Соединение должно пережить длинный опрос.
            timeoutSeconds: $timeout + 10,
        );

        if (! $result->ok) {
            return [];
        }

        /** @var list<array<string, mixed>> $updates */
        $updates = $result->payload ?? [];

        return $updates;
    }

    /**
     * Формирует подпись: заголовок, краткий текст и ссылка на полную новость.
     *
     * Telegram считает длину по видимому тексту (после разбора HTML), в
     * UTF-16. Поэтому бюджет на тело новости считаем по видимым символам с
     * запасом под многоточие и возможные эмодзи.
     */
    private function caption(News $news, int $limit): string
    {
        $url = route('news-details', $news);
        $title = trim((string) $news->title);
        $linkText = 'Читать полностью';

        // Видимая длина фиксированных частей: заголовок + ссылка + два «\n\n».
        $fixed = mb_strlen($title) + mb_strlen($linkText) + 4;
        // Запас (16): многоточие Str::limit + расхождение код-поинтов и UTF-16.
        $budget = $limit - $fixed - 16;

        $body = $this->plainContent($news);
        $body = $budget > 0 ? Str::limit($body, $budget, '…') : '';

        $parts = array_filter([
            '<b>'.e($title).'</b>',
            $body !== '' ? e($body) : null,
            '<a href="'.e($url).'">'.e($linkText).'</a>',
        ]);

        return implode("\n\n", $parts);
    }

    /**
     * Превращает HTML-содержимое новости в чистый текст.
     */
    private function plainContent(News $news): string
    {
        $text = preg_replace('/<\/(p|div|br|li|h[1-6])\s*\/?>/i', "\n", (string) $news->content);

        return trim(preg_replace("/\n{3,}/", "\n\n", strip_tags(html_entity_decode($text))));
    }

    /**
     * Относительный путь к обложке на публичном диске, если файл существует.
     * Если файла нет локально — вернётся null, и новость уйдёт текстом со ссылкой.
     */
    private function coverPath(News $news): ?string
    {
        if (empty($news->cover_image_url)) {
            return null;
        }

        return Storage::disk('public')->exists($news->cover_image_url)
            ? $news->cover_image_url
            : null;
    }
}
