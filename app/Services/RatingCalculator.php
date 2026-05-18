<?php

namespace App\Services;

use App\Models\Rating;
use App\Models\Regatta;
use App\Models\RegattaResult;
use App\Models\Season;
use Illuminate\Support\Facades\DB;

/**
 * Пересчитывает рейтинг команд и участников после публикации результатов регаты.
 *
 * Алгоритм:
 *   1. Берём все RegattaResult сезона с учётом level_coefficient каждой регаты.
 *   2. Суммируем взвешенные очки по каждой команде.
 *   3. Записываем/обновляем строки в таблице ratings.
 *   4. Проставляем rank_position по убыванию total_points.
 */
class RatingCalculator
{
    public function recalculateForSeason(Season $season): void
    {
        DB::transaction(function () use ($season) {
            $this->recalculateTeamRatings($season);
        });
    }

    public function recalculateAfterRegatta(Regatta $regatta): void
    {
        $this->recalculateForSeason($regatta->season);
    }

    // ──────────────────────────────────────────────
    // Private
    // ──────────────────────────────────────────────

    private function recalculateTeamRatings(Season $season): void
    {
        // Агрегируем очки: total_points * level_coefficient регаты
        $rows = RegattaResult::query()
            ->join('regattas', 'regattas.id', '=', 'regatta_results.regatta_id')
            ->where('regattas.season_id', $season->id)
            ->selectRaw('
                regatta_results.team_id,
                SUM(regatta_results.total_points * regattas.level_coefficient) AS weighted_points
            ')
            ->groupBy('regatta_results.team_id')
            ->orderByDesc('weighted_points')
            ->get();

        // Обновляем / создаём записи рейтинга
        $rank = 1;
        foreach ($rows as $row) {
            Rating::updateOrCreate(
                [
                    'season_id'   => $season->id,
                    'team_id'     => $row->team_id,
                    'rating_type' => 'team',
                ],
                [
                    'total_points'  => $row->weighted_points,
                    'rank_position' => $rank++,
                    'user_id'       => null,
                ]
            );
        }
    }
}
