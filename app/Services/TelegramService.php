<?php

namespace App\Services;

use App\Models\News;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramService
{
    private const TIMEOUT_SECONDS = 10;

    // Лимиты Telegram Bot API на длину текста.
    private const CAPTION_LIMIT = 1024;
    private const MESSAGE_LIMIT = 4096;

    public function __construct(
        private readonly ?string $botToken = null,
        private readonly ?string $chatId = null,
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

    public function isConfigured(): bool
    {
        return ! empty($this->token()) && ! empty($this->chat());
    }

    /**
     * Публикует новость в Telegram-канал/группу.
     * Если задана обложка — отправляет фото с подписью, иначе текстовое сообщение.
     */
    public function publishNews(News $news): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('Telegram is not configured, skipping news publication', [
                'news_id' => $news->id,
            ]);

            return false;
        }

        $photoUrl = $this->coverUrl($news);

        return $photoUrl !== null
            ? $this->sendPhoto($photoUrl, $this->caption($news, self::CAPTION_LIMIT), $news)
            : $this->sendMessage($this->caption($news, self::MESSAGE_LIMIT), $news);
    }

    private function sendPhoto(string $photoUrl, string $caption, News $news): bool
    {
        return $this->call('sendPhoto', [
            'chat_id'    => $this->chat(),
            'photo'      => $photoUrl,
            'caption'    => $caption,
            'parse_mode' => 'HTML',
        ], $news);
    }

    private function sendMessage(string $text, News $news): bool
    {
        return $this->call('sendMessage', [
            'chat_id'                  => $this->chat(),
            'text'                     => $text,
            'parse_mode'               => 'HTML',
            'disable_web_page_preview' => false,
        ], $news);
    }

    private function call(string $method, array $payload, News $news): bool
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->retry(2, 1000, throw: false)
                ->asJson()
                ->post("https://api.telegram.org/bot{$this->token()}/{$method}", $payload);

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
     */
    private function caption(News $news, int $limit): string
    {
        $url   = route('news-details', $news);
        $title = '<b>' . e($news->title) . '</b>';
        $link  = '<a href="' . e($url) . '">Читать полностью →</a>';

        // Запас под заголовок, ссылку и переносы строк.
        $reserved = mb_strlen(strip_tags($title)) + mb_strlen('Читать полностью →') + 4;
        $bodyText = $this->plainContent($news);
        $body     = e(Str::limit($bodyText, max(0, $limit - $reserved)));

        return trim($title . "\n\n" . $body . "\n\n" . $link);
    }

    /**
     * Превращает HTML-содержимое новости в чистый текст.
     */
    private function plainContent(News $news): string
    {
        $text = preg_replace('/<\/(p|div|br|li|h[1-6])\s*\/?>/i', "\n", (string) $news->content);

        return trim(preg_replace("/\n{3,}/", "\n\n", strip_tags(html_entity_decode($text))));
    }

    private function coverUrl(News $news): ?string
    {
        if (empty($news->cover_image_url)) {
            return null;
        }

        return asset('storage/' . $news->cover_image_url);
    }
}
