<?php

namespace App\Observers;

use App\Enums\RegattaStatus;
use App\Filament\Resources\RegattaResults\RegattaResultResource;
use App\Models\RegattaEntry;
use App\Models\RegattaResult;
use App\Models\RegattaResultItem;

/**
 * Реагирует на удаление заявки команды на регату.
 *
 * Поведение зависит от статуса регаты:
 *
 * A. Регата ещё «живая» (планируется/идёт) — заявку реально отзывают до итогов,
 *    поэтому результаты пересчитываем:
 *    1. Удаляем результаты гонок заявки (race_results): объявленный в миграции
 *       внешний ключ ON DELETE CASCADE по факту не срабатывает (таблица без
 *       действующего FK), иначе строки осиротели бы.
 *    2. Удаляем строки участника (regatta_result_items) этой команды.
 *    3. Пересчитываем очки гонок, зависящие от числа лодок (DNF/DSQ… = «число
 *       лодок + 1»), затем итоги, места и рейтинги сезона.
 *
 * B. Регата завершена/отменена/перенесена (архивная) — итоговые результаты
 *    историчны и не должны исчезать вместе с командой и её заявкой. Поэтому
 *    строки НЕ удаляем и НЕ пересчитываем, а «замораживаем»: сохраняем снапшот
 *    (имя команды, яхта, парусный номер, рулевой) и обнуляем живые ссылки.
 *
 * Регистрируется в AppServiceProvider:
 *   RegattaEntry::observe(RegattaEntryResultObserver::class);
 */
class RegattaEntryResultObserver
{
    /** Статусы регаты, при которых результаты считаются историческими. */
    private const ARCHIVED_STATUSES = [
        RegattaStatus::Finished,
        RegattaStatus::Cancelled,
        RegattaStatus::Postponed,
    ];

    public function deleted(RegattaEntry $entry): void
    {
        $status = $entry->regatta?->regatta_status;

        if ($status !== null && in_array($status, self::ARCHIVED_STATUSES, true)) {
            $this->freezeArchivedResults($entry);

            return;
        }

        $this->recomputeLiveResults($entry);
    }

    /**
     * Вариант B: сохраняем итоговые строки, отвязывая их от удаляемой команды.
     */
    private function freezeArchivedResults(RegattaEntry $entry): void
    {
        $resultIds = RegattaResult::query()
            ->where('regatta_id', $entry->regatta_id)
            ->pluck('id');

        $captainName = $entry->crew()
            ->where('role', 'captain')
            ->with('teamMember.user')
            ->first()
            ?->teamMember?->user?->name;

        RegattaResultItem::query()
            ->whereIn('regatta_result_id', $resultIds)
            ->where('team_id', $entry->team_id)
            ->when(
                filled($entry->yacht_id),
                fn ($q) => $q->where('yacht_id', $entry->yacht_id),
            )
            ->get()
            ->each(function (RegattaResultItem $item) use ($captainName): void {
                $item->forceFill([
                    // Перезаписываем снимок актуальными значениями на момент заморозки.
                    'team_name'    => $item->team?->name ?? $item->team_name,
                    'yacht_name'   => $item->yacht?->name ?? $item->yacht_name,
                    'sail_number'  => $item->yacht?->vfps_number ?? $item->sail_number,
                    'captain_name' => $captainName ?? $item->captain_name,
                    // Отвязываем от удаляемых сущностей — итоговые очки и место остаются.
                    'team_id'      => null,
                    'yacht_id'     => null,
                ])->saveQuietly();
            });

        // Результаты гонок привязаны к удаляемой заявке и уже недостижимы —
        // убираем, чтобы не осиротели (итоги команды хранит regatta_result_items).
        $entry->raceResults()->delete();
    }

    /**
     * Вариант A: прежнее разрушительное поведение для «живых» регат.
     */
    private function recomputeLiveResults(RegattaEntry $entry): void
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
