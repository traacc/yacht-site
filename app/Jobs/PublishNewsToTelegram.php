<?php

namespace App\Jobs;

use App\Models\News;
use App\Services\Telegram\TelegramSendResult;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishNewsToTelegram implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  bool  $force  Принудительная отправка (ручная публикация из админки),
     *                       игнорирует флаг published_to_tg и отправляет повторно.
     */
    public function __construct(public string $newsId, public bool $force = false) {}

    /** Сколько раз всего пытаемся отправить новость (см. config/services.php). */
    public function tries(): int
    {
        return max(1, (int) config('services.telegram.publish_tries', 5));
    }

    public function handle(TelegramService $telegram): void
    {
        if (! $this->force && $this->attempts() === 1) {
            // Атомарно «захватываем» новость: помечаем published_to_tg только если
            // она ещё не была опубликована. Это защищает от двойной отправки, когда
            // джоба попадает в очередь и из observer'а, и из плановой команды.
            // На повторных заходах захват уже наш — перезахватывать не нужно.
            $claimed = News::query()
                ->whereKey($this->newsId)
                ->where('published_to_tg', false)
                ->update(['published_to_tg' => true]);

            if ($claimed === 0) {
                return;
            }
        }

        $news = News::find($this->newsId);

        if ($news === null || ! $news->isPublished()) {
            // Новость удалена или снята с публикации — освобождаем захват.
            if (! $this->force) {
                $news?->updateQuietly(['published_to_tg' => false]);
            }

            return;
        }

        $result = $telegram->publishNews($news);

        if ($result->ok) {
            // Помечаем как отправленную (в force-режиме «захват» не ставил флаг).
            if (! $news->published_to_tg) {
                $news->updateQuietly(['published_to_tg' => true]);
            }

            return;
        }

        // Связи с Telegram нет (или он ответил 429/5xx) — вернём джобу в очередь
        // и попробуем ещё раз. Захват при этом не снимаем: пока джоба жива,
        // плановая команда не должна ставить дубль в очередь.
        if ($result->shouldRetry() && $this->attempts() < $this->tries()) {
            $this->release($this->retryDelay($result));

            return;
        }

        // Попытки исчерпаны или ошибка неустранимая. В обычном режиме освобождаем
        // захват, чтобы плановая команда повторила позже; в force-режиме чужой
        // флаг не трогаем.
        if (! $this->force) {
            $news->updateQuietly(['published_to_tg' => false]);
        }

        $this->fail(new \RuntimeException(
            "Не удалось опубликовать новость {$this->newsId} в Telegram"
            .' (попыток: '.$this->attempts().'): '.($result->description ?? 'неизвестная ошибка')
        ));
    }

    /**
     * Страховка на случай, когда джоба падает не через handle() (исключение
     * в очереди, превышение $tries воркером): захват нужно снять, иначе новость
     * навсегда останется помеченной как отправленная.
     */
    public function failed(?\Throwable $exception): void
    {
        if ($this->force) {
            return;
        }

        News::query()->whereKey($this->newsId)->update(['published_to_tg' => false]);
    }

    /**
     * Пауза перед следующей попыткой, сек. Требование Telegram (retry_after)
     * важнее нашей лестницы задержек.
     */
    private function retryDelay(TelegramSendResult $result): int
    {
        if ($result->retryAfter !== null && $result->retryAfter > 0) {
            return $result->retryAfter;
        }

        /** @var list<int> $backoff */
        $backoff = (array) config('services.telegram.publish_backoff', [60]);
        $backoff = array_values(array_filter($backoff, static fn (mixed $seconds): bool => is_numeric($seconds)));

        if ($backoff === []) {
            return 60;
        }

        return (int) ($backoff[$this->attempts() - 1] ?? end($backoff));
    }
}
