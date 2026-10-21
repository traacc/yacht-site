<?php

namespace App\Actions\Rating;

use App\Enums\ParticipantSort;
use App\Models\Season;
use App\Services\RatingCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Участники сезона в PDF (админка, «Личные рейтинги»): по вертикали ФИО,
 * дальше дата рождения, команды, очки за каждую прошедшую регату сезона
 * (номера расшифрованы легендой), сумма и место в личном рейтинге.
 *
 * Ответ потоковый — Livewire отдаёт на скачивание только StreamedResponse.
 */
final class GenerateSeasonParticipantsPdfAction
{
    public function __construct(private readonly RatingCalculator $calculator) {}

    public function execute(Season $season, ParticipantSort $sort): StreamedResponse
    {
        $table = $this->calculator->seasonPersonalTable($season);

        $pdf = Pdf::loadView('pdf.participants', [
            'title' => "Участники сезона {$season->year}",
            'subtitle' => 'Личный рейтинг · по состоянию на '.now()->format('d.m.Y'),
            'regattas' => $table['regattas'],
            'rows' => $sort->apply($table['rows']),
            'sort' => $sort,
        ])
            ->setPaper('a4', 'landscape')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false);

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            "uchastniki-sezona-{$season->year}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }
}
