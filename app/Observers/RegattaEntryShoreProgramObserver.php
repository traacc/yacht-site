<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\RegattaEntry\SyncShoreProgramPaymentAction;
use App\Models\RegattaEntry;

/**
 * Пересчитывает счёт за береговую программу, когда меняется число человек
 * (или регата, а с ней цена). Обсервер, а не вызовы из форм: заявку сохраняют
 * публичные формы, ЛК, админка и «Перезаписать» — счёт должен следовать за
 * любым из этих путей.
 *
 * Регистрируется в AppServiceProvider:
 *   RegattaEntry::observe(RegattaEntryShoreProgramObserver::class);
 */
class RegattaEntryShoreProgramObserver
{
    public function created(RegattaEntry $entry): void
    {
        if ($entry->shore_program_persons !== null) {
            app(SyncShoreProgramPaymentAction::class)->handle($entry);
        }
    }

    public function updated(RegattaEntry $entry): void
    {
        if ($entry->wasChanged(['shore_program_persons', 'regatta_id'])) {
            app(SyncShoreProgramPaymentAction::class)->handle($entry);
        }
    }
}
