<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\FinancialDateBasis;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentSettlement;
use Illuminate\Support\Carbon;

/**
 * Параметры финансового отчёта за период.
 *
 * Отдельный объект, а не массив из формы: отчёт строится и из админки,
 * и из экспорта, и (в перспективе) из планировщика — набор полей должен
 * быть один и валидироваться в одном месте.
 */
final readonly class PeriodReportFilters
{
    /**
     * @param  list<PaymentPurpose>  $purposes  пустой список — все назначения
     */
    public function __construct(
        public Carbon $from,
        public Carbon $until,
        public FinancialDateBasis $dateBasis = FinancialDateBasis::PaidAt,
        public bool $onlyConfirmed = true,
        public ?PaymentSettlement $settlement = null,
        public array $purposes = [],
    ) {}

    /**
     * Сборка из состояния формы Filament.
     *
     * Select с options(Enum::class) кладёт в состояние объект enum, а не строку,
     * поэтому каждое значение приводится через нормализацию.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $purposes = array_values(array_filter(array_map(
            static fn (mixed $value): ?PaymentPurpose => self::enum(PaymentPurpose::class, $value),
            (array) ($data['purposes'] ?? []),
        )));

        return new self(
            from: Carbon::parse($data['from'])->startOfDay(),
            until: Carbon::parse($data['until'])->endOfDay(),
            dateBasis: self::enum(FinancialDateBasis::class, $data['date_basis'] ?? null)
                ?? FinancialDateBasis::PaidAt,
            // Отбор по дате подтверждения сам по себе отсекает неподтверждённые.
            onlyConfirmed: (bool) ($data['only_confirmed'] ?? true),
            settlement: self::enum(PaymentSettlement::class, $data['settlement'] ?? null),
            purposes: $purposes,
        );
    }

    /** «01.01.2026 — 31.03.2026» для заголовков отчёта и имени файла. */
    public function periodLabel(): string
    {
        return $this->from->format('d.m.Y').' — '.$this->until->format('d.m.Y');
    }

    /** Человекочитаемое описание применённых условий отбора. */
    public function summaryLines(): array
    {
        return [
            'Период' => $this->periodLabel(),
            'База даты' => $this->dateBasis->label(),
            'Приходы' => $this->onlyConfirmed
                ? 'Только подтверждённые бухгалтером'
                : 'Все, кроме отменённых',
            'Форма расчёта' => $this->settlement?->label() ?? 'Наличные и безналичные',
            'Назначения' => $this->purposes === []
                ? 'Все'
                : implode(', ', array_map(
                    static fn (PaymentPurpose $purpose): string => $purpose->label(),
                    $this->purposes,
                )),
        ];
    }

    /** Имя файла выгрузки: financial-report_2026-01-01_2026-03-31.xlsx */
    public function filename(): string
    {
        return 'financial-report_'
            .$this->from->format('Y-m-d').'_'
            .$this->until->format('Y-m-d').'.xlsx';
    }

    /**
     * @template T of \BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T|null
     */
    private static function enum(string $enum, mixed $value): ?object
    {
        if ($value instanceof $enum) {
            return $value;
        }

        return blank($value) ? null : $enum::tryFrom((string) $value);
    }
}
