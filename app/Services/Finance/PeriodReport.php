<?php

declare(strict_types=1);

namespace App\Services\Finance;

use Illuminate\Support\Carbon;

/**
 * Готовый финансовый отчёт за период: приходы «от кого и за что»,
 * отдельный учёт раздела «Услуги», расходы и итог.
 *
 * Только агрегаты — детальные строки экспорт и предпросмотр берут
 * запросом @see FinancialReportBuilder::rowsQuery(), чтобы не держать
 * в памяти весь реестр за квартал.
 *
 * @phpstan-type PurposeRow array{key: string, label: string, cash: float, cashless: float, unknown: float, total: float, count: int, is_service: bool}
 * @phpstan-type MonthRow array{key: string, label: string, total: float, count: int}
 */
final readonly class PeriodReport
{
    /**
     * @param  list<PurposeRow>  $purposeRows  приходы по назначениям («за что»)
     * @param  array{cash: float, cashless: float, unknown: float}  $settlementTotals
     * @param  list<PurposeRow>  $serviceRows  подмножество приходов раздела «Услуги»
     * @param  list<MonthRow>  $monthRows
     */
    public function __construct(
        public PeriodReportFilters $filters,
        public array $purposeRows,
        public array $settlementTotals,
        public float $incomeTotal,
        public int $incomeCount,
        public array $serviceRows,
        public float $serviceTotal,
        public int $serviceCount,
        public array $monthRows,
        public float $expenseTotal,
        public ?string $expenseNote,
        public Carbon $generatedAt,
        public ?string $generatedBy,
    ) {}

    /** Итог отчёта: приходы минус расходы. */
    public function balance(): float
    {
        return $this->incomeTotal - $this->expenseTotal;
    }

    /** Доля раздела «Услуги» в приходах, %. */
    public function serviceShare(): float
    {
        return $this->incomeTotal > 0.0
            ? round($this->serviceTotal / $this->incomeTotal * 100, 1)
            : 0.0;
    }

    public function isEmpty(): bool
    {
        return $this->incomeCount === 0;
    }

    /** Учёт расходов ещё не ведётся (реестр расходов — п. 4.4 ТЗ). */
    public function hasExpenses(): bool
    {
        return $this->expenseNote === null;
    }
}
