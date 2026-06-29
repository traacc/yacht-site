<?php

namespace App\Observers;

use App\Jobs\PublishNewsToTelegram;
use App\Jobs\PublishNewsToVk;
use App\Models\News;
use App\Services\SettingsService;

/**
 * Отслеживает события новости.
 * Регистрируется в AppServiceProvider:
 *   News::observe(NewsObserver::class);
 */
class NewsObserver
{
    /**
     * После сохранения новости: если она уже опубликована и ещё не была
     * отправлена в Telegram / VK — ставим задачи на публикацию в соцсети.
     *
     * Новости с будущей датой публикации подхватят плановые команды
     * news:publish-to-telegram и news:publish-to-vk, когда наступит published_at.
     */
    public function saved(News $news): void
    {
        if (! $news->isPublished()) {
            return;
        }

        $settings = app(SettingsService::class);

        if (! $news->published_to_tg && $settings->get('home.telegram_autopublish', true)) {
            PublishNewsToTelegram::dispatch($news->id);
        }

        if (! $news->published_to_vk && $settings->get('home.vk_autopublish', true)) {
            PublishNewsToVk::dispatch($news->id);
        }
    }
}
