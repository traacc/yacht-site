<?php

declare(strict_types=1);

namespace App\Actions\Advert;

use App\Models\Advert;
use App\Models\User;
use App\Notifications\AdvertModeratedNotification;

/**
 * Решение модератора по объявлению.
 *
 * Автору уходит уведомление по его настройкам центра уведомлений (email,
 * Telegram, колокольчик) — в отличие от служебного уведомления модераторам,
 * это личное сообщение пользователю, и глушить его настройками правильно.
 */
class ModerateAdvertAction
{
    public function approve(Advert $advert, ?User $moderator = null): Advert
    {
        $advert->approve($moderator);

        $this->notifyAuthor($advert, approved: true);

        return $advert;
    }

    public function reject(Advert $advert, ?string $reason = null, ?User $moderator = null): Advert
    {
        $reason = trim((string) $reason);

        $advert->reject($reason === '' ? null : $reason, $moderator);

        $this->notifyAuthor($advert, approved: false);

        return $advert;
    }

    private function notifyAuthor(Advert $advert, bool $approved): void
    {
        $author = $advert->author;

        if (! $author instanceof User) {
            return;
        }

        // В конструктор уведомления только скаляры: оно уходит в очередь.
        $author->notify(new AdvertModeratedNotification(
            advertTitle: $advert->title,
            approved: $approved,
            reason: $advert->rejection_reason,
        ));
    }
}
