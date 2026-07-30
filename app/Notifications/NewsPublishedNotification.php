<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Jobs\NotifyUsersAboutNews;

/**
 * Опубликована новость ассоциации.
 *
 * @see NotifyUsersAboutNews
 */
final class NewsPublishedNotification extends UserNotification
{
    public function __construct(
        public readonly string $newsTitle,
        public readonly string $newsUrl,
        public readonly string $excerpt,
        public readonly ?string $coverUrl = null,
    ) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::News;
    }

    public function title(): string
    {
        return $this->newsTitle;
    }

    public function body(): string
    {
        return $this->excerpt;
    }

    public function url(): ?string
    {
        return $this->newsUrl;
    }

    /** Обложка новости, если она есть и файл лежит на диске. */
    public function imageUrl(): ?string
    {
        return $this->coverUrl;
    }

    public function icon(): string
    {
        return 'heroicon-o-newspaper';
    }
}
