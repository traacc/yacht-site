<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\RegattaEntry;
use App\Notifications\RegattaEntryModeratedNotification;

/**
 * Сообщает заявителю исход модерации заявки на регату.
 *
 * Обсервер, а не вызов из админки: статус меняют и ресурс «Одобрение заявок»,
 * и массовые действия, и методы модели approve()/reject() — уведомление должно
 * уходить независимо от места вызова.
 *
 * Регистрируется в AppServiceProvider.
 */
class RegattaEntryModerationObserver
{
    public function updated(RegattaEntry $entry): void
    {
        if (! $entry->wasChanged('status')) {
            return;
        }

        if (! in_array($entry->status, ['approved', 'rejected'], true)) {
            return;
        }

        // Автор известен только у заявок, поданных через сайт или ЛК.
        $user = $entry->applicant;

        if ($user === null) {
            return;
        }

        $entry->loadMissing('regatta');

        $user->notify(new RegattaEntryModeratedNotification(
            regattaName: (string) $entry->regatta?->name,
            approved: $entry->status === 'approved',
        ));
    }
}
