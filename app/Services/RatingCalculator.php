<?php

namespace App\Services;

use App\Enums\PenaltyCode;
use App\Models\PersonalRating;
use App\Models\Regatta;
use App\Models\RegattaEntry;
use App\Models\RegattaResultItem;
use App\Models\Season;
use App\Models\Series;
use App\Models\Team;
use App\Models\TeamRating;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Пересчитывает командный и личный рейтинг после публикации результатов регаты.
 *
 * Алгоритм:
 *   1. За каждый результат регаты считаем очки по формуле:
 *      (участвовало команд + 1 − место в регате) × коэффициент регаты.
 *   2. Командный рейтинг — суммируем очки по каждой команде.
 *   3. Личный рейтинг — те же очки регаты начисляем каждому участнику
 *      экипажа команды (captain / main / reserve) и суммируем по участникам.
 *   4. Проставляем rank_position по убыванию total_points.
 *
 * Регата любого типа идёт в зачёт по своему `level_coefficient`; регата вне
 * зачёта — это коэффициент 0, при котором формула даёт всем ноль очков.
 * У сборных и индивидуальных экипажей регулярных и выездных регат команды нет,
 * поэтому личные очки начисляются по составу заявки (@see crewMaps()).
 */
class RatingCalculator
{
    public function recalculateForSeason(Season $season): void
    {
        DB::transaction(function () use ($season) {
            // Очки за каждый результат регаты считаем один раз — они общие
            // для командного и личного рейтинга.
            $scoredItems = $this->scoredResultItems($season);

            $this->recalculateTeamRatings($season, $scoredItems);
            $this->recalculatePersonalRatings($season, $scoredItems);
        });
    }

    public function recalculateAfterRegatta(Regatta $regatta): void
    {
        $this->recalculateForSeason($regatta->season);
    }

    /**
     * Разбивка очков личного рейтинга по регатам для каждого участника сезона:
     *   [user_id => [ ['name' => ..., 'date' => ..., 'points' => float], ... ]].
     * Очки регаты начисляются каждому участнику экипажа — так же, как
     * в recalculatePersonalRatings(). Используется для всплывающего окна.
     */
    public function personalRegattaBreakdown(Season $season): array
    {
        $scoredItems = $this->scoredResultItems($season);
        $crewMaps = $this->crewMaps($season);

        // [user_id][regatta_id] => ['name' => ..., 'date' => ..., 'points' => ...]
        $breakdown = [];
        foreach ($scoredItems as $item) {
            $userIds = $this->crewUserIdsFor($crewMaps, $item);

            foreach ($userIds as $userId) {
                if (! isset($breakdown[$userId][$item->regatta_id])) {
                    $breakdown[$userId][$item->regatta_id] = [
                        'name' => $item->regatta_name,
                        'date' => $item->regatta_date,
                        'points' => 0.0,
                    ];
                }

                $breakdown[$userId][$item->regatta_id]['points'] += $item->score;
            }
        }

        return $this->formatBreakdown($breakdown);
    }

    /**
     * Разбивка очков командного рейтинга по регатам для каждой команды сезона:
     *   [team_id => [ ['name' => ..., 'date' => ..., 'points' => float], ... ]].
     * Используется для всплывающего окна.
     */
    public function teamRegattaBreakdown(Season $season): array
    {
        // [team_id][regatta_id] => ['name' => ..., 'date' => ..., 'points' => ...]
        $breakdown = [];
        foreach ($this->scoredResultItems($season) as $item) {
            if (! isset($breakdown[$item->team_id][$item->regatta_id])) {
                $breakdown[$item->team_id][$item->regatta_id] = [
                    'name' => $item->regatta_name,
                    'date' => $item->regatta_date,
                    'points' => 0.0,
                ];
            }

            $breakdown[$item->team_id][$item->regatta_id]['points'] += $item->score;
        }

        return $this->formatBreakdown($breakdown);
    }

