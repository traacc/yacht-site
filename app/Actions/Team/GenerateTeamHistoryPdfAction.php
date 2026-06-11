<?php

namespace App\Actions\Team;

use App\Models\Team;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

final class GenerateTeamHistoryPdfAction
{
    /**
     * Сформировать PDF с историей команды: общая информация,
     * состав и данные по каждому участнику, участие в регатах.
     */
    public function execute(Team $team): Response
    {
        $team->load([
            'organizer',
            'activeMembers',
            'regattaEntries.regatta',
            'regattaEntries.yacht',
            'regattaResultItems.regattaResult',
            'ratings.season',
        ]);

        $rating = $team->ratings
            ->where('rating_type', 'team')
            ->sortByDesc(fn ($r) => $r->season?->year ?? 0)
            ->first()?->rank_position;

        $members = $team->activeMembers->map(fn ($member) => [
            'name' => $member->name ?? '—',
            'birthday' => $member->birth_date?->format('d.m.Y') ?? '—',
            'category' => $member->sport_category?->getLabel() ?? '—',
            'role' => $member->pivot?->role === 'captain' ? 'Капитан' : 'Участник',
        ])->values();

        $participation = $team->regattaEntries
            ->filter(fn ($entry) => $entry->regatta?->isFinished())
            ->sortByDesc(fn ($entry) => $entry->regatta?->date_start)
            ->map(fn ($entry) => [
                'regatta' => $entry->regatta?->name ?? '—',
                'date_event' => $entry->regatta?->dateRange() ?? '—',
                'yacht' => $entry->yacht?->name ?? '—',
                'place' => $team->regattaResultItems
                    ->firstWhere('regattaResult.regatta_id', $entry->regatta_id)
                    ?->final_position ?? '—',
            ])->values();

        $pdf = Pdf::loadView('pdf.team-history', [
            'team' => $team,
            'captain' => $team->organizer?->name ?? '—',
            'rating' => $rating ?? '—',
            'status' => $team->is_archived ? 'Неактивная' : 'Активная',
            'members' => $members,
            'participation' => $participation,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false);

        $safeName = preg_replace('/[^\w\s\-а-яё]/ui', '', $team->name);
        $safeName = trim(preg_replace('/\s+/', '_', $safeName)) ?: 'team';

        return $pdf->download("{$safeName}_history.pdf");
    }
}
