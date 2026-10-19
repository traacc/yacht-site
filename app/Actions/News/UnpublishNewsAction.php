<?php

declare(strict_types=1);

namespace App\Actions\News;

use App\Models\News;
use DomainException;

/**
 * Снимает новость с сайта, не удаляя её.
 *
 * Скоуп News::published() и isPublished() учитывают unpublished_at, поэтому
 * новость сразу пропадает из лент, sitemap и детальных страниц (404), а
 * ещё не отправленные джобы Telegram/VK/рассылки отпускают захват.
 * Уже опубликованные посты в соцсетях не удаляются.
 */
final class UnpublishNewsAction
{
    public function handle(News $news): void
    {
        // Условие в самом UPDATE: повторное нажатие не перезапишет дату снятия.
        $updated = News::query()
            ->whereKey($news->getKey())
            ->whereNull('unpublished_at')
            ->update(['unpublished_at' => now()]);

        if ($updated !== 1) {
            throw new DomainException('Новость уже снята с публикации.');
        }

        $news->refresh();
    }
}
