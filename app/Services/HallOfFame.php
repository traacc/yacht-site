<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PersonalRating;
use App\Models\Season;
use App\Models\TeamRating;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * «Зал славы» раздела Carter 30: лидеры рейтингов каждого сезона.
 *
 * Берёт уже посчитанные места из `team_ratings` / `personal_ratings`
 * ({@see RatingCalculator}). Места там плотные (1-1-2-3), поэтому «ТОП-N» —
 * это места с 1-го по N-е: на одном месте может стоять несколько команд или
 * участников, и в карточке они идут списком под одним номером.
 */
class HallOfFame
{
    public const TEAM_PLACES = 5;

    public const PERSONAL_PLACES = 3;

    /**
     * Карточки сезонов, новые сверху. Сезон без рейтингов не показывается.
     *
     * @return Collection<int, array{
     *     year: int,
     *     in_progress: bool,
     *     teams: list<array{place: int, points: float, entries: list<array{id: ?string, name: string, image: ?string}>}>,
     *     personal: list<array{place: int, points: float, entries: list<array{id: ?string, name: string, image: ?string}>}>,
     * }>
     */
    public function seasons(): Collection
    {
        $seasons = Season::query()
            ->where(fn ($q) => $q->whereHas('teamRatings')->orWhereHas('personalRatings'))
            ->orderByDesc('year')
            ->get();

        // Команды и участники могли быть удалены в корзину после сезона —
        // в истории они всё равно остаются, но без ссылки на карточку.
        $teamRatings = TeamRating::query()
            ->whereIn('season_id', $seasons->modelKeys())
            ->where('rank_position', '<=', self::TEAM_PLACES)
            ->with(['team' => fn ($q) => $q->withTrashed()])
            ->ranked()
            ->get()
            ->groupBy('season_id');

        $personalRatings = PersonalRating::query()
            ->whereIn('season_id', $seasons->modelKeys())
            ->where('rank_position', '<=', self::PERSONAL_PLACES)
            ->with(['user' => fn ($q) => $q->withTrashed()])
            ->ranked()
            ->get()
            ->groupBy('season_id');

        return $seasons->map(fn (Season $season) => [
            'year' => $season->year,
            'in_progress' => $season->end_date === null || $season->end_date->endOfDay()->isFuture(),
            'teams' => $this->places($teamRatings->get($season->id, collect()), fn (TeamRating $r) => [
                'id' => $r->team?->trashed() ? null : $r->team?->id,
                'name' => $r->team?->name ?? '—',
                'image' => $r->team?->picture ? Storage::url($r->team->picture) : null,
            ]),
            'personal' => $this->places($personalRatings->get($season->id, collect()), fn (PersonalRating $r) => [
                'id' => $r->user?->trashed() ? null : $r->user?->id,
                'name' => $r->user?->name ?? '—',
                'image' => $r->user?->photo_url ? asset('storage/'.$r->user->photo_url) : null,
            ]),
        ])->values();
    }

    /**
     * Строки рейтинга, сгруппированные по месту. Очки на одном месте равны,
     * поэтому берутся у первой строки группы.
     */
    private function places(Collection $ratings, callable $entry): array
    {
        return $ratings
            ->groupBy('rank_position')
            ->map(fn (Collection $group, int $place) => [
                'place' => $place,
                'points' => (float) $group->first()->total_points,
                'entries' => $group
                    ->map($entry)
                    ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }
}
