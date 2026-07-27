<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Filament\Pages\BroadcastNotification;

/**
 * Произвольное уведомление, отправленное администратором из админ-панели.
 *
 * @see BroadcastNotification
 */
final class AdminBroadcastNotification extends UserNotification
{
    public function __construct(
        public readonly string $categoryValue,
        public readonly string $subject,
        public readonly string $message,
        public readonly ?string $link = null,
    ) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::from($this->categoryValue);
    }

    public function title(): string
    {
        return $this->subject;
    }

    public function body(): string
    {
        return $this->message;
    }

    public function url(): ?string
    {
        return $this->link;
    }

    public function icon(): string
    {
        return 'heroicon-o-megaphone';
    }
}