    /**
     * Командные результаты серии — итоговая турнирная таблица по всем регатам
     * серии. Очки за регату считаются по той же формуле, что и в рейтинге:
     * (участвовало команд + 1 − место) × коэффициент регаты.
     *
     * @return array{
     *     regattas: array<int, array{id:string, external_id:int, name:string, date:?string}>,
     *     standings: array<int, array{rank:int, name:string, points:array<string,?float>, places:array<string,?int>, races:array<string,array>, total:float}>
     * }
     */
    public function seriesTeamStandings(Series $series): array
    {
        $items = RegattaResultItem::query()
            ->join('regatta_results', 'regatta_results.id', '=', 'regatta_result_items.regatta_result_id')
            ->join('regattas', 'regattas.id', '=', 'regatta_results.regatta_id')
            ->where('regattas.series_id', $series->id)
            ->whereNull('regattas.deleted_at')
            ->get([
                'regatta_result_items.team_id',
                'regatta_result_items.regatta_result_id',
                'regatta_result_items.final_position',
                'regattas.id as regatta_id',
                'regattas.name as regatta_name',
                'regattas.external_id as regatta_external_id',
                'regattas.date_start as regatta_date',
                'regattas.level_coefficient',
            ])
            // Только реально стартовавшие команды (числовое место).
            ->filter(fn ($item) => is_numeric($item->final_position))
            ->values();

        // Колонки таблицы — регаты серии, отсортированные по дате старта.
        $regattas = $items
            ->unique('regatta_id')
            ->sortBy('regatta_date')
            ->map(fn ($item) => [
                'id' => $item->regatta_id,
                'external_id' => $item->regatta_external_id,
                'name' => $item->regatta_name,
                'date' => $item->regatta_date ? Carbon::parse($item->regatta_date)->format('d.m.Y') : null,
            ])
            ->values()
            ->all();

        // Количество участвовавших команд в каждой регате.
        $participantsByResult = $items->groupBy('regatta_result_id')->map->count();

        // Строки удалённых команд (team_id обнулён) считаются в размере флота,
        // но в зачёт серии не попадают — начислять очки уже некому.
        $teamNames = Team::whereIn('id', $items->pluck('team_id')->unique()->filter())->pluck('name', 'id');

        // Результаты по отдельным гонкам — для детализации в карточке команды.
        // Логика повторяет GenerateRegattaResultPdfAction: гонки регаты нумеруются
        // по порядку старта, результат команды в гонке достаётся через её заявку.
        $teamIds = $items->pluck('team_id')->unique()->filter()->values();
        $regattaIds = collect($regattas)->pluck('id');

        $racesByRegatta = Regatta::query()
            ->whereIn('id', $regattaIds)
            ->with(['races' => fn ($q) => $q->orderBy('event_datetime')])
            ->get()
            ->keyBy('id');

        $entriesByRegatta = RegattaEntry::query()
            ->whereIn('regatta_id', $regattaIds)
            ->whereIn('team_id', $teamIds)
            ->with('raceResults')
            ->orderByRaw("status = 'approved' ASC")
            ->get()
            ->groupBy('regatta_id');

        $raceBreakdown = [];
        foreach ($regattaIds as $regattaId) {
            $raceEvents = $racesByRegatta->get($regattaId)?->races ?? collect();
            if ($raceEvents->isEmpty()) {
                continue;
            }

            $entriesByTeam = $entriesByRegatta->get($regattaId, collect())->keyBy('team_id');

            foreach ($teamIds as $teamId) {
                $entry = $entriesByTeam->get($teamId);
                if (! $entry) {
                    continue;
                }

                $raceResultsByEvent = $entry->raceResults->keyBy('event_id');

                $raceBreakdown[$teamId][$regattaId] = $raceEvents->values()
                    ->map(function ($event, $i) use ($raceResultsByEvent) {
                        $result = $raceResultsByEvent->get($event->id);

                        return [
                            'number' => $i + 1,
                            'name' => $event->name,
                            'date' => $event->event_datetime?->format('d.m.Y H:i'),
                            'position' => $result?->position,
                            'points' => $result?->points,
                            'penalty_code' => $result?->penalty_code,
                            // Расшифровка кода (DNF/DNS/OCS…) для публичной страницы —
                            // сырой код без неё непонятен обычному пользователю.
                            'penalty_label' => $result?->penalty_code
                                ? PenaltyCode::from($result->penalty_code)->label()
                                : null,
                        ];
                    })
                    ->all();
            }
        }

        // Суммируем очки по командам, сохраняя разбивку по регатам.
        $byTeam = [];
        foreach ($items as $item) {
            if ($item->team_id === null) {
                continue;
            }

            $participants = $participantsByResult[$item->regatta_result_id] ?? 0;
            $score = ($participants + 1 - (int) $item->final_position) * (float) $item->level_coefficient;

            $teamId = $item->team_id;
            $byTeam[$teamId] ??= [
                'team_id' => $teamId,
                'name' => $teamNames[$teamId] ?? '—',
                'points' => [],
                'places' => [],
                'total' => 0.0,
            ];
            $byTeam[$teamId]['points'][$item->regatta_id] = ($byTeam[$teamId]['points'][$item->regatta_id] ?? 0) + $score;
            $byTeam[$teamId]['places'][$item->regatta_id] = (int) $item->final_position;
            $byTeam[$teamId]['total'] += $score;
        }

        $standings = collect($byTeam)
            ->sortByDesc('total')
            ->values()
            ->map(fn ($row, $i) => [
                'rank' => $i + 1,
                'team_id' => $row['team_id'],
                'name' => $row['name'],
                'points' => collect($regattas)
                    ->mapWithKeys(fn ($r) => [
                        $r['id'] => isset($row['points'][$r['id']]) ? round($row['points'][$r['id']], 3) : null,
                    ])
                    ->all(),
                'places' => collect($regattas)
                    ->mapWithKeys(fn ($r) => [
                        $r['id'] => $row['places'][$r['id']] ?? null,
                    ])
                    ->all(),
                'races' => collect($regattas)
                    ->mapWithKeys(fn ($r) => [
                        $r['id'] => $raceBreakdown[$row['team_id']][$r['id']] ?? [],
                    ])
                    ->all(),
                'total' => round($row['total'], 3),
            ])
            ->all();

        return [
            'regattas' => $regattas,
            'standings' => $standings,
        ];
    }

