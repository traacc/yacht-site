<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\PaymentRegistry;
use App\Services\Finance\FinancialReportBuilder;
use App\Services\Finance\PeriodReport;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Выгрузка финансового отчёта за период в .xlsx (ТЗ 3-го этапа, п. 4.5).
 *
 * Три листа: «Сводка» (итоги по назначениям, форме расчёта, месяцам,
 * отдельный блок «Услуги», расходы и итог), «Приходы» (все платежи периода
 * «от кого и за что») и «Услуги» (только приходы этого раздела).
 */
class FinancialReportExport
{
    /**
     * Разделитель разрядов оставляем запятой: Excel подставляет свой
     * по локали пользователя. Литеральный пробел («# ##0.00») ломает
     * вывод — PhpSpreadsheet печатает им 12,5 как «0 012,50».
     */
    private const MONEY_FORMAT = '#,##0.00';

    private const HEADER_FILL = 'E2E8F0';

    private const SECTION_FILL = 'F1F5F9';

    /** @var array<string, string> Заголовок → ширина колонки листа детализации. */
    private const DETAIL_COLUMNS = [
        'Дата оплаты' => 18,
        'Приход подтверждён' => 20,
        'От кого' => 30,
        'За что' => 24,
        'Название платежа' => 40,
        'Сумма' => 16,
        'Форма расчёта' => 16,
        'Способ оплаты' => 20,
        'Статус' => 18,
        'Команда' => 26,
        'Яхта' => 26,
        'Регата' => 30,
    ];

    public function __construct(private readonly FinancialReportBuilder $builder) {}

