<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Season;

final class HomeResultsService
{
    public const MODE_LATEST_REGATTA = 'latest_regatta';

    public const MODE_TEAM_RATING = 'team_rating';

    public const MODE_HIDDEN = 'hidden';

    public function mode(): string
    {
        $mode = app(SettingsService::class)->get('home.results_mode', self::MODE_LATEST_REGATTA);

        return in_array($mode, [self::MODE_LATEST_REGATTA, self::MODE_TEAM_RATING, self::MODE_HIDDEN], true)
            ? $mode
            : self::MODE_LATEST_REGATTA;
    }

    /**
     * Данные последнего сезона с командным рейтингом.
     *
     * @return array{season: Season|null, ratings: array}
     */
    public function teamRatingData(): array
    {
        $season = Season::query()
            ->whereHas('teamRatings')
            ->where('year', '<=', now()->year)
            ->orderByDesc('year')
            ->first();

        $ratings = app(TeamRatingViewData::class)->forSeason($season);

        return compact('season', 'ratings');
    }
}
