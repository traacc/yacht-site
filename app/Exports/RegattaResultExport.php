<?php

namespace App\Exports;

use App\Models\RegattaResult;
use App\Models\RaceResults;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegattaResultExport
{
    protected RegattaResult $regattaResult;

    // Column map: race index (1-based) => [position col, points col]
    // Columns: A=1, B=2, ..., H=8 (race1 pos), I=9 (race1 pts), J=10 (race2 pos), ...
    protected const RACE_COLUMNS = [
        1 => ['pos' => 'H', 'pts' => 'I'],
        2 => ['pos' => 'J', 'pts' => 'K'],
        3 => ['pos' => 'L', 'pts' => 'M'],
        4 => ['pos' => 'N', 'pts' => 'O'],
        5 => ['pos' => 'P', 'pts' => 'Q'],
        6 => ['pos' => 'R', 'pts' => 'S'],
    ];

    public function __construct(RegattaResult $regattaResult)
    {
        $this->regattaResult = $regattaResult;
    }

    public function download(string $filename = 'regatta_results.xlsx'): StreamedResponse
    {
        $spreadsheet = $this->build();

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    protected function build(): Spreadsheet
    {
        $regatta = $this->regattaResult->regatta;

        // Load all result items with relations, sorted by final position
        $resultItems = $this->regattaResult->items()
            ->with([
                'team',
                'yacht',
                //'raceResults',
                'regattaEntry.crew.teamMember.user',
            ])
            ->orderBy('final_position')
            ->get();

        // Determine the number of races dynamically
        $raceCount = $resultItems->flatMap->raceResults->pluck('event_id')->unique()->count();
        $raceCount = max(1, min($raceCount, 6)); // Clamp to 1–6 races (template supports 6)

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Результаты');

        // ── Column widths (match template) ──────────────────────────────────
        $colWidths = [
            'A' => 5.23, 'B' => 6.69, 'C' => 8.69, 'D' => 10.15,
            'E' => 17.77, 'F' => 7.46, 'G' => 6.0,
            'H' => 4.77, 'I' => 4.0, 'J' => 4.77, 'K' => 4.0,
            'L' => 4.77, 'M' => 4.0, 'N' => 4.77, 'O' => 4.0,
            'P' => 4.77, 'Q' => 4.0, 'R' => 4.77, 'S' => 4.0,
            'T' => 9.23,
        ];
        foreach ($colWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // ── Rows 1–3: header info ────────────────────────────────────────────
        $this->writeHeaderRows($sheet, $regatta);

        // ── Rows 5–6: column headers ─────────────────────────────────────────
        $this->writeColumnHeaders($sheet, $raceCount);

        // ── Data rows ────────────────────────────────────────────────────────
        $currentRow = 7;
        foreach ($resultItems as $item) {
            $currentRow = $this->writeResultItem($sheet, $item, $currentRow, $raceCount);
        }

        return $spreadsheet;
    }

    // ────────────────────────────────────────────────────────────────────────
    // Header rows 1–3
    // ────────────────────────────────────────────────────────────────────────

    protected function writeHeaderRows($sheet, $regatta): void
    {
        $dateRange = $this->formatDateRange($regatta->date_start, $regatta->date_end);

        $headers = [
            1 => $regatta->name,
            2 => 'Зачётная группа Carter 30', // Adjust or make dynamic as needed
            3 => $regatta->water_area . '. ' . $dateRange,
        ];

        $rowHeights = [1 => 12.9, 2 => 14.6, 3 => 12.9, 4 => 16.75];
        foreach ($rowHeights as $row => $height) {
            $sheet->getRowDimension($row)->setRowHeight($height);
        }

        foreach ($headers as $row => $text) {
            $sheet->mergeCells("A{$row}:S{$row}");
            $sheet->setCellValue("A{$row}", $text);
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 10],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
        }
    }

    // ────────────────────────────────────────────────────────────────────────
    // Column header rows 5–6
    // ────────────────────────────────────────────────────────────────────────

    protected function writeColumnHeaders($sheet, int $raceCount): void
    {
        $sheet->getRowDimension(5)->setRowHeight(9.0);
        $sheet->getRowDimension(6)->setRowHeight(15.0);

        $headerStyle = [
            'font'      => ['bold' => true, 'size' => 7],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ];

        // Merge row 5 fixed columns (A:G span rows 5:6 individually)
        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $col) {
            $sheet->mergeCells("{$col}5:{$col}6");
        }

        $fixedHeaders = [
            'A5' => 'Место', 'B5' => 'Парус №', 'C5' => 'Команда',
            'D5' => 'Яхта', 'E5' => 'Экипаж', 'F5' => 'Дата рождения', 'G5' => 'Разряд',
        ];
        foreach ($fixedHeaders as $cell => $value) {
            $sheet->setCellValue($cell, $value);
            $sheet->getStyle($cell)->applyFromArray($headerStyle);
        }

        // Race header columns
        foreach (self::RACE_COLUMNS as $raceNum => $cols) {
            if ($raceNum > $raceCount) {
                break;
            }
            $posCol = $cols['pos'];
            $ptsCol = $cols['pts'];

            // Row 5: merge pos+pts, label "Гонка N"
            $sheet->mergeCells("{$posCol}5:{$ptsCol}5");
            $sheet->setCellValue("{$posCol}5", "Гонка {$raceNum}");
            $sheet->getStyle("{$posCol}5")->applyFromArray($headerStyle);

            // Row 6: sub-headers
            $sheet->setCellValue("{$posCol}6", 'Место');
            $sheet->setCellValue("{$ptsCol}6", 'Очки');
            $sheet->getStyle("{$posCol}6")->applyFromArray($headerStyle);
            $sheet->getStyle("{$ptsCol}6")->applyFromArray($headerStyle);
        }

        // Total column
        $sheet->mergeCells('T5:T6');
        $sheet->setCellValue('T5', "Итого\nочков");
        $sheet->getStyle('T5')->applyFromArray(array_merge($headerStyle, [
            'alignment' => ['wrapText' => true, 'horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]));
    }

    // ────────────────────────────────────────────────────────────────────────
    // One result item block (team + crew rows)
    // ────────────────────────────────────────────────────────────────────────

    protected function writeResultItem($sheet, $item, int $startRow, int $raceCount): int
    {
        // Collect crew members via RegattaEntry
        $crewMembers = collect();
        $entry = $item->regattaEntry;
        if ($entry) {
            $crewMembers = $entry->crew->map(fn ($crew) => $crew->teamMember->user ?? null)->filter();
        }

        $crewCount  = max(1, $crewMembers->count());
        $endRow     = $startRow + $crewCount - 1;

        // ── Merges for all spanned columns ──────────────────────────────────
        $spannedCols = ['A', 'B', 'C', 'D']; // position, sail, team, yacht
        // Race result cells also span the crew rows
        foreach (self::RACE_COLUMNS as $raceNum => $cols) {
            if ($raceNum <= $raceCount) {
                $spannedCols[] = $cols['pos'];
                $spannedCols[] = $cols['pts'];
            }
        }
        $spannedCols[] = 'T'; // total

        foreach ($spannedCols as $col) {
            if ($endRow > $startRow) {
                $sheet->mergeCells("{$col}{$startRow}:{$col}{$endRow}");
            }
        }

        // Row heights
        for ($r = $startRow; $r <= $endRow; $r++) {
            $sheet->getRowDimension($r)->setRowHeight(9.9);
        }

        // ── Cell styles ──────────────────────────────────────────────────────
        $centerBold = [
            'font'      => ['bold' => true, 'size' => 8],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['top' => ['borderStyle' => Border::BORDER_THIN], 'bottom' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $centerNormal = [
            'font'      => ['bold' => false, 'size' => 8],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['top' => ['borderStyle' => Border::BORDER_THIN], 'bottom' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $leftSmall = [
            'font'      => ['size' => 6],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['top' => ['borderStyle' => Border::BORDER_THIN]],
        ];

        // ── Position ─────────────────────────────────────────────────────────
        $sheet->setCellValue("A{$startRow}", $item->final_position);
        $sheet->getStyle("A{$startRow}")->applyFromArray($centerBold);

        // ── Sail number (yacht name used as sail № placeholder) ──────────────
        $sailNumber = optional($item->yacht)->name ?? '';
        $sheet->setCellValue("B{$startRow}", $sailNumber);
        $sheet->getStyle("B{$startRow}")->applyFromArray($centerNormal);

        // ── Team name ─────────────────────────────────────────────────────────
        $sheet->setCellValue("C{$startRow}", optional($item->team)->name ?? '');
        $sheet->getStyle("C{$startRow}")->applyFromArray($centerBold);

        // ── Yacht name ───────────────────────────────────────────────────────
        $sheet->setCellValue("D{$startRow}", optional($item->yacht)->name ?? '');
        $sheet->getStyle("D{$startRow}")->applyFromArray($centerBold);

        // ── Race results ─────────────────────────────────────────────────────
        // Index race results by event_id (race number)
        $raceResultsByEvent = $item->raceResults->keyBy('event_id');

        // Collect points columns that are NOT discarded (the two worst are discarded)
        // The template formula sums only non-parenthesised columns — we replicate that logic.
        $pointsCols = [];

        foreach (self::RACE_COLUMNS as $raceNum => $cols) {
            if ($raceNum > $raceCount) {
                break;
            }
            $posCol = $cols['pos'];
            $ptsCol = $cols['pts'];

            $raceResult = $raceResultsByEvent->get($raceNum);
            $position   = $raceResult ? $raceResult->position : null;
            $points     = $raceResult ? $raceResult->points   : null;

            $posValue = $position ?? 'dns';
            $ptsValue = $points   ?? ($raceCount + 1); // dns/dnf = N+1

            $sheet->setCellValue("{$posCol}{$startRow}", $posValue);
            $sheet->getStyle("{$posCol}{$startRow}")->applyFromArray($centerNormal);

            $sheet->setCellValue("{$ptsCol}{$startRow}", $ptsValue);
            $sheet->getStyle("{$ptsCol}{$startRow}")->applyFromArray($centerBold);

            $pointsCols[$raceNum] = "{$ptsCol}{$startRow}";
        }

        // ── Total formula: sum all points cols except the 2 worst ────────────
        // Determine the 2 highest-points (worst) race columns to exclude
        $pointsValues = collect($pointsCols)->map(fn ($cell) => (float) $sheet->getCell($cell)->getValue());
        $sortedDesc   = $pointsValues->sortDesc();
        $excludeRaces = $sortedDesc->keys()->take(min(2, max(0, $raceCount - 2)))->all();

        $sumCols = [];
        foreach ($pointsCols as $raceNum => $cell) {
            if (!in_array($raceNum, $excludeRaces)) {
                $sumCols[] = $cell;
            }
        }

        if (!empty($sumCols)) {
            $formula = '=' . implode('+', $sumCols);
        } else {
            $formula = $item->total_points ?? 0;
        }

        $sheet->setCellValue("T{$startRow}", $formula);
        $sheet->getStyle("T{$startRow}")->applyFromArray($centerBold);

        // ── Crew members ──────────────────────────────────────────────────────
        foreach ($crewMembers as $index => $user) {
            $crewRow  = $startRow + $index;
            $isCaptain = ($index === 0);

            // Name
            $name = $user ? $user->name : '';
            $sheet->setCellValue("E{$crewRow}", $name);
            $nameStyle = array_merge($leftSmall, ['font' => ['bold' => $isCaptain, 'size' => $isCaptain ? 8 : 6]]);
            $sheet->getStyle("E{$crewRow}")->applyFromArray($nameStyle);

            // Birthday
            if ($user && $user->birthday) {
                $sheet->setCellValue("F{$crewRow}", \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(strtotime($user->birthday)));
                $sheet->getStyle("F{$crewRow}")->getNumberFormat()->setFormatCode('DD.MM.YYYY');
            }
            $sheet->getStyle("F{$crewRow}")->applyFromArray([
                'font'      => ['size' => 6],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['top' => ['borderStyle' => Border::BORDER_THIN]],
            ]);

            // Sport category
            $sheet->setCellValue("G{$crewRow}", $user ? ($user->sport_category ?? '') : '');
            $sheet->getStyle("G{$crewRow}")->applyFromArray([
                'font'      => ['size' => 6],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['top' => ['borderStyle' => Border::BORDER_THIN]],
            ]);
        }

        return $endRow + 1;
    }

    // ────────────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────────────

    protected function formatDateRange(?string $dateStart, ?string $dateEnd): string
    {
        if (!$dateStart) {
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
