<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Filament\Resources\CrewJoinRequests\CrewJoinRequestResource;
use App\Filament\User\Resources\RegattaEntries\RegattaEntryResource;
use Illuminate\Support\Str;

/**
 * Кто-то хочет попасть в экипаж — уведомление автору заявки и администрации.
 *
 * Различаются только ссылкой «Открыть»: автор идёт в свои заявки в ЛК,
 * администратор — в раздел откликов админ-панели (@see forAdmin()).
 */
final class CrewJoinRequestSubmittedNotification extends UserNotification
{
    public function __construct(
        public readonly string $applicantName,
        public readonly string $applicantContacts,
        public readonly string $regattaName,
        public readonly ?string $teamName = null,
        public readonly ?string $message = null,
        public readonly bool $admin = false,
    ) {}

    /** Тот же текст, но со ссылкой в админ-панель. */
    public function forAdmin(): self
    {
        return new self(
            applicantName: $this->applicantName,
            applicantContacts: $this->applicantContacts,
            regattaName: $this->regattaName,
            teamName: $this->teamName,
            message: $this->message,
            admin: true,
        );
    }

    public function category(): NotificationCategory
    {
        return NotificationCategory::SiteRequests;
    }

    public function title(): string
    {
        return 'Заявка в экипаж: '.$this->applicantName;
    }

    public function body(): string
    {
        $lines = [
            $this->applicantName.' хочет попасть в экипаж'
                .($this->teamName ? ' «'.$this->teamName.'»' : '')
                .' на регате «'.$this->regattaName.'».',
            'Контакты: '.$this->applicantContacts,
        ];

        if (filled($this->message)) {
            $lines[] = 'Сообщение: '.Str::limit($this->message, 500);
        }

        return implode("\n\n", $lines);
    }

    public function url(): ?string
    {
        // Панель указываем явно: уведомление уходит из очереди, где текущей панели нет.
        return $this->admin
            ? CrewJoinRequestResource::getUrl(panel: 'admin')
            : RegattaEntryResource::getUrl(panel: 'user');
    }

    public function icon(): string
    {
        return 'heroicon-o-user-plus';
    }
}
