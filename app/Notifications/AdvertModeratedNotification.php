<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Filament\User\Resources\Adverts\AdvertResource as UserAdvertResource;

/**
 * Решение модератора по объявлению автора.
 *
 * Категория SiteRequests уже описана как «ответы на ваши вопросы, отклики на
 * объявления, заявки на аренду», поэтому отдельная настройка не нужна.
 *
 * В конструкторе только скаляры: уведомление сериализуется в очередь
 * (@see UserNotification).
 */
final class AdvertModeratedNotification extends UserNotification
{
    public function __construct(
        public readonly string $advertTitle,
        public readonly bool $approved,
        public readonly ?string $reason = null,
    ) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::SiteRequests;
    }

    public function title(): string
    {
        return $this->approved
            ? 'Объявление опубликовано'
            : 'Объявление отклонено';
    }

    public function body(): string
    {
        if ($this->approved) {
            return '«'.$this->advertTitle.'» прошло модерацию и опубликовано на сайте.';
        }

        $body = '«'.$this->advertTitle.'» не прошло модерацию.';

        return $this->reason !== null && $this->reason !== ''
            ? $body.' Причина: '.$this->reason
            : $body.' Вы можете отредактировать его и отправить повторно.';
    }

    public function url(): ?string
    {
        // Панель указываем явно: уведомление отправляется из очереди, где текущей
        // панели нет, и getUrl() собрал бы несуществующий маршрут.
        return UserAdvertResource::getUrl(panel: 'user');
    }

    public function icon(): string
    {
        return $this->approved
            ? 'heroicon-o-check-circle'
            : 'heroicon-o-x-circle';
    }
}
