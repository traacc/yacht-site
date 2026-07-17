<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Regatta;
use App\Models\RegattaEntry;
use App\Services\RgdParticipantsExporter;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Экспорт списка участников регаты в XLSX. Данные (заявки, экипаж, яхта под
 * OwnedScope) грузятся тем же RgdParticipantsExporter::loadParticipants(), что и
 * судейский .rgd, чтобы состав/сортировка совпадали. Каждая заявка — блок строк
 * по числу членов экипажа; парус №, яхта, команда и город объединены по блоку.
 */
class RegattaParticipantsExport
{
    /** Порядок членов экипажа: капитан → основной состав → запас. */
    private const ROLE_ORDER = ['captain' => 0, 'main' => 1, 'reserve' => 2];

    public function __construct(
        private readonly RgdParticipantsExporter $rgd,
    ) {}

    /** Имя файла для скачивания. */
    public function filename(Regatta $regatta): string
    {
        $slug = Str::slug($regatta->name) ?: 'regatta';

        return "uchastniki-{$slug}.xlsx";
    }

    /**
     * Заявки регаты для экспорта (тот же источник, что и у .rgd).
     *
     * @return \Illuminate\Support\Collection<int, RegattaEntry>
     */
    public function loadParticipants(Regatta $regatta): \Illuminate\Support\Collection
    {
        return $this->rgd->loadParticipants($regatta);
    }

    /** Потоковый ответ со скачиванием XLSX. */
    public function download(Regatta $regatta): StreamedResponse
    {
        $spreadsheet = $this->build($regatta, $this->loadParticipants($regatta));

        $response = new StreamedResponse(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $this->filename($regatta) . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    /**
     * Строит книгу XLSX по заявкам регаты.
     *
     * @param  \Illuminate\Support\Collection<int, RegattaEntry>  $entries
     */
    public function build(Regatta $regatta, iterable $entries): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Участники');

        $widths = [
            'A' => 5, 'B' => 9, 'C' => 22, 'D' => 20,
            'E' => 30, 'F' => 13, 'G' => 9, 'H' => 11, 'I' => 20,
        ];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // ── Заголовок регаты ────────────────────────────────────────────────
        $dateRange = trim(implode(' — ', array_filter([
            $regatta->date_start?->format('d.m.Y'),
            $regatta->date_end?->format('d.m.Y'),
        ])));
        $sheet->mergeCells('A1:I1');
        $sheet->setCellValue('A1', $regatta->name);
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->mergeCells('A2:I2');
        $sheet->setCellValue('A2', trim(($regatta->water_area ? $regatta->water_area . '. ' : '') . $dateRange));
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // ── Шапка таблицы ───────────────────────────────────────────────────
        $headerRow = 4;
        $headers = [
            'A' => '№', 'B' => 'Парус №', 'C' => 'Команда', 'D' => 'Яхта',
            'E' => 'Экипаж (ФИО)', 'F' => 'Дата рождения', 'G' => 'Разряд',
            'H' => 'Роль', 'I' => 'Город',
        ];
        foreach ($headers as $col => $text) {
            $sheet->setCellValue("{$col}{$headerRow}", $text);
        }
        $sheet->getStyle("A{$headerRow}:I{$headerRow}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8E8E8']],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // ── Строки участников ───────────────────────────────────────────────
        $row = $headerRow + 1;
        $number = 1;
        foreach ($entries as $entry) {
            $row = $this->writeEntry($sheet, $entry, $row, $number);
            $number++;
        }

        return $spreadsheet;
    }

    /** Пишет блок одной заявки и возвращает номер следующей свободной строки. */
    private function writeEntry($sheet, RegattaEntry $entry, int $startRow, int $number): int
    {
        $yacht = $entry->yacht;

        $crew = $entry->crew
            ->sortBy(fn ($c) => self::ROLE_ORDER[$c->role] ?? 9)
            ->map(function ($c) {
                $user = $c->teamMember?->user;
                $name = trim((string) $user?->name);

                return $name === '' ? null : [
                    'name'  => $name,
                    'birth' => $user?->birth_date,
                    'rank'  => $user?->sport_category?->getLabel() ?? '',
                    'role'  => $c->role === 'captain' ? 'Капитан' : '',
                ];
            })
            ->filter()
            ->values();

        $count   = max(1, $crew->count());
        $endRow  = $startRow + $count - 1;

        // Поля уровня заявки объединяем по всем строкам экипажа.
        $entryCells = [
            'A' => (string) $number,
            'B' => (string) ($yacht?->vfps_number ?? ''),
            'C' => (string) ($entry->team?->name ?? ''),
            'D' => (string) ($yacht?->name ?? ''),
            'I' => (string) ($yacht?->reg_place ?? ''),
        ];
        foreach ($entryCells as $col => $value) {
            if ($endRow > $startRow) {
                $sheet->mergeCells("{$col}{$startRow}:{$col}{$endRow}");
            }
            $sheet->setCellValue("{$col}{$startRow}", $value);
        }

        // Строки членов экипажа.
        foreach ($crew as $i => $member) {
            $r = $startRow + $i;
            $sheet->setCellValue("E{$r}", $member['name']);

            if ($member['birth']) {
                $sheet->setCellValue("F{$r}", ExcelDate::PHPToExcel($member['birth']));
                $sheet->getStyle("F{$r}")->getNumberFormat()->setFormatCode('DD.MM.YYYY');
            }

            $sheet->setCellValue("G{$r}", $member['rank']);
            $sheet->setCellValue("H{$r}", $member['role']);
        }

        // ── Оформление блока ────────────────────────────────────────────────
        $range = "A{$startRow}:I{$endRow}";
        $sheet->getStyle($range)->applyFromArray([
            'font'      => ['size' => 9],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        // Числовые/короткие поля — по центру, ФИО/команда/яхта/город — по левому краю.
        $sheet->getStyle("A{$startRow}:B{$endRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("F{$startRow}:H{$endRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return $endRow + 1;
    }
}
