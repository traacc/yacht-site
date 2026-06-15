<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Экспорт команд в .xlsx. Учитывает переданный запрос
 * (фильтры/поиск/сортировка таблицы Filament).
 */
class TeamExport
{
    /** @var array<string, string> Заголовок → буква колонки. */
    private const COLUMNS = [
        'ID' => 'A',
        'Название' => 'B',
        'Организатор' => 'C',
        'Статус одобрения' => 'D',
        'Участников' => 'E',
        'Описание' => 'F',
        'Дата создания' => 'G',
    ];

    private const COLUMN_WIDTHS = [
        'A' => 12, 'B' => 30, 'C' => 30, 'D' => 18, 'E' => 12, 'F' => 40, 'G' => 18,
    ];

    /** @var array<string, string> approval_status → подпись. */
    private const STATUS_LABELS = [
        'pending' => 'На рассмотрении',
        'approved' => 'Одобрена',
        'rejected' => 'Отклонена',
        'withdrawn' => 'Отозвана',
    ];

    public function download(Builder $query, string $filename = 'teams.xlsx'): StreamedResponse
    {
        $spreadsheet = $this->build($query);

        $response = new StreamedResponse(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    protected function build(Builder $query): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Команды');

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
        $query->with('organizer')->each(function (Team $team) use ($sheet, &$row): void {
            $membersCount = $team->active_members_count ?? $team->activeMembers()->count();

            $sheet->setCellValue([1, $row], $team->formatted_external_id ?? '');
            $sheet->setCellValue([2, $row], $team->name);
            $sheet->setCellValue([3, $row], $team->organizer?->name ?? '');
            $sheet->setCellValue([4, $row], self::STATUS_LABELS[$team->approval_status] ?? $team->approval_status);
            $sheet->setCellValue([5, $row], (int) $membersCount);
            $sheet->setCellValue([6, $row], $team->description ?? '');
            $sheet->setCellValue([7, $row], $team->created_at?->format('d.m.Y H:i'));

            $row++;
        });

        if ($row > 2) {
            $sheet->getStyle('A1:'.$lastCol.($row - 1))
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        return $spreadsheet;
    }
}
