<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Yacht;
use Illuminate\Contracts\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Экспорт яхт в .xlsx. Структура колонок повторяет шаблон импорта
 * (public/files/CARTER 30_list.xlsx), чтобы выгрузку можно было
 * отредактировать и загрузить обратно.
 */
class YachtExport
{
    /** @var array<string, string> Заголовок → ширина колонки. */
    private const COLUMNS = [
        'Тип яхты' => 'A',
        '№' => 'B',
        'Название яхты' => 'C',
        'Г.в.' => 'D',
        'Владелец' => 'E',
        'Место регистрации' => 'F',
    ];

    private const COLUMN_WIDTHS = [
        'A' => 14, 'B' => 8, 'C' => 24, 'D' => 8, 'E' => 36, 'F' => 22,
    ];

    public function download(string $filename = 'yachts.xlsx'): StreamedResponse
    {
        $spreadsheet = $this->build();

        $response = new StreamedResponse(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    protected function build(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Яхты');

        foreach (self::COLUMN_WIDTHS as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // ── Строка заголовков ────────────────────────────────────────────
        foreach (array_keys(self::COLUMNS) as $i => $title) {
            $sheet->setCellValue([$i + 1, 1], $title);
        }

        $columns = self::COLUMNS;
        $lastCol = end($columns);
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // ── Данные ───────────────────────────────────────────────────────
        $row = 2;
        $this->query()->each(function (Yacht $yacht) use ($sheet, &$row): void {
            $sheet->setCellValue([1, $row], $yacht->project ?? $yacht->class);
            $sheet->setCellValue([2, $row], $yacht->vfps_number);
            $sheet->setCellValue([3, $row], $yacht->name);
            $sheet->setCellValue([4, $row], $yacht->year);
            $sheet->setCellValue([5, $row], $yacht->user?->name ?? $yacht->owner_name);
            $sheet->setCellValue([6, $row], $yacht->reg_place);

            $row++;
        });

        // Номера на парусе храним как текст, чтобы не потерять ведущие нули.
        $sheet->getStyle('B2:B'.max($row - 1, 2))
            ->getNumberFormat()->setFormatCode('@');

        if ($row > 2) {
            $sheet->getStyle('A1:'.$lastCol.($row - 1))
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        return $spreadsheet;
    }

    protected function query(): Builder
    {
        return Yacht::query()
            ->with('user:id,name')
            ->orderBy('vfps_number');
    }
}
