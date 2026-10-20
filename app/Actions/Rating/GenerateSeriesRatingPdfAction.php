<?php

namespace App\Actions\Rating;

use App\Models\Season;
use App\Models\Series;
use App\Services\RatingCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Рейтинг по сериям сезона в PDF: все серии одним файлом, каждая — своя
 * таблица на отдельном листе (те же серии, что на странице «Результаты серий»).
 */
final class GenerateSeriesRatingPdfAction
{
    public function __construct(private readonly RatingCalculator $calculator) {}

    public function execute(Season $season): Response
    {
        $tables = Series::query()
            ->where('season_id', $season->id)
            ->whereHas('regattas.results.items')
            ->get()
            ->map(fn (Series $series) => ['title' => $series->name, ...$this->calculator->seriesTeamTable($series)])
            ->filter(fn (array $table) => $table['standings'] !== [])
            ->values()
            ->all();

        $pdf = Pdf::loadView('pdf.rating-table', [
            'documentTitle' => "Рейтинг серий {$season->year}",
            'tables' => $tables,
            'season' => $season,
        ])
            ->setPaper('a4', 'landscape')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false);

        return $pdf->download("series-rating-{$season->year}.pdf");
    }
}
