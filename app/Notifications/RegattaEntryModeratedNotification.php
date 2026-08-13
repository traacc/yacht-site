<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Filament\User\Resources\RegattaEntries\RegattaEntryResource;

/**
 * Решение администратора по заявке на регату.
 *
 * Заявки на регулярные и выездные регаты подаются со статусом «на
 * рассмотрении» (@see App\Actions\Regatta\SubmitSeatEntryAction), поэтому
 * заявитель узнаёт исход в личном кабинете.
 */
final class RegattaEntryModeratedNotification extends UserNotification
{
    public function __construct(
        public readonly string $regattaName,
        public readonly bool $approved,
    ) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::SiteRequests;
    }

    public function title(): string
    {
        return $this->approved
            ? 'Заявка на регату одобрена'
            : 'Заявка на регату отклонена';
    }

    public function body(): string
    {
        return $this->approved
            ? 'Ваша заявка на регату «'.$this->regattaName.'» одобрена. Детали участия — в личном кабинете.'
            : 'Ваша заявка на регату «'.$this->regattaName.'» отклонена. Уточнить причину можно у организаторов.';
    }

    public function url(): ?string
    {
        // Панель указываем явно: уведомление уходит из очереди, где текущей панели нет.
        return RegattaEntryResource::getUrl(panel: 'user');
    }

    public function icon(): string
    {
        return $this->approved ? 'heroicon-o-check-badge' : 'heroicon-o-x-circle';
    }
}
