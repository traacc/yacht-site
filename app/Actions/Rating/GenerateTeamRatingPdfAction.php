<?php

namespace App\Actions\Rating;

use App\Models\Season;
use App\Services\RatingCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Общий командный рейтинг сезона в PDF: по вертикали команды, по горизонтали
 * регаты сезона по номерам (место и очки), справа итог. Собирается из текущих
 * результатов, поэтому по ходу сезона пополняется сам.
 */
final class GenerateTeamRatingPdfAction
{
    public function __construct(private readonly RatingCalculator $calculator) {}

    public function execute(Season $season): Response
    {
        $table = $this->calculator->seasonTeamTable($season);

        $pdf = Pdf::loadView('pdf.rating-table', [
            'documentTitle' => "Командный рейтинг {$season->year}",
            'tables' => $table['standings'] === [] ? [] : [
                ['title' => 'Командный рейтинг', ...$table],
            ],
            'season' => $season,
        ])
            ->setPaper('a4', 'landscape')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false);

        return $pdf->download("team-rating-{$season->year}.pdf");
    }
}