    public function download(PeriodReport $report): StreamedResponse
    {
        $spreadsheet = $this->build($report);

        $response = new StreamedResponse(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$report->filters->filename().'"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    /** Сохранить книгу во временный файл (для прикрепления к отчёту в реестре). */
    public function saveTo(PeriodReport $report, string $path): void
    {
        (new Xlsx($this->build($report)))->save($path);
    }

    public function build(PeriodReport $report): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;

        $this->writeSummary($spreadsheet->getActiveSheet(), $report);

        $this->writeDetails(
            $spreadsheet->createSheet(),
            'Приходы',
            $this->builder->rowsQuery($report->filters),
        );

        $this->writeDetails(
            $spreadsheet->createSheet(),
            'Услуги',
            $this->builder->serviceRowsQuery($report->filters),
        );

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    // ──────────────────────────────────────────────
    // Лист «Сводка»
    // ──────────────────────────────────────────────

    private function writeSummary(Worksheet $sheet, PeriodReport $report): void
    {
        $sheet->setTitle('Сводка');

        foreach (['A' => 42, 'B' => 18, 'C' => 18, 'D' => 18, 'E' => 18, 'F' => 12] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $row = 1;
        $sheet->setCellValue([1, $row], 'Финансовый отчёт за период');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
        $row += 2;

        foreach ($report->filters->summaryLines() as $label => $value) {
            $sheet->setCellValue([1, $row], $label);
            $sheet->setCellValueExplicit([2, $row], $value, DataType::TYPE_STRING);
            $sheet->mergeCells([2, $row, 6, $row]);
            $row++;
        }

        $sheet->setCellValue([1, $row], 'Отчёт сформирован');
        $sheet->setCellValue([2, $row], $report->generatedAt->format('d.m.Y H:i')
            .($report->generatedBy !== null ? ', '.$report->generatedBy : ''));
        $sheet->mergeCells([2, $row, 6, $row]);
        $row += 2;

        // ── Приходы по назначениям ────────────────
        $row = $this->writeSectionTitle($sheet, $row, 'Приходы — от кого и за что');
        $row = $this->writePurposeTable($sheet, $row, $report->purposeRows, 'ИТОГО ПРИХОДЫ', [
            'cash' => $report->settlementTotals['cash'],
            'cashless' => $report->settlementTotals['cashless'],
            'unknown' => $report->settlementTotals['unknown'],
            'total' => $report->incomeTotal,
            'count' => $report->incomeCount,
        ]);
        $row++;

        // ── Отдельный учёт раздела «Услуги» ───────
        $row = $this->writeSectionTitle($sheet, $row, 'В том числе раздел «Услуги»');

        if ($report->serviceRows === []) {
            $sheet->setCellValue([1, $row], 'Приходов по разделу «Услуги» за период нет');
            $row += 2;
        } else {
            $row = $this->writePurposeTable($sheet, $row, $report->serviceRows, 'ИТОГО УСЛУГИ', [
                'cash' => array_sum(array_column($report->serviceRows, 'cash')),
                'cashless' => array_sum(array_column($report->serviceRows, 'cashless')),
                'unknown' => array_sum(array_column($report->serviceRows, 'unknown')),
                'total' => $report->serviceTotal,
                'count' => $report->serviceCount,
            ]);

            $sheet->setCellValue([1, $row], 'Доля раздела «Услуги» в приходах');
            $sheet->setCellValue([2, $row], $report->serviceShare().' %');
            $row += 2;
        }

        // ── По месяцам ────────────────────────────
        if ($report->monthRows !== []) {
            $row = $this->writeSectionTitle($sheet, $row, 'Приходы по месяцам');
            $row = $this->writeHeaderRow($sheet, $row, ['Месяц', 'Сумма', 'Платежей']);

            foreach ($report->monthRows as $month) {
                $sheet->setCellValue([1, $row], $month['label']);
                $this->money($sheet, 2, $row, $month['total']);
                $sheet->setCellValue([3, $row], $month['count']);
                $row++;
            }

            $row++;
        }

        // ── Расходы ───────────────────────────────
        $row = $this->writeSectionTitle($sheet, $row, 'Расходы');
        $sheet->setCellValue([1, $row], 'Всего расходов');
        $this->money($sheet, 2, $row, $report->expenseTotal);
        $row++;

        if ($report->expenseNote !== null) {
            $sheet->setCellValue([1, $row], $report->expenseNote);
            $sheet->mergeCells([1, $row, 6, $row]);
            $sheet->getStyle("A{$row}")->getFont()->setItalic(true);
            $row++;
        }

        $row++;

        // ── Итог ──────────────────────────────────
        $sheet->setCellValue([1, $row], 'ИТОГ (приходы − расходы)');
        $this->money($sheet, 2, $row, $report->balance());
        $sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_FILL]],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{cash: float, cashless: float, unknown: float, total: float, count: int}  $totals
     */
    private function writePurposeTable(Worksheet $sheet, int $row, array $rows, string $totalLabel, array $totals): int
    {
        $row = $this->writeHeaderRow($sheet, $row, [
            'Назначение', 'Наличные', 'Безналичные', 'Способ не указан', 'Всего', 'Платежей',
        ]);

        foreach ($rows as $purpose) {
            $sheet->setCellValue([1, $row], $purpose['label']);
            $this->money($sheet, 2, $row, $purpose['cash']);
            $this->money($sheet, 3, $row, $purpose['cashless']);
            $this->money($sheet, 4, $row, $purpose['unknown']);
            $this->money($sheet, 5, $row, $purpose['total']);
            $sheet->setCellValue([6, $row], $purpose['count']);
            $row++;
        }

        $sheet->setCellValue([1, $row], $totalLabel);
        $this->money($sheet, 2, $row, $totals['cash']);
        $this->money($sheet, 3, $row, $totals['cashless']);
        $this->money($sheet, 4, $row, $totals['unknown']);
        $this->money($sheet, 5, $row, $totals['total']);
        $sheet->setCellValue([6, $row], $totals['count']);
        $sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::SECTION_FILL]],
        ]);

        return $row + 2;
    }

    private function writeSectionTitle(Worksheet $sheet, int $row, string $title): int
    {
        $sheet->setCellValue([1, $row], $title);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);

        return $row + 1;
    }

    /** @param  list<string>  $titles */
    private function writeHeaderRow(Worksheet $sheet, int $row, array $titles): int
    {
        foreach ($titles as $index => $title) {
            $sheet->setCellValue([$index + 1, $row], $title);
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count($titles));
        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_FILL]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        return $row + 1;
    }

    private function money(Worksheet $sheet, int $column, int $row, float $value): void
    {
        $sheet->setCellValue([$column, $row], $value);
        $sheet->getStyle([$column, $row])->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);
    }

    // ──────────────────────────────────────────────
    // Листы детализации
    // ──────────────────────────────────────────────

    private function writeDetails(Worksheet $sheet, string $title, Builder $query): void
    {
        $sheet->setTitle($title);

        $column = 1;
        foreach (self::DETAIL_COLUMNS as $header => $width) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth($width);
            $sheet->setCellValue([$column, 1], $header);
            $column++;
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count(self::DETAIL_COLUMNS));
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_FILL]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->freezePane('A2');

        $row = 2;
        $total = 0.0;

        $query->each(function (PaymentRegistry $payment) use ($sheet, &$row, &$total): void {
            $amount = (float) $payment->amount;

            $sheet->setCellValue([1, $row], $payment->paid_at?->format('d.m.Y H:i'));
            $sheet->setCellValue([2, $row], $payment->confirmed_at?->format('d.m.Y H:i'));
            $sheet->setCellValueExplicit([3, $row], $payment->payerLabel(), DataType::TYPE_STRING);
            $sheet->setCellValue([4, $row], $payment->purposeLabel());
            $sheet->setCellValueExplicit([5, $row], (string) $payment->name, DataType::TYPE_STRING);
            $this->money($sheet, 6, $row, $amount);
            $sheet->setCellValue([7, $row], $payment->settlement()?->label() ?? '—');
            $sheet->setCellValue([8, $row], $payment->payment_method?->label() ?? '—');
            $sheet->setCellValue([9, $row], $payment->status?->label() ?? '');
            $sheet->setCellValue([10, $row], $payment->team?->name ?? '');
            $sheet->setCellValue([11, $row], $payment->yacht !== null ? $payment->yachtLabel() : '');
            $sheet->setCellValue([12, $row], $payment->regatta?->name ?? '');

            $total += $amount;
            $row++;
        });

        $sheet->setCellValue([5, $row], 'ВСЕГО ('.($row - 2).' шт.)');
        $this->money($sheet, 6, $row, $total);
        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_FILL]],
        ]);

        $sheet->getStyle("A1:{$lastColumn}{$row}")
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }
}
