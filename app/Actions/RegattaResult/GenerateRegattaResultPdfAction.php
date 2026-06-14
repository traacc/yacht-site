<?php

namespace App\Actions\RegattaResult;

use App\Models\RegattaResult;
use App\Models\RegattaEntry;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Формирует PDF с результатами регаты — PDF-аналог RegattaResultExport (xlsx).
 *
 * Логика сбора данных намеренно повторяет App\Exports\RegattaResultExport:
 * номера гонок выводятся из событий регаты, экипаж и результаты гонок
 * подтягиваются через заявку команды (RegattaEntry), связь идёт по team_id.
 */
final class GenerateRegattaResultPdfAction
{
    public function execute(RegattaResult $regattaResult, ?string $filename = null): StreamedResponse
    {
        $regatta = $regattaResult->regatta;

        // Позиции результата, отсортированные по итоговому месту как число.
        // Пустые / нечисловые final_position уходят в конец.
        $resultItems = $regattaResult->items()
            ->with(['team', 'yacht'])
            ->get()
            ->sortBy(fn ($item) => is_numeric($item->final_position) ? (int) $item->final_position : PHP_INT_MAX)
            ->values();

        // ── Гонки регаты (event_type = race) → порядковые номера 1..N ──────────
        $raceEvents = $regatta
            ? $regatta->races()->where('event_type', 'race')->get()
            : collect();

        $raceNumberByEvent = [];
        foreach ($raceEvents->values() as $i => $event) {
            $raceNumberByEvent[$event->id] = $i + 1;
        }
        $eventByRaceNumber = array_flip($raceNumberByEvent);

        // Шаблон поддерживает 0–6 гонок.
        $raceCount = min(count($raceNumberByEvent), 6);

        // ── Заявки регаты, индексированные по команде ───────────────────────────
        // Приоритет у одобренной заявки: approved сортируем последними, т.к.
        // keyBy оставляет последний элемент с данным ключом.
        $entriesByTeam = RegattaEntry::query()
            ->where('regatta_id', $regattaResult->regatta_id)
            ->with(['crew.teamMember.user', 'raceResults'])
            ->orderByRaw("status = 'approved' ASC")
            ->get()
            ->keyBy('team_id');

        // ── Нормализуем строки для шаблона ─────────────────────────────────────
        $rows = $resultItems->map(function ($item) use ($entriesByTeam, $eventByRaceNumber, $raceCount) {
            $entry = $entriesByTeam->get($item->team_id);

            // Экипаж: капитан → основной состав → запас.
            $roleOrder = ['captain' => 0, 'main' => 1, 'reserve' => 2];
            $crew = collect();
            if ($entry) {
                $crew = $entry->crew
                    ->sortBy(fn ($c) => $roleOrder[$c->role] ?? 9)
                    ->map(function ($c) {
                        $user = $c->teamMember->user ?? null;
                        if (! $user) {
                            return null;
                        }
                        return [
                            'name'     => $user->name,
                            'birth'    => $user->birth_date?->format('d.m.Y'),
                            'category' => $user->sport_category?->getLabel(),
                        ];
                    })
                    ->filter()
                    ->values();
            }

            // Результаты отдельных гонок индексируем по event_id.
            $raceResultsByEvent = $entry ? $entry->raceResults->keyBy('event_id') : collect();

            $races = [];
            for ($raceNum = 1; $raceNum <= $raceCount; $raceNum++) {
                $eventId    = $eventByRaceNumber[$raceNum] ?? null;
                $raceResult = $eventId ? $raceResultsByEvent->get($eventId) : null;

                // Место: код пенальти (dns/dnf/dsq…) либо число, иначе прочерк.
                if ($raceResult && $raceResult->penalty_code) {
                    $pos = mb_strtolower($raceResult->penalty_code);
                } else {
                    $pos = $raceResult && $raceResult->position !== null
                        ? $raceResult->position
                        : '-';
                }

                $pts = $raceResult && $raceResult->points !== null
                    ? $raceResult->points
                    : null;

                $races[$raceNum] = ['pos' => $pos, 'pts' => $pts];
            }

            return [
                'position'  => $item->final_position,
                'sail'      => optional($item->yacht)->vfps_number ?? '',
                'team'      => optional($item->team)->name ?? '',
                'not_participate' => $item->not_participate ?? '',
                'yacht'     => optional($item->yacht)->name ?? '',
                'total'     => $item->total_points ?? 0,
                'crew'      => $crew,
                'races'     => $races,
            ];
        });

        $pdf = Pdf::loadView('pdf.regatta-result', [
            'regatta'   => $regatta,
            'rows'      => $rows,
            'raceCount' => $raceCount,
            'dateRange' => $this->formatDateRange($regatta?->date_start, $regatta?->date_end),
        ])
            ->setPaper('a4', 'landscape')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false);

        if (! $filename) {
            $safeName = preg_replace('/[^\w\s\-а-яё]/ui', '', $regatta?->name ?? 'regatta');
            $safeName = trim(preg_replace('/\s+/', '_', $safeName)) ?: 'regatta';
            $result_type = $regattaResult->result_type;
            $result_type_label = 'итоговые_результаты';

            if($result_type == 'preliminary')
                $result_type_label = 'предварительные_результаты';

            $filename = "{$safeName}_{$result_type_label}.pdf";
        }

        // StreamedResponse (а не Pdf::download), т.к. action вызывается в
        // Livewire-контексте Filament, который иначе JSON-кодирует бинарный ответ.
        $output = $pdf->output();

        return new StreamedResponse(function () use ($output) {
            echo $output;
        }, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    protected function formatDateRange(?string $dateStart, ?string $dateEnd): string
    {
        if (! $dateStart) {
            return '';
        }

        $months = [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
            5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
            9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
        ];

        $start = \Carbon\Carbon::parse($dateStart);
        $end   = $dateEnd ? \Carbon\Carbon::parse($dateEnd) : $start;

        $monthName = $months[(int) $end->format('n')];
        $year      = $end->format('Y');

        if ($start->isSameDay($end)) {
            return "{$start->format('j')} {$monthName} {$year} года";
        }

        if ($start->format('n') === $end->format('n')) {
            return "{$start->format('j')}-{$end->format('j')} {$monthName} {$year} года";
        }

        $startMonth = $months[(int) $start->format('n')];
        return "{$start->format('j')} {$startMonth} - {$end->format('j')} {$monthName} {$year} года";
    }
}
