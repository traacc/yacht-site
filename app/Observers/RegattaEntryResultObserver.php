<?php

namespace App\Observers;

use App\Filament\Resources\RegattaResults\RegattaResultResource;
use App\Models\RegattaEntry;
use App\Models\RegattaResult;
use App\Models\RegattaResultItem;

/**
 * Пересчитывает результаты регаты при удалении заявки команды.
 *
 * 1. Удаляем результаты гонок заявки (race_results): объявленный в миграции
 *    внешний ключ ON DELETE CASCADE по факту не срабатывает (таблица без
 *    действующего FK), поэтому иначе строки осиротели бы.
 * 2. Удаляем строки участника (regatta_result_items) этой команды из всех
 *    результатов регаты — команда без заявки не должна оставаться в таблице.
 * 3. Пересчитываем очки гонок, зависящие от числа лодок: очки за буквенные
 *    статусы (DNF, DSQ…) равны «число лодок + 1», а число лодок уменьшилось.
 * 4. Пересчитываем итоговые очки и места оставшихся участников и рейтинги
 *    сезона: они опираются на удалённые гонки, а места сдвигаются после
 *    выбывания команды.
 *
 * У регаты может быть несколько результатов (предварительный/финальный),
 * поэтому обрабатываем каждый.
 *
 * Регистрируется в AppServiceProvider:
 *   RegattaEntry::observe(RegattaEntryResultObserver::class);
 */
class RegattaEntryResultObserver
{
    public function deleted(RegattaEntry $entry): void
    {
        $entry->raceResults()->delete();

        $results = RegattaResult::query()
            ->where('regatta_id', $entry->regatta_id)
            ->get();

        // Убираем строки выбывшей команды из всех результатов регаты.
        RegattaResultItem::query()
            ->whereIn('regatta_result_id', $results->modelKeys())
            ->where('team_id', $entry->team_id)
            ->when(
                filled($entry->yacht_id),
                fn ($q) => $q->where('yacht_id', $entry->yacht_id),
            )
            ->delete();

        $results->each(function (RegattaResult $result): void {
            RegattaResultResource::recomputeRacePoints($result);
            RegattaResultResource::recomputeItemTotals($result);
            RegattaResultResource::recalculateRatings($result);
        });
    }
}
