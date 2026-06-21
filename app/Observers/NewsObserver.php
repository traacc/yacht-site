<?php

namespace App\Observers;

use App\Jobs\PublishNewsToTelegram;
use App\Models\News;

/**
 * Отслеживает события новости.
 * Регистрируется в AppServiceProvider:
 *   News::observe(NewsObserver::class);
 */
class NewsObserver
{
    /**
     * После сохранения новости: если она уже опубликована и ещё не была
     * отправлена в Telegram — ставим задачу на публикацию в канал.
     *
     * Новости с будущей датой публикации подхватит плановая команда
     * news:publish-to-telegram, когда наступит published_at.
     */
    public function saved(News $news): void
    {
        if ($news->published_to_tg || ! $news->isPublished()) {
            return;
        }

        PublishNewsToTelegram::dispatch($news->id);
    }
}
