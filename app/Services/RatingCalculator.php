<?php

namespace App\Services;

use App\Models\PersonalRating;
use App\Models\Regatta;
use App\Models\RegattaEntry;
use App\Models\RegattaResultItem;
use App\Models\Season;
use App\Models\TeamRating;
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

    // ──────────────────────────────────────────────
    // Private
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
