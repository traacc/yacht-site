<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\News;
use App\Models\User;
use App\Notifications\NewsPublishedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Рассылает уведомление о новости подписчикам категории «Анонсы и новости».
 *
 * Сама ничего не отправляет: режет получателей на порции и раскидывает
 * SendUserNotificationChunk, чтобы одна долгая job не упиралась в таймаут.
 */
class NotifyUsersAboutNews implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Размер порции получателей на одну job. */
    private const CHUNK_SIZE = 500;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public string $newsId) {}

    public function handle(): void
    {
        // Атомарный «захват»: защищает от повторной рассылки, если новость
        // пересохранили (observer срабатывает на каждое saved()).
        $claimed = News::query()
            ->whereKey($this->newsId)
            ->where('notified_users', false)
            ->update(['notified_users' => true]);

        if ($claimed === 0) {
            return;
        }

        $news = News::find($this->newsId);

        if ($news === null || ! $news->isPublished()) {
            $news?->updateQuietly(['notified_users' => false]);

            return;
        }

        $notification = new NewsPublishedNotification(
            newsTitle: (string) $news->title,
            newsUrl: route('news-details', $news),
            excerpt: $this->excerpt($news),
            coverUrl: $this->coverUrl($news),
        );

        User::query()
            ->select('id')
            ->chunkById(self::CHUNK_SIZE, function (Collection $users) use ($notification): void {
                SendUserNotificationChunk::dispatch($users->modelKeys(), $notification);
            });
    }

    /**
     * Абсолютный URL обложки для письма — или null, если обложки нет либо
     * файл отсутствует на диске (иначе в письме будет «битая» картинка).
     */
    private function coverUrl(News $news): ?string
    {
        $path = (string) $news->cover_image_url;

        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    private function excerpt(News $news): string
    {
        $text = preg_replace('/<\/(p|div|br|li|h[1-6])\s*\/?>/i', "\n", (string) $news->content);
        $text = trim(preg_replace("/\n{3,}/", "\n\n", strip_tags(html_entity_decode((string) $text))));

        return Str::limit($text, 400, '…');
    }
}
