<?php

namespace App\Actions\Regatta;

use App\Models\Regatta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

final class GenerateRegattaTeamsPdfAction
{
    /**
     * Сформировать PDF со списком всех заявленных команд и их составом.
     */
    public function execute(Regatta $regatta): Response
    {
        $entries = $regatta->approvedEntries()
            ->with(['team.organizer', 'crew.teamMember.user', 'yacht'])
            ->get()
            ->sortBy(fn ($entry) => $entry->team?->name, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        if ($entries->isEmpty()) {
            abort(404, 'Нет заявленных команд для скачивания');
        }

        $pdf = Pdf::loadView('pdf.regatta-teams', [
            'regatta' => $regatta,
            'entries' => $entries,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false);

        $safeName = preg_replace('/[^\w\s\-а-яё]/ui', '', $regatta->name);
        $safeName = trim(preg_replace('/\s+/', '_', $safeName)) ?: 'regatta';

        return $pdf->download("{$safeName}_teams.pdf");
    }
}
