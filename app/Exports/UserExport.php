<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\SportCategory;
use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Экспорт пользователей в .xlsx. Учитывает переданный запрос
 * (фильтры/поиск/сортировка таблицы Filament).
 */
class UserExport
{
    /** @var array<string, string> Заголовок → буква колонки. */
    private const COLUMNS = [
        'ID' => 'A',
        'ФИО' => 'B',
        'Дата рождения' => 'C',
        'Email' => 'D',
        'Телефон' => 'E',
        'Спортивный разряд' => 'F',
        'Системная роль' => 'G',
        'Дата регистрации' => 'H',
    ];

    private const COLUMN_WIDTHS = [
        'A' => 12, 'B' => 30, 'C' => 14, 'D' => 30, 'E' => 20, 'F' => 22, 'G' => 18, 'H' => 18,
    ];

    public function download(Builder $query, string $filename = 'users.xlsx'): StreamedResponse
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
        $sheet->setTitle('Пользователи');

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
        $query->each(function (User $user) use ($sheet, &$row): void {
            $category = $user->sport_category;
            $role = $user->system_role;

            $sheet->setCellValue([1, $row], $user->formatted_external_id ?? '');
            $sheet->setCellValue([2, $row], $user->name);
            $sheet->setCellValue([3, $row], $user->birth_date?->format('d.m.Y'));
            $sheet->setCellValue([4, $row], $user->email);
            $sheet->setCellValue([5, $row], $user->phone);
            $sheet->setCellValue([6, $row], $category instanceof SportCategory ? $category->getLabel() : '');
            $sheet->setCellValue([7, $row], $role instanceof SystemRole ? $role->getLabel() : '');
            $sheet->setCellValue([8, $row], $user->created_at?->format('d.m.Y H:i'));

            $row++;
        });

        // Телефон храним как текст, чтобы не потерять формат и плюс.
        $sheet->getStyle('E2:E'.max($row - 1, 2))
            ->getNumberFormat()->setFormatCode('@');

        if ($row > 2) {
            $sheet->getStyle('A1:'.$lastCol.($row - 1))
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        return $spreadsheet;
    }
}
