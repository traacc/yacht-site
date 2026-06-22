<?php

namespace App\Services;

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
        $crewByRegattaTeam = $this->crewByRegattaTeam($season);

        // [user_id][regatta_id] => ['name' => ..., 'date' => ..., 'points' => ...]
        $breakdown = [];
        foreach ($scoredItems as $item) {
            $userIds = $crewByRegattaTeam[$item->regatta_id][$item->team_id] ?? [];

            foreach ($userIds as $userId) {
                if (! isset($breakdown[$userId][$item->regatta_id])) {
                    $breakdown[$userId][$item->regatta_id] = [
                        'name'   => $item->regatta_name,
                        'date'   => $item->regatta_date,
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
                    'name'   => $item->regatta_name,
                    'date'   => $item->regatta_date,
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
     *     standings: array<int, array{rank:int, name:string, points:array<string,?float>, total:float}>
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
                'id'          => $item->regatta_id,
                'external_id' => $item->regatta_external_id,
                'name'        => $item->regatta_name,
                'date'        => $item->regatta_date ? Carbon::parse($item->regatta_date)->format('d.m.Y') : null,
            ])
            ->values()
            ->all();

        // Количество участвовавших команд в каждой регате.
        $participantsByResult = $items->groupBy('regatta_result_id')->map->count();

        $teamNames = Team::whereIn('id', $items->pluck('team_id')->unique())->pluck('name', 'id');

        // Суммируем очки по командам, сохраняя разбивку по регатам.
        $byTeam = [];
        foreach ($items as $item) {
            $participants = $participantsByResult[$item->regatta_result_id] ?? 0;
            $score = ($participants + 1 - (int) $item->final_position) * (float) $item->level_coefficient;

            $teamId = $item->team_id;
            $byTeam[$teamId] ??= [
                'name'   => $teamNames[$teamId] ?? '—',
                'points' => [],
                'total'  => 0.0,
            ];
            $byTeam[$teamId]['points'][$item->regatta_id] = ($byTeam[$teamId]['points'][$item->regatta_id] ?? 0) + $score;
            $byTeam[$teamId]['total'] += $score;
        }

        $standings = collect($byTeam)
            ->sortByDesc('total')
            ->values()
            ->map(fn ($row, $i) => [
                'rank'   => $i + 1,
                'name'   => $row['name'],
                'points' => collect($regattas)
                    ->mapWithKeys(fn ($r) => [
                        $r['id'] => isset($row['points'][$r['id']]) ? round($row['points'][$r['id']], 3) : null,
                    ])
                    ->all(),
                'total'  => round($row['total'], 3),
            ])
            ->all();

        return [
            'regattas'  => $regattas,
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
                    'name'   => $r['name'],
                    'date'   => $r['date'] ? \Illuminate\Support\Carbon::parse($r['date'])->format('d.m.Y') : null,
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
            ->filter(fn ($item) => is_numeric($item->final_position))
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
            $points[$item->team_id] = ($points[$item->team_id] ?? 0) + $item->score;
        }

        arsort($points);

        $rank = 1;
        foreach ($points as $teamId => $total) {
            TeamRating::updateOrCreate(
                [
                    'season_id' => $season->id,
                    'team_id'   => $teamId,
                ],
                [
                    'total_points'  => $total,
                    'rank_position' => $rank++,
                ]
            );
        }
    }

    private function recalculatePersonalRatings(Season $season, Collection $scoredItems): void
    {
        // Состав экипажей сезона: regatta_id → team_id → [user_id => user_id]
        $crewByRegattaTeam = $this->crewByRegattaTeam($season);

        // Очки команды за регату начисляем каждому участнику её экипажа.
        $points = [];
        foreach ($scoredItems as $item) {
            $userIds = $crewByRegattaTeam[$item->regatta_id][$item->team_id] ?? [];

            foreach ($userIds as $userId) {
                $points[$userId] = ($points[$userId] ?? 0) + $item->score;
            }
        }

        arsort($points);

        $rank = 1;
        foreach ($points as $userId => $total) {
            PersonalRating::updateOrCreate(
                [
                    'season_id' => $season->id,
                    'user_id'   => $userId,
                ],
                [
                    'total_points'  => $total,
                    'rank_position' => $rank++,
                ]
            );
        }
    }

    /**
     * Карта экипажей сезона: [regatta_id][team_id] => [user_id => user_id].
     * Объединяет несколько заявок одной команды на регату, исключает дубли.
     */
    private function crewByRegattaTeam(Season $season): array
    {
        $entries = RegattaEntry::query()
            ->whereHas('regatta', fn ($q) => $q->where('season_id', $season->id))
            ->with('crew.teamMember:id,user_id')
            ->get(['id', 'regatta_id', 'team_id']);

        $map = [];
        foreach ($entries as $entry) {
            foreach ($entry->crew as $crewMember) {
                $userId = $crewMember->teamMember?->user_id;

                if ($userId !== null) {
                    $map[$entry->regatta_id][$entry->team_id][$userId] = $userId;
                }
            }
        }

        return $map;
    }
}
