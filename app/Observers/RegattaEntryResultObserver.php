<?php

namespace App\Observers;

use App\Enums\RegattaStatus;
use App\Enums\SportCategory;
use App\Filament\Resources\RegattaResults\RegattaResultResource;
use App\Models\RegattaEntry;
use App\Models\RegattaResult;
use App\Models\RegattaResultItem;
use Illuminate\Database\Eloquent\Builder;

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

    /**
     * До удаления — пока дочерние записи заявки ещё на месте.
     *
     * Экипаж (regatta_entry_crew) удаляется по FK ON DELETE CASCADE вместе с
     * заявкой, поэтому снимок состава и результатов гонок нужно снять здесь, в
     * `deleting`, а не в `deleted` (там дети уже вычищены базой).
     */
    public function deleting(RegattaEntry $entry): void
    {
        if ($this->isArchived($entry)) {
            $this->freezeArchivedResults($entry);
        }
    }

    /**
     * После удаления — для «живых» регат пересчитываем оставшихся участников
     * (заявка уже исключена, места и очки за буквенные статусы сдвигаются).
     */
    public function deleted(RegattaEntry $entry): void
    {
        if (! $this->isArchived($entry)) {
            $this->recomputeLiveResults($entry);
        }
    }

    /**
     * Сужает выборку строк протокола до строк удаляемой заявки.
     *
     * У командной заявки признак — команда (плюс яхта, если она известна).
     * У сборной и индивидуальной команды нет, и фильтр по `team_id` превратился
     * бы в `IS NULL`, зацепив чужие строки: и другие индивидуальные заявки,
     * и уже «замороженные» строки удалённых команд. Поэтому такие заявки
     * ищутся по прямой ссылке `regatta_entry_id`.
     */
    private function scopeToEntry(Builder $query, RegattaEntry $entry): Builder
    {
        if ($entry->team_id === null) {
            return $query->where('regatta_entry_id', $entry->id);
        }

        return $query
            ->where('team_id', $entry->team_id)
            ->when(
                filled($entry->yacht_id),
                fn (Builder $q): Builder => $q->where('yacht_id', $entry->yacht_id),
            );
    }

    /** Считается ли регата архивной (итоги историчны и не пересчитываются). */
    private function isArchived(RegattaEntry $entry): bool
    {
        $status = $entry->regatta?->regatta_status;

        return $status !== null && in_array($status, self::ARCHIVED_STATUSES, true);
    }

    /**
     * Вариант B: сохраняем итоговые строки, отвязывая их от удаляемой команды.
     */
    private function freezeArchivedResults(RegattaEntry $entry): void
    {
        $resultIds = RegattaResult::query()
            ->where('regatta_id', $entry->regatta_id)
            ->pluck('id');

        $entry->loadMissing('crew.teamMember.user', 'crew.user', 'raceResults', 'regatta.races');

        $crewSnapshot = $this->buildCrewSnapshot($entry);
        $raceBreakdown = $this->buildRaceBreakdown($entry);
        $captainName = collect($crewSnapshot)
            ->firstWhere('role', 'captain')['name'] ?? null;

        RegattaResultItem::query()
            ->whereIn('regatta_result_id', $resultIds)
            ->tap(fn ($q) => $this->scopeToEntry($q, $entry))
            ->get()
            ->each(function (RegattaResultItem $item) use ($captainName, $crewSnapshot, $raceBreakdown): void {
                $item->forceFill([
                    // Перезаписываем снимок актуальными значениями на момент заморозки.
                    'team_name' => $item->team?->name ?? $item->team_name,
                    'yacht_name' => $item->yacht?->name ?? $item->yacht_name,
                    'sail_number' => $item->yacht?->vfps_number ?? $item->sail_number,
                    'captain_name' => $captainName ?? $item->captain_name,
                    'crew_snapshot' => $crewSnapshot ?: $item->crew_snapshot,
                    'race_breakdown' => $raceBreakdown ?: $item->race_breakdown,
                    // Отвязываем от удаляемых сущностей — итоговые очки и место остаются.
                    'team_id' => null,
                    'yacht_id' => null,
                ])->saveQuietly();
            });

        // Результаты гонок привязаны к удаляемой заявке и уже недостижимы —
        // убираем, чтобы не осиротели (разбивку хранит race_breakdown строки).
        $entry->raceResults()->delete();
    }

    /**
     * Снимок состава экипажа заявки. Формат совпадает с
     * RegattaResults::buildCrewMap: капитан сверху, остальные по алфавиту.
     *
     * @return array<int, array{id: ?string, name: string, birthday: string, rank: string, avatar: ?string, role: ?string}>
     */
    private function buildCrewSnapshot(RegattaEntry $entry): array
    {
        $crew = $entry->crew
            ->map(function ($c): array {
                // Участник сборного экипажа приходит без team_member — он привязан
                // к пользователю напрямую либо описан контактами (гость).
                $user = $c->teamMember?->user ?? $c->user;

                return [
                    'id' => $user?->id,
                    'name' => $user?->name ?? $c->full_name ?? '',
                    'birthday' => $user?->birth_date?->format('d.m.Y') ?? '—',
                    'rank' => SportCategory::labelOrNone($user?->sport_category),
                    'avatar' => $user?->photo_url ? asset('storage/'.$user->photo_url) : null,
                    'role' => $c->role,
                ];
            })
            ->sort(function (array $a, array $b): int {
                $aCaptain = ($a['role'] ?? null) === 'captain';
                $bCaptain = ($b['role'] ?? null) === 'captain';

                if ($aCaptain !== $bCaptain) {
                    return $aCaptain ? -1 : 1;
                }

                return strcoll($a['name'], $b['name']);
            })
            ->values()
            ->all();

        return $crew;
    }

    /**
     * Снимок результатов по гонкам заявки. Формат совпадает с
     * RegattaResults::buildRacesMap. Возвращает пустой массив, если у заявки
     * нет ни одного результата гонки (модалку показывать не за что).
     *
     * @return array<int, array{num: int, name: string, pos: string, pts: float|string|null, discarded: bool}>
     */
    private function buildRaceBreakdown(RegattaEntry $entry): array
    {
        $races = $entry->regatta?->races ?? collect();

        if ($races->isEmpty()) {
            return [];
        }

        $resultsByEvent = $entry->raceResults->keyBy('event_id');

        $breakdown = [];
        $hasAny = false;

        foreach ($races->values() as $i => $event) {
            $rr = $resultsByEvent->get($event->id);

            if ($rr) {
                $hasAny = true;
            }

            if ($rr && $rr->penalty_code) {
                $pos = mb_strtoupper($rr->penalty_code);
            } else {
                $pos = $rr && $rr->position !== null ? (string) $rr->position : '—';
            }

            $breakdown[] = [
                'num' => $i + 1,
                'name' => $event->name ?: ('Гонка '.($i + 1)),
                'pos' => $pos,
                'pts' => $rr && $rr->points !== null ? $rr->points : null,
                'discarded' => $rr ? $rr->isDiscarded() : false,
            ];
        }

        return $hasAny ? $breakdown : [];
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

        // Убираем строки выбывшего участника из всех результатов регаты.
        RegattaResultItem::query()
            ->whereIn('regatta_result_id', $results->modelKeys())
            ->tap(fn ($q) => $this->scopeToEntry($q, $entry))
            ->delete();

        $results->each(function (RegattaResult $result): void {
            RegattaResultResource::recomputeRacePoints($result);
            RegattaResultResource::recomputeItemTotals($result);
            RegattaResultResource::recalculateRatings($result);
        });
    }
}
