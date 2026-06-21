<?php

namespace App\Jobs;

use App\Models\News;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishNewsToTelegram implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public string $newsId)
    {
    }

    public function handle(TelegramService $telegram): void
    {
        // Атомарно «захватываем» новость: помечаем published_to_tg только если
        // она ещё не была опубликована. Это защищает от двойной отправки, когда
        // джоба попадает в очередь и из observer'а, и из плановой команды.
        $claimed = News::query()
            ->whereKey($this->newsId)
            ->where('published_to_tg', false)
            ->update(['published_to_tg' => true]);

        if ($claimed === 0) {
            return;
        }

        $news = News::find($this->newsId);

        if ($news === null || ! $news->isPublished()) {
            // Новость удалена или снята с публикации — освобождаем захват.
            $news?->updateQuietly(['published_to_tg' => false]);

            return;
        }

        if (! $telegram->publishNews($news)) {
            // Отправка не удалась — сбрасываем флаг, чтобы повторить попытку.
            $news->updateQuietly(['published_to_tg' => false]);

            $this->fail(new \RuntimeException("Не удалось опубликовать новость {$this->newsId} в Telegram"));
        }
    }
}
