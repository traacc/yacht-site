<?php

declare(strict_types=1);

namespace App\Actions\News;

use App\Models\News;
use DomainException;

/**
 * Возвращает снятую новость на сайт с исходной датой публикации.
 *
 * Сохраняем через Eloquent, чтобы сработал NewsObserver: если до снятия
 * новость не успела уйти в Telegram/VK или подписчикам, это произойдёт сейчас.
 * Уже отправленное повторно не уходит — флаги published_to_* остаются.
 */
final class RepublishNewsAction
{
    public function handle(News $news): void
    {
        $news->refresh();

        if (! $news->isUnpublished()) {
            throw new DomainException('Новость не снята с публикации.');
        }

        $news->update(['unpublished_at' => null]);
    }
}
