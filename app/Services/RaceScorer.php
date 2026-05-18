<?php

namespace App\Services;

use App\Enums\PenaltyCode;
use App\Models\Race;
use App\Models\RaceResult;
use App\Models\RegattaEntry;
use Illuminate\Support\Facades\DB;

/**
 * Записывает и пересчитывает результаты гонки.
 *
 * Система очков: Low Point System (ISAF Racing Rules Appendix A).
 *   1-е место  = 1 очко
 *   2-е место  = 2 очка
 *   ...
 *   Штрафные коды (DNF, DNS, DSQ …) = число участников + 1
 *
 * Очки в RegattaResult обновляются автоматически после каждого сохранения.
 */
class RaceScorer
{
    /**
     * Сохранить финиш гонки.
     *
     * @param Race $race
     * @param array $finishes  [['entry_id' => uuid, 'position' => int, 'penalty_code' => string|null], ...]
     */
    public function recordFinishes(Race $race, array $finishes): void
    {
        DB::transaction(function () use ($race, $finishes) {
            $totalEntrants = count($finishes);

            foreach ($finishes as $finish) {
                $penaltyCode = $finish['penalty_code'] ?? null;
                $points = $penaltyCode
                    ? PenaltyCode::from($penaltyCode)->computePoints($totalEntrants)
                    : (float) $finish['position'];

                RaceResult::updateOrCreate(
                    [
                        'race_id'          => $race->id,
                        'regatta_entry_id' => $finish['entry_id'],
                    ],
                    [
                        'position'     => $penaltyCode ? null : $finish['position'],
                        'points'       => $points,
                        'penalty_code' => $penaltyCode,
                    ]
                );
            }

            // Обновить итоги регаты
            $this->updateRegattaResults($race);
        });
    }

    /**
     * Пересчитать RegattaResult для всех команд регаты (суммируем по гонкам).
     */
    public function updateRegattaResults(Race $race): void
    {
        $regatta = $race->regatta;

        $sums = RaceResult::query()
            ->join('races', 'races.id', '=', 'race_results.race_id')
            ->join('regatta_entries', 'regatta_entries.id', '=', 'race_results.regatta_entry_id')
            ->where('races.regatta_id', $regatta->id)
            ->selectRaw('
                regatta_entries.team_id,
                SUM(race_results.points) AS total_points
            ')
            ->groupBy('regatta_entries.team_id')
            ->orderBy('total_points')
            ->get();

        $position = 1;
        foreach ($sums as $row) {
            \App\Models\RegattaResult::updateOrCreate(
                ['regatta_id' => $regatta->id, 'team_id' => $row->team_id],
                ['total_points' => $row->total_points, 'final_position' => $position++]
            );
        }
    }
}
