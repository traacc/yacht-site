<?php

namespace App\Services;

use App\Models\Regatta;
use App\Models\RegattaEntry;
use App\Models\RegattaResultItem;
use App\Models\Series;
use App\Support\Points;
use Illuminate\Support\Collection;

/**
 * Данные публичной страницы «Серии» (раздел «Соревнования»).
 *
 * Для каждой серии отдаёт описание (как в подразделе «Календарь») и результаты
 * по этапам: отдельная таблица на каждую регату серии с её коэффициентом.
 * Строки таблицы этапа упорядочены по месту команды в рейтинге серии
 * (сумма очков всех этапов с учётом коэффициента), а не по месту на этапе.
 */
class SeriesStageResultsService
{
    public function __construct(private readonly RatingCalculator $calculator) {}

    /**
     * Все серии с регатами: описание серии + таблицы результатов по этапам.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function overview(): Collection
    {
        $series = Series::query()
            ->whereHas('regattas')
            ->with([
                'season',
                'regattas' => fn ($q) => $q->orderBy('date_start')->orderBy('time_start'),
                'regattas.results' => fn ($q) => $q->where('is_published', true),
                'regattas.results.items' => fn ($q) => $q->reorder()
                    ->orderByRaw('final_position IS NULL')
                    ->orderByRaw('CAST(final_position AS UNSIGNED)')
                    ->orderBy('final_position'),
                'regattas.results.items.team',
                'regattas.results.items.yacht',
            ])
            ->get()
            ->sortByDesc(fn (Series $s) => $s->season?->year)
            ->values();

        $captains = $this->captains($series);

        return $series->map(fn (Series $s) => $this->present($s, $captains));
    }

    /**
     * Одна серия: описание + таблицы результатов по этапам.
     *
     * @return array<string, mixed>
     */
    private function present(Series $series, array $captains): array
    {
        // Рейтинг серии считаем тем же калькулятором, что и «Результаты серий»
        // в разделе «Рейтинги», — очки этапа уже учитывают коэффициент регаты.
        $standings = collect($this->calculator->seriesTeamStandings($series)['standings'])
            ->keyBy('team_id');

        $stages = $series->regattas
            ->values()
            ->map(fn (Regatta $regatta, int $i) => [
                'regatta' => $regatta,
                'number' => $i + 1,
                'coefficient' => $regatta->level_coefficient !== null
                    ? number_format((float) $regatta->level_coefficient, 2, ',', ' ')
                    : null,
                'published' => $regatta->results->isNotEmpty(),
                'rows' => $this->stageRows($regatta, $standings, $captains),
            ]);

        return [
            'model' => $series,
            'name' => $series->name,
            'description' => $series->description,
            'season' => $series->season?->year,
            'url' => route('series-details', $series),
            'stages' => $stages,
        ];
    }

    /**
     * Строки таблицы одного этапа, отсортированные по рейтингу серии.
     *
     * @param  Collection<string, array<string, mixed>>  $standings  зачёт серии по team_id
     * @param  array<string, array{id: ?string, name: ?string}>  $captains
     * @return Collection<int, array<string, mixed>>
     */
    private function stageRows(Regatta $regatta, Collection $standings, array $captains): Collection
    {
        return $regatta->results
            ->flatMap->items
            ->map(function (RegattaResultItem $item) use ($regatta, $standings, $captains) {
                $standing = $item->team_id ? $standings->get($item->team_id) : null;
                $captain = $captains[$regatta->id.':'.$item->team_id] ?? null;
                $seriesPoints = $standing['points'][$regatta->id] ?? null;

                return [
                    'series_rank' => $standing['rank'] ?? null,
                    'series_total' => isset($standing['total']) ? Points::format($standing['total']) : null,
                    // Очки, которые этап дал команде в зачёт серии (с коэффициентом).
                    'series_points' => $seriesPoints !== null ? Points::format($seriesPoints) : null,
                    'place' => $item->final_position,
                    'team_name' => $item->displayTeamName ?: '—',
                    'yacht' => $item->displayYachtName ?: '—',
                    'sail_number' => $item->displaySailNumber ?: '—',
                    'captain_id' => $captain['id'] ?? null,
                    'captain_name' => $captain['name'] ?: $item->captain_name,
                    'points' => $item->displayTotalPoints,
                ];
            })
            // Ключ сортировки: место в рейтинге серии, затем место на этапе.
            // Команды вне зачёта серии (без числового места) уходят вниз.
            ->sortBy(fn (array $row) => sprintf(
                '%06d%06d',
                $row['series_rank'] ?? 999999,
                is_numeric($row['place']) ? (int) $row['place'] : 999999,
            ))
            ->values();
    }

    /**
     * Рулевые этапов: ключ «regatta_id:team_id» → id и имя пользователя.
     * Берём из одобренной заявки команды; если её нет — в строке результата
     * останется денормализованный `captain_name`.
     *
     * @param  Collection<int, Series>  $series
     * @return array<string, array{id: ?string, name: ?string}>
     */
    private function captains(Collection $series): array
    {
        $regattaIds = $series->flatMap->regattas->pluck('id');

        if ($regattaIds->isEmpty()) {
            return [];
        }

        return RegattaEntry::query()
            ->whereIn('regatta_id', $regattaIds)
            ->where('status', 'approved')
            ->with([
                'crew' => fn ($q) => $q->where('role', 'captain'),
                'crew.teamMember.user',
            ])
            ->get()
            ->mapWithKeys(function (RegattaEntry $entry) {
                $user = $entry->crew->first()?->teamMember?->user;

                return [$entry->regatta_id.':'.$entry->team_id => [
                    'id' => $user?->id,
                    'name' => $user?->name,
                ]];
            })
            ->filter(fn (array $captain) => $captain['name'] !== null)
            ->all();
    }
}