    // ──────────────────────────────────────────────
    // Private

    /**
     * Приводит карту [entity_id][regatta_id => данные] к списку регат,
     * отсортированному по дате (новые сверху), с форматированием даты и очков.
     */
    private function formatBreakdown(array $breakdown): array
    {
        return array_map(
            fn ($regattas) => collect($regattas)
                ->sortByDesc('date')
                ->map(fn ($r) => [
                    'name' => $r['name'],
                    'date' => $r['date'] ? Carbon::parse($r['date'])->format('d.m.Y') : null,
                    'points' => round($r['points'], 3),
                ])
                ->values()
                ->all(),
            $breakdown
        );
    }
    // ──────────────────────────────────────────────

    /**
     * Результаты регат сезона с числовым местом, к каждому добавлено поле
     * `score` — очки по формуле (участвовало команд + 1 − место) × коэффициент.
     *
     * Строки удалённых команд (team_id обнулён, см. миграцию
     * add_snapshot_to_regatta_result_items) участвуют в подсчёте размера флота,
     * но из выборки исключаются: начислять очки уже некому. Строка без команды,
     * но со ссылкой на заявку — другое дело: это сборный или индивидуальный
     * экипаж, и личные очки его участникам начисляются.
     */
    private function scoredResultItems(Season $season): Collection
    {
        // цепочка: regatta_result_items → regatta_results → regattas
        $items = RegattaResultItem::query()
            ->join('regatta_results', 'regatta_results.id', '=', 'regatta_result_items.regatta_result_id')
            ->join('regattas', 'regattas.id', '=', 'regatta_results.regatta_id')
            ->where('regattas.season_id', $season->id)
            ->get([
                'regatta_result_items.team_id',
                'regatta_result_items.regatta_entry_id',
                'regatta_result_items.regatta_result_id',
                'regatta_result_items.final_position',
                'regattas.id as regatta_id',
                'regattas.name as regatta_name',
                'regattas.date_start as regatta_date',
                'regattas.level_coefficient',
            ]);

        // Количество участвовавших команд в каждой регате — считаем только
        // строки с числовым местом (реально стартовавшие команды).
        $participantsByResult = $items
            ->filter(fn ($item) => is_numeric($item->final_position))
            ->groupBy('regatta_result_id')
            ->map->count();

        return $items
            ->filter(fn ($item) => is_numeric($item->final_position)
                && ($item->team_id !== null || $item->regatta_entry_id !== null))
            ->map(function ($item) use ($participantsByResult) {
                $participants = $participantsByResult[$item->regatta_result_id] ?? 0;

                $item->score = ($participants + 1 - (int) $item->final_position)
                    * (float) $item->level_coefficient;

                return $item;
            })
            ->values();
    }

    private function recalculateTeamRatings(Season $season, Collection $scoredItems): void
    {
        $points = [];
        foreach ($scoredItems as $item) {
            // Сборные и индивидуальные экипажи идут только в личный рейтинг:
            // команды, которой начислять очки, у них нет.
            if ($item->team_id === null) {
                continue;
            }

            $points[$item->team_id] = ($points[$item->team_id] ?? 0) + $item->score;
        }

        $ranked = $this->rankByPoints($points);

        foreach ($ranked as $teamId => $row) {
            TeamRating::updateOrCreate(
                [
                    'season_id' => $season->id,
                    'team_id' => $teamId,
                ],
                [
                    'total_points' => $row['total'],
                    'rank_position' => $row['rank'],
                ]
            );
        }

        // Команды, потерявшие очки (результаты или заявки удалены), иначе
        // остались бы в таблице со старым местом и ломали нумерацию.
        TeamRating::where('season_id', $season->id)
            ->whereNotIn('team_id', array_keys($ranked))
            ->delete();
    }

