<?php

namespace App\Services;

use App\Enums\PenaltyCode;
use App\Models\RaceResult;
use App\Models\RegattaEntry;
use App\Models\RegattaEvents;
use App\Models\RegattaResult;
use App\Models\RegattaResultItem;
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
 * Очки в RegattaResult/RegattaResultItem обновляются автоматически после каждого сохранения.
 */
class RaceScorer
{
    /**
     * Сохранить финиш гонки.
     *
     * @param RegattaEvents $race
     * @param array $finishes  [['entry_id' => uuid, 'position' => int, 'penalty_code' => string|null], ...]
     */
    public function recordFinishes(RegattaEvents $race, array $finishes): void
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
                        'event_id'         => $race->id,
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
     * Пересчитать RegattaResult + RegattaResultItem для всех команд регаты.
     */
    public function updateRegattaResults(RegattaEvents $race): void
    {
        $regatta = $race->regatta;

        $sums = RaceResult::query()
            ->join('regatta_events', 'regatta_events.id', '=', 'race_results.event_id')
            ->join('regatta_entries', 'regatta_entries.id', '=', 'race_results.regatta_entry_id')
            ->where('regatta_events.regatta_id', $regatta->id)
            ->selectRaw('
                regatta_entries.team_id,
                regatta_entries.yacht_id,
                SUM(race_results.points) AS total_points
            ')
            ->groupBy('regatta_entries.team_id', 'regatta_entries.yacht_id')
            ->orderBy('total_points')
            ->get();

        // Создаём/обновляем заголовок результата (один на регату, manual source)
        $result = RegattaResult::updateOrCreate(
            ['regatta_id' => $regatta->id],
            ['result_type' => 'preliminary', 'source' => 'manual']
        );

        $position = 1;
        foreach ($sums as $row) {
            RegattaResultItem::updateOrCreate(
                [
                    'regatta_result_id' => $result->id,
                    'team_id'           => $row->team_id,
                ],
                [
                    'yacht_id'       => $row->yacht_id,
                    'total_points'   => $row->total_points,
                    'final_position' => $position++,
                ]
            );
        }
    }
}
