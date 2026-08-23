<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\PaymentPurpose;
use App\Enums\PaymentSettlement;
use App\Enums\PaymentStatus;
use App\Models\PaymentRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Сборка финансового отчёта за период по реестру платежей (ТЗ 3-го этапа, п. 4.5).
 *
 * Расходы в отчёт пока не попадают: реестр расходов (п. 4.4) хранит только
 * название и файл, сумм и групп там нет. Расходная часть выводится нулём
 * с пояснением — @see PeriodReport::hasExpenses(); когда реестр появится,
 * достаточно заполнить expenseTotal здесь.
 */
final class FinancialReportBuilder
{
    /** Ключ группы для платежей без указанного способа оплаты. */
    public const SETTLEMENT_UNKNOWN = 'unknown';

    /** Ключ строки для платежей без указанного назначения. */
    public const PURPOSE_UNKNOWN = 'unknown';

    public const EXPENSES_NOTE = 'Реестр расходов ведётся без сумм и групп (п. 4.4 ТЗ) — в отчёт включены только приходы.';

    /**
     * Форма расчёта в SQL: отдельной колонки нет, категория выводится
     * из способа оплаты (@see PaymentSettlement).
     */
    private const SETTLEMENT_CASE = "case when payment_method is null then 'unknown' when payment_method = 'cash' then 'cash' else 'cashless' end";

    public function build(PeriodReportFilters $filters, ?string $generatedBy = null): PeriodReport
    {
        $breakdown = $this->purposeBreakdown($filters);

        $purposeRows = $breakdown['rows'];
        $serviceRows = array_values(array_filter(
            $purposeRows,
            static fn (array $row): bool => $row['is_service'],
        ));

        return new PeriodReport(
            filters: $filters,
            purposeRows: $purposeRows,
            settlementTotals: $breakdown['settlements'],
            incomeTotal: $breakdown['total'],
            incomeCount: $breakdown['count'],
            serviceRows: $serviceRows,
            serviceTotal: array_sum(array_column($serviceRows, 'total')),
            serviceCount: (int) array_sum(array_column($serviceRows, 'count')),
            monthRows: $this->monthBreakdown($filters),
            expenseTotal: 0.0,
            expenseNote: self::EXPENSES_NOTE,
            generatedAt: Carbon::now(),
            generatedBy: $generatedBy,
        );
    }

    /**
     * Отобранные платежи без сортировки и связей — основа агрегатов.
     */
    public function query(PeriodReportFilters $filters): Builder
    {
        $column = $filters->dateBasis->column();

        return PaymentRegistry::query()
            ->whereBetween($column, [$filters->from, $filters->until])
            ->when(
                $filters->onlyConfirmed,
                fn (Builder $query): Builder => $query->whereNotNull('confirmed_at'),
                // Без подтверждения бухгалтера приход всё равно не считается
                // состоявшимся, если платёж отменён.
                fn (Builder $query): Builder => $query->where('status', '!=', PaymentStatus::Canceled->value),
            )
            ->when(
                $filters->settlement,
                fn (Builder $query, PaymentSettlement $settlement): Builder => $query
                    ->whereIn('payment_method', $settlement->methodValues()),
            )
            ->when(
                $filters->purposes !== [],
                fn (Builder $query): Builder => $query->whereIn('purpose', array_map(
                    static fn (PaymentPurpose $purpose): string => $purpose->value,
                    $filters->purposes,
                )),
            );
    }

    /**
     * Детальные строки отчёта «от кого и за что» — для предпросмотра и выгрузки.
     */
    public function rowsQuery(PeriodReportFilters $filters): Builder
    {
        return $this->query($filters)
            ->with(['regatta', 'yacht', 'team', 'confirmedBy'])
            ->orderBy($filters->dateBasis->column())
            // Стабилизирует порядок при постраничном обходе в экспорте.
            ->orderBy('id');
    }