    private function recalculatePersonalRatings(Season $season, Collection $scoredItems): void
    {
        $crewMaps = $this->crewMaps($season);

        // Очки команды за регату начисляем каждому участнику её экипажа.
        $points = [];
        foreach ($scoredItems as $item) {
            $userIds = $this->crewUserIdsFor($crewMaps, $item);

            foreach ($userIds as $userId) {
                $points[$userId] = ($points[$userId] ?? 0) + $item->score;
            }
        }

        $ranked = $this->rankByPoints($points);

        foreach ($ranked as $userId => $row) {
            PersonalRating::updateOrCreate(
                [
                    'season_id' => $season->id,
                    'user_id' => $userId,
                ],
                [
                    'total_points' => $row['total'],
                    'rank_position' => $row['rank'],
                ]
            );
        }

        // Участники, потерявшие очки (исключены из экипажа, результат удалён),
        // иначе остались бы в таблице со старым местом и ломали нумерацию.
        PersonalRating::where('season_id', $season->id)
            ->whereNotIn('user_id', array_keys($ranked))
            ->delete();
    }

    /**
     * Проставляет места по убыванию очков. Участники с одинаковыми очками
     * (с точностью до 3 знаков, как очки хранятся в БД) получают одно и то же
     * место, а места идут подряд без пропусков — принцип «1-1-1-2-3»
     * (плотное ранжирование).
     *
     * @param  array<array-key, float|int>  $points  entity_id => total_points
     * @return array<array-key, array{total: float|int, rank: int}>
     */
    private function rankByPoints(array $points): array
    {
        arsort($points);

        $ranked = [];
        $rank = 0;
        $prev = null;
        foreach ($points as $id => $total) {
            if ($prev === null || round((float) $total, 3) < round((float) $prev, 3)) {
                $rank++;
            }

            $ranked[$id] = ['total' => $total, 'rank' => $rank];
            $prev = $total;
        }

        return $ranked;
    }

    /**
     * Состав экипажей сезона в двух разрезах.
     *
     * `byTeam` — исторический путь: заявка команды, участники приходят из
     * `team_members`. `byEntry` — сборные и индивидуальные экипажи регулярных
     * и выездных регат: команды нет, человек привязан к строке экипажа напрямую.
     *
     * `byTeam` объединяет несколько заявок одной команды на регату и исключает
     * дубли участников.
     *
     * @return array{
     *     byTeam: array<string, array<string, array<string, string>>>,
     *     byEntry: array<string, array<string, string>>
     * }
     */
    private function crewMaps(Season $season): array
    {
        $entries = RegattaEntry::query()
            ->whereHas('regatta', fn ($q) => $q->where('season_id', $season->id))
            ->with('crew.teamMember:id,user_id')
            ->get(['id', 'regatta_id', 'team_id']);

        $byTeam = [];
        $byEntry = [];

        foreach ($entries as $entry) {
            foreach ($entry->crew as $crewMember) {
                $userId = $crewMember->resolvedUserId();

                if ($userId === null) {
                    continue;
                }

                $byEntry[$entry->id][$userId] = $userId;

                if ($entry->team_id !== null) {
                    $byTeam[$entry->regatta_id][$entry->team_id][$userId] = $userId;
                }
            }
        }

        return ['byTeam' => $byTeam, 'byEntry' => $byEntry];
    }

    /**
     * Участники, которым идут очки строки протокола.
     *
     * Прямая ссылка на заявку точнее команды: она есть и у сборных экипажей,
     * и у заявок, где на одну команду в регате пришлось несколько лодок.
     *
     * @param  array{byTeam: array<string, array<string, array<string, string>>>, byEntry: array<string, array<string, string>>}  $maps
     * @return array<string, string>
     */
    private function crewUserIdsFor(array $maps, object $item): array
    {
        if ($item->regatta_entry_id !== null && isset($maps['byEntry'][$item->regatta_entry_id])) {
            return $maps['byEntry'][$item->regatta_entry_id];
        }

        if ($item->team_id === null) {
            return [];
        }

        return $maps['byTeam'][$item->regatta_id][$item->team_id] ?? [];
    }
}
