<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use Illuminate\Support\Str;

/**
 * Ответ экипажа на отклик «Хочу в этот экипаж».
 *
 * Уходит только зарегистрированным откликнувшимся; гостю ответ приходит
 * письмом от самого экипажа на указанную им почту.
 */
final class CrewJoinRequestResolvedNotification extends UserNotification
{
    public function __construct(
        public readonly bool $accepted,
        public readonly string $regattaName,
        public readonly ?string $teamName = null,
        public readonly ?string $responseNote = null,
        public readonly ?string $regattaUrl = null,
    ) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::SiteRequests;
    }

    public function title(): string
    {
        return $this->accepted
            ? 'Вас приняли в экипаж'
            : 'Экипаж отклонил вашу заявку';
    }

    public function body(): string
    {
        $crew = $this->teamName ? 'Экипаж «'.$this->teamName.'»' : 'Экипаж';

        $lines = [
            $this->accepted
                ? $crew.' принял вас на регату «'.$this->regattaName.'».'
                : $crew.' не смог принять вас на регату «'.$this->regattaName.'».',
        ];

        if (filled($this->responseNote)) {
            $lines[] = Str::limit($this->responseNote, 500);
        }

        return implode("\n\n", $lines);
    }

    public function url(): ?string
    {
        return $this->regattaUrl;
    }

    public function icon(): string
    {
        return $this->accepted ? 'heroicon-o-check-badge' : 'heroicon-o-x-circle';
    }
}
