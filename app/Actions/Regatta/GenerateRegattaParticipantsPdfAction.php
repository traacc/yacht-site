<?php

namespace App\Actions\Regatta;

use App\Enums\ParticipantSort;
use App\Models\Regatta;
use App\Services\RegattaParticipants;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Участники регаты в PDF (админка, «Регаты» → «Участники»): ФИО, дата
 * рождения и команда по одобренным заявкам, сверху общее количество.
 *
 * Ответ потоковый — Livewire отдаёт на скачивание только StreamedResponse.
 */
final class GenerateRegattaParticipantsPdfAction
{
    public function __construct(private readonly RegattaParticipants $participants) {}

    public function execute(Regatta $regatta, ParticipantSort $sort): StreamedResponse
    {
        $dates = collect([$regatta->date_start?->format('d.m.Y'), $regatta->date_end?->format('d.m.Y')])
            ->filter()
            ->unique()
            ->implode(' — ');

        $pdf = Pdf::loadView('pdf.participants', [
            'title' => "Участники: {$regatta->name}",
            'subtitle' => $dates,
            'regattas' => null,
            'rows' => $this->participants->forRegatta($regatta, $sort),
            'sort' => $sort,
        ])
            ->setPaper('a4')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false);

        $slug = Str::slug($regatta->name) ?: 'regatta';

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            "uchastniki-{$slug}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }
}
