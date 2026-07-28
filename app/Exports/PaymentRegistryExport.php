<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\PaymentRegistry;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Экспорт реестра платежей в .xlsx с учётом фильтров и активной группировки:
 * строки платежей, подытог после каждой группы и общий итог.
 *
 * Группировка передаётся замыканиями, а не объектом Filament — app/Exports
 * не должен зависеть от слоя админки.
 */
class PaymentRegistryExport
{
    /** @var array<string, string> Заголовок → буква колонки. */
    private const COLUMNS = [
        'Дата оплаты' => 'A',
        'Название' => 'B',
        'Назначение' => 'C',
        'Сумма' => 'D',
        'Статус' => 'E',
        'Приход подтверждён' => 'F',
        'Способ оплаты' => 'G',
        'Форма расчёта' => 'H',
        'Плательщик' => 'I',
        'Команда' => 'J',
        'Яхта' => 'K',
        'Регата' => 'L',
        'Создан' => 'M',
    ];

    private const COLUMN_WIDTHS = [
        'A' => 18, 'B' => 40, 'C' => 22, 'D' => 16, 'E' => 18, 'F' => 20, 'G' => 20,
        'H' => 16, 'I' => 28, 'J' => 26, 'K' => 26, 'L' => 30, 'M' => 18,
    ];

    public function download(
        Builder $query,
        ?Closure $groupKeyUsing = null,
        ?Closure $groupTitleUsing = null,
        ?string $groupLabel = null,
        string $filename = 'payments.xlsx',
    ): StreamedResponse {
        $spreadsheet = $this->build($query, $groupKeyUsing, $groupTitleUsing, $groupLabel);

        $response = new StreamedResponse(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    protected function build(
        Builder $query,
        ?Closure $groupKeyUsing,
        ?Closure $groupTitleUsing,
        ?string $groupLabel,
    ): Spreadsheet {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Реестр платежей');

        foreach (self::COLUMN_WIDTHS as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

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
        $sheet->freezePane('A2');

        $row = 2;
        $totalSum = 0.0;
        $totalCount = 0;
        $groupSum = 0.0;
        $groupCount = 0;
        $previousKey = null;
        $previousTitle = null;
        $isFirst = true;

        // orderBy('id') стабилизирует порядок: each() использует offset-пагинацию,
        // и при одинаковых значениях сортировки строки могли бы дублироваться.
        $query->orderBy('id')->each(function (PaymentRegistry $registry) use (
            $sheet, $groupKeyUsing, $groupTitleUsing, $groupLabel,
            &$row, &$totalSum, &$totalCount, &$groupSum, &$groupCount,
            &$previousKey, &$previousTitle, &$isFirst
        ): void {
            $key = $groupKeyUsing !== null ? $groupKeyUsing($registry) : null;

            if ($groupKeyUsing !== null && ! $isFirst && $key !== $previousKey) {
                $this->writeSubtotal($sheet, $row, $groupLabel, $previousTitle, $groupSum, $groupCount);
                $row++;
                $groupSum = 0.0;
                $groupCount = 0;
            }

            $this->writeRow($sheet, $row, $registry);

            $amount = (float) $registry->amount;
            $groupSum += $amount;
            $groupCount++;
            $totalSum += $amount;
            $totalCount++;

            $previousKey = $key;
            $previousTitle = $groupTitleUsing !== null ? $groupTitleUsing($registry) : null;
            $isFirst = false;
            $row++;
        });

        if ($groupKeyUsing !== null && ! $isFirst) {
            $this->writeSubtotal($sheet, $row, $groupLabel, $previousTitle, $groupSum, $groupCount);
            $row++;
        }

        // Общий итог
        $sheet->setCellValue([2, $row], 'ВСЕГО'.($totalCount > 0 ? " ({$totalCount} шт.)" : ''));
        $sheet->setCellValue([4, $row], $totalSum);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0'],
            ],
        ]);

        $sheet->getStyle('D2:D'.$row)->getNumberFormat()->setFormatCode('# ##0.00');
        $sheet->getStyle('A1:'.$lastCol.$row)
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        return $spreadsheet;
    }

    private function writeRow(mixed $sheet, int $row, PaymentRegistry $registry): void
    {
        $sheet->setCellValue([1, $row], $registry->paid_at?->format('d.m.Y H:i'));
        $sheet->setCellValueExplicit([2, $row], (string) $registry->name, DataType::TYPE_STRING);
        $sheet->setCellValue([3, $row], $registry->purposeLabel());
        $sheet->setCellValue([4, $row], (float) $registry->amount);
        $sheet->setCellValue([5, $row], $registry->status?->label() ?? '');
        $sheet->setCellValue([6, $row], $registry->isConfirmed()
            ? 'Да ('.$registry->confirmed_at?->format('d.m.Y H:i').')'
            : 'Нет');
        $sheet->setCellValue([7, $row], $registry->payment_method?->label() ?? '');
        $sheet->setCellValue([8, $row], $registry->settlement()?->label() ?? '');
        $sheet->setCellValue([9, $row], (string) $registry->payer_name);
        $sheet->setCellValue([10, $row], $registry->team?->name ?? '');
        $sheet->setCellValue([11, $row], $registry->yacht !== null ? $registry->yachtLabel() : '');
        $sheet->setCellValue([12, $row], $registry->regatta?->name ?? '');
        $sheet->setCellValue([13, $row], $registry->created_at?->format('d.m.Y H:i'));
    }

    private function writeSubtotal(
        mixed $sheet,
        int $row,
        ?string $groupLabel,
        ?string $title,
        float $sum,
        int $count,
    ): void {
        $label = trim(($groupLabel ? "{$groupLabel}: " : '').($title ?? '—'));

        $sheet->setCellValue([2, $row], "Итого — {$label} ({$count} шт.)");
        $sheet->setCellValue([4, $row], $sum);

        $columns = self::COLUMNS;
        $lastCol = end($columns);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F1F5F9'],
            ],
        ]);
    }
}
