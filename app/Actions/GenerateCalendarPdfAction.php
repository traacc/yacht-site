<?php

namespace App\Actions;

use App\Models\Regatta;
use App\Models\Season;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class GenerateCalendarPdfAction
{
    public function execute(?int $year = null): Response
    {
        $year ??= (int) now()->format('Y');

        $regattas = Regatta::query()
            ->with('season')
            ->whereHas('season', fn ($q) => $q->where('year', $year))
            ->orderBy('date_start')
            ->get();

        $season = Season::query()->where('year', $year)->first();

        $pdf = Pdf::loadView('pdf.regattas-calendar', [
            'regattas' => $regattas,
            'year'     => $year,
            'season'   => $season,
        ])
        ->setPaper('a4', 'landscape')
        ->setOption('defaultFont', 'DejaVu Sans')
        ->setOption('isHtml5ParserEnabled', true)
        ->setOption('isRemoteEnabled', false);

        return $pdf->download("calendar-{$year}.pdf");
    }
}
