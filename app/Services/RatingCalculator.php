<?php

namespace App\Services;

use App\Models\Regatta;
use App\Models\RegattaResultItem;
use App\Models\Season;
use App\Models\TeamRating;
use Illuminate\Support\Facades\DB;

/**
 * Пересчитывает рейтинг команд и участников после публикации результатов регаты.
 *
 * Алгоритм:
 *   1. Берём все RegattaResultItem сезона вместе с коэффициентом каждой регаты.
 *   2. За каждый результат начисляем очки по формуле:
 *      (участвовало команд + 1 − место в регате) × коэффициент регаты.
 *   3. Суммируем очки по каждой команде.
 *   4. Записываем/обновляем строки в таблице ratings.
 *   5. Проставляем rank_position по убыванию total_points.
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
        // Берём все позиции участников сезона вместе с коэффициентом регаты.
        // цепочка: regatta_result_items → regatta_results → regattas
        $items = RegattaResultItem::query()
            ->join('regatta_results', 'regatta_results.id', '=', 'regatta_result_items.regatta_result_id')
            ->join('regattas', 'regattas.id', '=', 'regatta_results.regatta_id')
            ->where('regattas.season_id', $season->id)
            ->get([
                'regatta_result_items.team_id',
                'regatta_result_items.regatta_result_id',
                'regatta_result_items.final_position',
                'regattas.level_coefficient',
            ]);

        // Количество участвовавших команд в каждой регате — считаем только
        // строки с числовым местом (реально стартовавшие команды).
        $participantsByResult = $items
            ->filter(fn ($item) => is_numeric($item->final_position))
            ->groupBy('regatta_result_id')
            ->map->count();

        // Очки по формуле: (участвовало команд + 1 − место) × коэффициент регаты.
        $points = [];
        foreach ($items as $item) {
            if (! is_numeric($item->final_position)) {
                continue;
            }

            $participants = $participantsByResult[$item->regatta_result_id] ?? 0;
            $position     = (int) $item->final_position;

            $score = ($participants + 1 - $position) * (float) $item->level_coefficient;

            $points[$item->team_id] = ($points[$item->team_id] ?? 0) + $score;
        }

        arsort($points);

        // Обновляем / создаём записи рейтинга
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
}
