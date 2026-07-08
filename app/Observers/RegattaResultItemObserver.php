<?php

namespace App\Observers;

use App\Models\RegattaResultItem;

/**
 * Поддерживает денормализованный снапшот строки результата.
 *
 * Пока команда и яхта существуют, дозаполняет пустые поля снимка их текущими
 * именами. К моменту, когда команду/яхту и заявку удаляют, снимок уже готов —
 * и итоговая строка результата продолжает читаемо отображаться.
 * Финальную «заморозку» (перезапись снимка на актуальные значения и обнуление
 * ссылок) для завершённых регат выполняет RegattaEntryResultObserver.
 *
 * Регистрируется в AppServiceProvider:
 *   RegattaResultItem::observe(RegattaResultItemObserver::class);
 */
class RegattaResultItemObserver
{
    public function saving(RegattaResultItem $item): void
    {
        if (blank($item->team_name) && filled($item->team_id)) {
            $item->team_name = $item->team?->name;
        }

        if (filled($item->yacht_id)) {
            if (blank($item->yacht_name)) {
                $item->yacht_name = $item->yacht?->name;
            }
            if (blank($item->sail_number)) {
                $item->sail_number = $item->yacht?->vfps_number;
            }
        }
    }
}
