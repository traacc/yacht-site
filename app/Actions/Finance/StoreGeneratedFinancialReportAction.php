<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Exports\FinancialReportExport;
use App\Models\FinancialReport;
use App\Services\Finance\PeriodReport;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Сохраняет сформированный отчёт за период в раздел «Финансовые отчёты»:
 * книга .xlsx кладётся на публичный диск и прикрепляется к новой записи.
 *
 * Так у бухгалтера остаётся история выгрузок рядом с отчётами,
 * загруженными вручную, — раздел один и тот же.
 */
final class StoreGeneratedFinancialReportAction
{
    public function __construct(private readonly FinancialReportExport $export) {}

    public function handle(PeriodReport $report, ?string $name = null): FinancialReport
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'financial-report-');

        try {
            $this->export->saveTo($report, $temporaryPath);

            $path = 'financial-reports/'.Str::uuid()->toString().'.xlsx';
            $stream = fopen($temporaryPath, 'rb');
            Storage::disk('public')->writeStream($path, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }

        return FinancialReport::create([
            'name' => $name ?: 'Финансовый отчёт за период '.$report->filters->periodLabel(),
            'document' => $path,
        ]);
    }
}
