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

    /**
     * @param  bool  $force  Принудительная отправка (ручная публикация из админки),
     *                       игнорирует флаг published_to_tg и отправляет повторно.
     */
    public function __construct(public string $newsId, public bool $force = false)
    {
    }

    public function handle(TelegramService $telegram): void
    {
        if (! $this->force) {
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
        }

        $news = News::find($this->newsId);

        if ($news === null || ! $news->isPublished()) {
            // Новость удалена или снята с публикации — освобождаем захват.
            if (! $this->force) {
                $news?->updateQuietly(['published_to_tg' => false]);
            }

            return;
        }

        if ($telegram->publishNews($news)) {
            // Помечаем как отправленную (в force-режиме «захват» не ставил флаг).
            if (! $news->published_to_tg) {
                $news->updateQuietly(['published_to_tg' => true]);
            }

            return;
        }

        // Отправка не удалась. В обычном режиме освобождаем захват, чтобы
        // плановая команда повторила; в force-режиме чужой флаг не трогаем.
        if (! $this->force) {
            $news->updateQuietly(['published_to_tg' => false]);
        }

        $this->fail(new \RuntimeException("Не удалось опубликовать новость {$this->newsId} в Telegram"));
    }
}