    /** Только приходы раздела «Услуги» — отдельный учёт по требованию ТЗ. */
    public function serviceRowsQuery(PeriodReportFilters $filters): Builder
    {
        return $this->rowsQuery($filters)->whereIn('purpose', array_map(
            static fn (PaymentPurpose $purpose): string => $purpose->value,
            PaymentPurpose::serviceCases(),
        ));
    }

    /**
     * Приходы в разрезе «назначение × форма расчёта» одним запросом:
     * из него получаются и строки «за что», и итоги нал/безнал.
     *
     * @return array{rows: list<array<string, mixed>>, settlements: array{cash: float, cashless: float, unknown: float}, total: float, count: int}
     */
    private function purposeBreakdown(PeriodReportFilters $filters): array
    {
        // toBase(): нужны сырые значения колонок. У Eloquent-модели каст
        // превратил бы purpose в объект enum ещё до разбора строки агрегата.
        $aggregates = $this->query($filters)
            ->toBase()
            ->selectRaw('purpose, '.self::SETTLEMENT_CASE.' as settlement_key, count(*) as payments_count, sum(amount) as payments_sum')
            ->groupByRaw('purpose, '.self::SETTLEMENT_CASE)
            ->get();

        $rows = [];
        $settlements = [
            PaymentSettlement::Cash->value => 0.0,
            PaymentSettlement::Cashless->value => 0.0,
            self::SETTLEMENT_UNKNOWN => 0.0,
        ];
        $total = 0.0;
        $count = 0;

        foreach ($aggregates as $aggregate) {
            $purpose = PaymentPurpose::tryFrom((string) $aggregate->purpose);
            $key = $purpose?->value ?? self::PURPOSE_UNKNOWN;
            $settlementKey = (string) $aggregate->settlement_key;
            $sum = (float) $aggregate->payments_sum;
            $rowCount = (int) $aggregate->payments_count;

            $rows[$key] ??= [
                'key' => $key,
                'label' => $purpose?->label() ?? 'Не указано',
                'cash' => 0.0,
                'cashless' => 0.0,
                'unknown' => 0.0,
                'total' => 0.0,
                'count' => 0,
                'is_service' => $purpose?->isServiceIncome() ?? false,
            ];

            $rows[$key][$settlementKey] += $sum;
            $rows[$key]['total'] += $sum;
            $rows[$key]['count'] += $rowCount;

            $settlements[$settlementKey] += $sum;
            $total += $sum;
            $count += $rowCount;
        }

        return [
            'rows' => $this->sortByPurposeOrder($rows),
            'settlements' => $settlements,
            'total' => $total,
            'count' => $count,
        ];
    }

    /**
     * Приходы по месяцам периода.
     *
     * @return list<array{key: string, label: string, total: float, count: int}>
     */
    private function monthBreakdown(PeriodReportFilters $filters): array
    {
        $column = $filters->dateBasis->column();

        return $this->query($filters)
            ->toBase()
            ->selectRaw("date_format({$column}, '%Y-%m') as period, count(*) as payments_count, sum(amount) as payments_sum")
            ->groupByRaw("date_format({$column}, '%Y-%m')")
            ->orderByRaw("date_format({$column}, '%Y-%m')")
            ->get()
            ->map(static fn ($row): array => [
                'key' => (string) $row->period,
                'label' => Carbon::createFromFormat('Y-m', (string) $row->period)->translatedFormat('F Y'),
                'total' => (float) $row->payments_sum,
                'count' => (int) $row->payments_count,
            ])
            ->all();
    }

    /**
     * Порядок строк — как в справочнике назначений, «Не указано» в конце.
     *
     * @param  array<string, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sortByPurposeOrder(array $rows): array
    {
        $order = array_map(
            static fn (PaymentPurpose $purpose): string => $purpose->value,
            PaymentPurpose::cases(),
        );

        uasort($rows, static function (array $a, array $b) use ($order): int {
            $positionA = array_search($a['key'], $order, true);
            $positionB = array_search($b['key'], $order, true);

            return ($positionA === false ? PHP_INT_MAX : $positionA)
                <=> ($positionB === false ? PHP_INT_MAX : $positionB);
        });

        return array_values($rows);
    }
}
