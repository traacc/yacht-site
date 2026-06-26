<?php

namespace App\Services;

use App\Models\News;
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

    public function isConfigured(): bool
    {
        return ! empty($this->token()) && ! empty($this->chat());
    }

    /**
     * Публикует новость в Telegram-канал/группу.
     * Если есть обложка — отправляет фото с подписью, иначе текстовое сообщение.
     */
    public function publishNews(News $news): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('Telegram is not configured, skipping news publication', [
                'news_id' => $news->id,
            ]);

            return false;
        }

        $coverPath = $this->coverPath($news);

        return $coverPath !== null
            ? $this->sendPhoto($coverPath, $this->caption($news, self::CAPTION_LIMIT), $news)
            : $this->sendMessage($this->caption($news, self::MESSAGE_LIMIT), $news);
    }

    /**
     * Отправляет обложку как загружаемый файл (multipart), а не ссылкой:
     * это не зависит от публичной доступности сайта и идёт через наш прокси.
     */
    private function sendPhoto(string $coverPath, string $caption, News $news): bool
    {
        $contents = Storage::disk('public')->get($coverPath);

        return $this->execute('sendPhoto', $news, fn (PendingRequest $request): Response => $request
            ->attach('photo', $contents, basename($coverPath))
            ->post($this->endpoint('sendPhoto'), [
                'chat_id'    => $this->chat(),
                'caption'    => $caption,
                'parse_mode' => 'HTML',
            ]));
    }

    private function sendMessage(string $text, News $news): bool
    {
        return $this->execute('sendMessage', $news, fn (PendingRequest $request): Response => $request
            ->asJson()
            ->post($this->endpoint('sendMessage'), [
                'chat_id'                  => $this->chat(),
                'text'                     => $text,
                'parse_mode'               => 'HTML',
                'disable_web_page_preview' => false,
            ]));
    }

    /**
     * Базовый запрос с таймаутами, ретраями и прокси (если задан).
     */
    private function baseRequest(): PendingRequest
    {
        $request = Http::connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
            ->timeout(self::TIMEOUT_SECONDS)
            ->retry(2, 1000, throw: false);

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
     * @param  callable(PendingRequest): Response  $send
     */
    private function execute(string $method, News $news, callable $send): bool
    {
        try {
            $response = $send($this->baseRequest());

            if ($response->successful() && $response->json('ok') === true) {
                return true;
            }

            Log::warning('Telegram API responded with error', [
                'news_id' => $news->id,
                'method'  => $method,
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);

            return false;
        } catch (ConnectionException | RequestException $e) {
            Log::warning('Telegram API connection failed', [
                'news_id' => $news->id,
                'method'  => $method,
                'error'   => $e->getMessage(),
            ]);

            return false;
        }
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
        $url      = route('news-details', $news);
        $title    = trim((string) $news->title);
        $linkText = 'Читать полностью →';

        // Видимая длина фиксированных частей: заголовок + ссылка + два «\n\n».
        $fixed  = mb_strlen($title) + mb_strlen($linkText) + 4;
        // Запас (16): многоточие Str::limit + расхождение код-поинтов и UTF-16.
        $budget = $limit - $fixed - 16;

        $body = $this->plainContent($news);
        $body = $budget > 0 ? Str::limit($body, $budget, '…') : '';

        $parts = array_filter([
            '<b>' . e($title) . '</b>',
            $body !== '' ? e($body) : null,
            '<a href="' . e($url) . '">' . e($linkText) . '</a>',
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
