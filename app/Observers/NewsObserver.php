<?php

namespace App\Observers;

use App\Jobs\NotifyUsersAboutNews;
use App\Jobs\PublishNewsToTelegram;
use App\Jobs\PublishNewsToVk;
use App\Models\News;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Storage;

/**
 * Отслеживает события новости.
 * Регистрируется в AppServiceProvider:
 *   News::observe(NewsObserver::class);
 */
class NewsObserver
{
    /**
     * При замене или удалении обложки убираем прежний файл: Filament его
     * с диска не стирает, и обложки копились бы в news/covers.
     */
    public function updated(News $news): void
    {
        if (! $news->wasChanged('cover_image_url')) {
            return;
        }

        $old = $news->getOriginal('cover_image_url');

        if (! is_string($old) || $old === '' || str_starts_with($old, 'http')) {
            return;
        }

        $stillUsed = News::withTrashed()
            ->where('cover_image_url', $old)
            ->exists();

        if (! $stillUsed) {
            Storage::disk('public')->delete($old);
        }
    }

    /**
     * После сохранения новости: если она уже опубликована и ещё не была
     * отправлена в Telegram / VK — ставим задачи на публикацию в соцсети.
     *
     * Новости с будущей датой публикации подхватят плановые команды
     * news:publish-to-telegram и news:publish-to-vk, когда наступит published_at.
     */
    public function saved(News $news): void
    {
        // Автоматические внешние материалы живут только в разделе «Новости
        // парусного мира». Массовая рассылка и соцсети остаются контуром
        // редакционных новостей ассоциации.
        if ($news->type !== 'manual' || ! $news->isPublished()) {
            return;
        }

        $settings = app(SettingsService::class);

        if (! $news->published_to_tg && $settings->get('home.telegram_autopublish', true)) {
            PublishNewsToTelegram::dispatch($news->id);
        }

        if (! $news->published_to_vk && $settings->get('home.vk_autopublish', true)) {
            PublishNewsToVk::dispatch($news->id);
        }

        // Рассылка подписчикам категории «Анонсы и новости» центра уведомлений.
        if (! $news->notified_users && $settings->get('home.news_notifications', true)) {
            NotifyUsersAboutNews::dispatch($news->id);
        }
    }
}
