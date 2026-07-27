<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\PaymentRegistryLogEvent;
use App\Enums\SystemRole;
use App\Models\PaymentRegistryLog;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Экспорт журнала изменений реестра платежей в .xlsx. Учитывает переданный
 * запрос (фильтры/поиск/сортировка таблицы Filament).
 */
class PaymentRegistryLogExport
{
    /** @var array<string, string> Заголовок → буква колонки. */
    private const COLUMNS = [
        'Дата и время' => 'A',
        'Платёж' => 'B',
        'Сумма платежа' => 'C',
        'Событие' => 'D',
        'Кто изменил' => 'E',
        'Роль' => 'F',
        'Что изменено' => 'G',
        'IP' => 'H',
    ];

    private const COLUMN_WIDTHS = [
        'A' => 18, 'B' => 40, 'C' => 16, 'D' => 24, 'E' => 30, 'F' => 18, 'G' => 60, 'H' => 16,
    ];

    public function download(Builder $query, string $filename = 'payment_registry_log.xlsx'): StreamedResponse
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
        $sheet->setTitle('Журнал изменений');

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

        // Журнал длинный — фиксируем шапку.
        $sheet->freezePane('A2');

        // ── Данные ───────────────────────────────────────────────────────
        $row = 2;
        $query->with('user')->each(function (PaymentRegistryLog $log) use ($sheet, &$row): void {
            $event = $log->event;
            $role = $log->user?->system_role;

            $sheet->setCellValue([1, $row], $log->created_at?->format('d.m.Y H:i:s'));
            $sheet->setCellValueExplicit([2, $row], (string) $log->registry_name, DataType::TYPE_STRING);
            $sheet->setCellValue([3, $row], $log->registry_amount !== null ? (float) $log->registry_amount : null);
            $sheet->setCellValue([4, $row], $event instanceof PaymentRegistryLogEvent ? $event->getLabel() : '');
            $sheet->setCellValue([5, $row], $log->actorLabel());
            $sheet->setCellValue([6, $row], $role instanceof SystemRole ? $role->getLabel() : 'Система');
            $sheet->setCellValueExplicit([7, $row], $log->changesText(), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([8, $row], (string) $log->ip, DataType::TYPE_STRING);

            $row++;
        });

        $lastRow = max($row - 1, 2);

        // Сумма — денежный формат; IP — текст, чтобы Excel не искажал значение.
        $sheet->getStyle('C2:C'.$lastRow)->getNumberFormat()->setFormatCode('# ##0.00');
        $sheet->getStyle('H2:H'.$lastRow)->getNumberFormat()->setFormatCode('@');

        // Описание изменений многострочное.
        $sheet->getStyle('G2:G'.$lastRow)->getAlignment()
            ->setWrapText(true)
            ->setVertical(Alignment::VERTICAL_TOP);

        if ($row > 2) {
            $sheet->getStyle('A1:'.$lastCol.($row - 1))
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        return $spreadsheet;
    }
}
