<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SportCategory;
use App\Models\Season;
use App\Models\TeamRating;

final class TeamRatingViewData
{
    public function forSeason(?Season $season): array
    {
        if (! $season) {
            return [];
        }

        $breakdown = app(RatingCalculator::class)->teamRegattaBreakdown($season);

        return TeamRating::with(['team.activeMembers', 'team.organizer', 'team.defaultYacht'])
            ->forSeason($season)
            ->ranked()
            ->get()
            ->map(function (TeamRating $rating) use ($breakdown): array {
                $team = $rating->team;
                $yacht = $team?->defaultYacht;
                $captain = $team?->organizer;

                return [
                    'name' => $team?->name ?? '—',
                    'total_points' => (float) $rating->total_points,
                    'rank' => $rating->rank_position,
                    'captain' => $captain?->name ?? '—',
                    'captain_id' => $captain?->id,
                    'captain_avatar' => $captain?->photo_url ? asset('storage/'.$captain->photo_url) : null,
                    'yacht' => $yacht
                        ? trim($yacht->name.($yacht->vfps_number ? ' ('.$yacht->vfps_number.')' : ''))
                        : '—',
                    'regattas' => $breakdown[$rating->team_id] ?? [],
                    'members' => $team?->activeMembers
                        ?->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                        ->map(fn ($member) => [
                            'id' => $member->id,
                            'name' => $member->name,
                            'birthday' => $member->birth_date?->format('d.m.Y') ?? '—',
                            'category' => SportCategory::labelOrNone($member->sport_category),
                            'avatar' => $member->photo_url ? asset('storage/'.$member->photo_url) : null,
                        ])->values()->all() ?? [],
                ];
            })->values()->all();
    }
}
