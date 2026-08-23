{{-- Финансовый отчёт за период: приходы, отдельный блок «Услуги», расходы и итог. --}}
<x-filament-panels::page>
    {{ $this->content }}

    @php
        $report = $this->buildReport();
        $money = fn (float $value): string => number_format($value, 2, ',', ' ').' ₽';
    @endphp

    @if ($report === null)
        <div class="border border-gray-200 bg-white p-6 text-sm text-gray-500">
            Задайте период и нажмите «Сформировать отчёт» — ниже появятся приходы за период,
            отдельный итог по разделу «Услуги» и общий результат. Выгрузка в Excel станет
            доступна в шапке страницы.
        </div>
    @else
        {{-- Итоговые показатели --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="border border-gray-200 bg-white p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500">Приходы за период</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $money($report->incomeTotal) }}</div>
                <div class="mt-1 text-xs text-gray-500">Платежей: {{ $report->incomeCount }}</div>
            </div>

            <div class="border border-gray-200 bg-white p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500">В том числе «Услуги»</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $money($report->serviceTotal) }}</div>
                <div class="mt-1 text-xs text-gray-500">
                    Платежей: {{ $report->serviceCount }} · доля {{ $report->serviceShare() }} %
                </div>
            </div>

            <div class="border border-gray-200 bg-white p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500">Расходы за период</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $money($report->expenseTotal) }}</div>
                @unless ($report->hasExpenses())
                    <div class="mt-1 text-xs text-amber-700">Учёт расходов ещё не ведётся</div>
                @endunless
            </div>

            <div class="border border-gray-200 bg-white p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500">Итог</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $money($report->balance()) }}</div>
                <div class="mt-1 text-xs text-gray-500">Приходы − расходы</div>
            </div>
        </div>

        {{-- Параметры отбора --}}
        <div class="border border-gray-200 bg-white p-4 text-sm">
            <div class="grid grid-cols-1 gap-x-6 gap-y-1 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($report->filters->summaryLines() as $label => $value)
                    <div class="flex justify-between gap-4 border-b border-gray-100 py-1">
                        <span class="text-gray-500">{{ $label }}</span>
                        <span class="text-right font-medium text-gray-800">{{ $value }}</span>
                    </div>
                @endforeach
                <div class="flex justify-between gap-4 border-b border-gray-100 py-1">
                    <span class="text-gray-500">Сформирован</span>
                    <span class="text-right font-medium text-gray-800">
                        {{ $report->generatedAt->format('d.m.Y H:i') }}
                        @if ($report->generatedBy)
                            , {{ $report->generatedBy }}
                        @endif
                    </span>
                </div>
            </div>
        </div>

        @if ($report->isEmpty())
            <div class="border border-gray-200 bg-white p-6 text-sm text-gray-500">
                За выбранный период приходов нет. Проверьте базу даты и отметку «Только подтверждённые приходы».
            </div>
        @else
            {{-- Приходы по назначениям --}}
            <div class="border border-gray-200 bg-white">
                <div class="border-b border-gray-200 px-4 py-3 text-lg font-semibold text-gray-800">
                    Приходы — за что
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2">Назначение</th>
                                <th class="px-4 py-2 text-right">Наличные</th>
                                <th class="px-4 py-2 text-right">Безналичные</th>
                                <th class="px-4 py-2 text-right">Способ не указан</th>
                                <th class="px-4 py-2 text-right">Всего</th>
                                <th class="px-4 py-2 text-right">Платежей</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($report->purposeRows as $row)
                                <tr class="border-t border-gray-100">
                                    <td class="px-4 py-2">
                                        {{ $row['label'] }}
                                        @if ($row['is_service'])
                                            <span class="ml-1 bg-sky-100 px-1.5 py-0.5 text-xs text-sky-700">Услуги</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right">{{ $money($row['cash']) }}</td>
                                    <td class="px-4 py-2 text-right">{{ $money($row['cashless']) }}</td>
                                    <td class="px-4 py-2 text-right">{{ $money($row['unknown']) }}</td>
                                    <td class="px-4 py-2 text-right font-medium">{{ $money($row['total']) }}</td>
                                    <td class="px-4 py-2 text-right">{{ $row['count'] }}</td>
                                </tr>
                            @endforeach
                            <tr class="border-t border-gray-200 bg-gray-50 font-semibold">
                                <td class="px-4 py-2">ИТОГО ПРИХОДЫ</td>
                                <td class="px-4 py-2 text-right">{{ $money($report->settlementTotals['cash']) }}</td>
                                <td class="px-4 py-2 text-right">{{ $money($report->settlementTotals['cashless']) }}</td>
                                <td class="px-4 py-2 text-right">{{ $money($report->settlementTotals['unknown']) }}</td>
                                <td class="px-4 py-2 text-right">{{ $money($report->incomeTotal) }}</td>
                                <td class="px-4 py-2 text-right">{{ $report->incomeCount }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Отдельный учёт раздела «Услуги» --}}
            <div class="border border-gray-200 bg-white">
                <div class="border-b border-gray-200 px-4 py-3 text-lg font-semibold text-gray-800">
                    Отдельный учёт раздела «Услуги»
                </div>

                @if ($report->serviceRows === [])
                    <p class="px-4 py-4 text-sm text-gray-500">Приходов по разделу «Услуги» за период нет.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-2">Услуга</th>
                                    <th class="px-4 py-2 text-right">Наличные</th>
                                    <th class="px-4 py-2 text-right">Безналичные</th>
                                    <th class="px-4 py-2 text-right">Всего</th>
                                    <th class="px-4 py-2 text-right">Платежей</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($report->serviceRows as $row)
                                    <tr class="border-t border-gray-100">
                                        <td class="px-4 py-2">{{ $row['label'] }}</td>
                                        <td class="px-4 py-2 text-right">{{ $money($row['cash']) }}</td>
                                        <td class="px-4 py-2 text-right">{{ $money($row['cashless'] + $row['unknown']) }}</td>
                                        <td class="px-4 py-2 text-right font-medium">{{ $money($row['total']) }}</td>
                                        <td class="px-4 py-2 text-right">{{ $row['count'] }}</td>
                                    </tr>
                                @endforeach
                                <tr class="border-t border-gray-200 bg-gray-50 font-semibold">
                                    <td class="px-4 py-2">ИТОГО УСЛУГИ</td>
                                    <td class="px-4 py-2 text-right">
                                        {{ $money(array_sum(array_column($report->serviceRows, 'cash'))) }}
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        {{ $money(array_sum(array_column($report->serviceRows, 'cashless')) + array_sum(array_column($report->serviceRows, 'unknown'))) }}
                                    </td>
                                    <td class="px-4 py-2 text-right">{{ $money($report->serviceTotal) }}</td>
                                    <td class="px-4 py-2 text-right">{{ $report->serviceCount }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Помесячная разбивка --}}
            @if (count($report->monthRows) > 1)
                <div class="border border-gray-200 bg-white">
                    <div class="border-b border-gray-200 px-4 py-3 text-lg font-semibold text-gray-800">
                        Приходы по месяцам
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-2">Месяц</th>
                                    <th class="px-4 py-2 text-right">Сумма</th>
                                    <th class="px-4 py-2 text-right">Платежей</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($report->monthRows as $month)
                                    <tr class="border-t border-gray-100">
                                        <td class="px-4 py-2">{{ $month['label'] }}</td>
                                        <td class="px-4 py-2 text-right">{{ $money($month['total']) }}</td>
                                        <td class="px-4 py-2 text-right">{{ $month['count'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Детализация: от кого и за что --}}
            @php
                $rows = $this->previewRows();
                $limit = \App\Filament\Pages\FinancialPeriodReport::PREVIEW_LIMIT;
            @endphp

            <div class="border border-gray-200 bg-white">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-4 py-3">
                    <span class="text-lg font-semibold text-gray-800">Приходы — от кого и за что</span>
                    @if ($report->incomeCount > $limit)
                        <span class="text-xs text-gray-500">
                            Показаны первые {{ $limit }} из {{ $report->incomeCount }} — полный список в выгрузке Excel
                        </span>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2">Дата оплаты</th>
                                <th class="px-4 py-2">От кого</th>
                                <th class="px-4 py-2">За что</th>
                                <th class="px-4 py-2 text-right">Сумма</th>
                                <th class="px-4 py-2">Форма расчёта</th>
                                <th class="px-4 py-2">Приход</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $payment)
                                <tr class="border-t border-gray-100 align-top">
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        {{ $payment->paid_at?->format('d.m.Y H:i') ?? '—' }}
                                    </td>
                                    <td class="px-4 py-2">{{ $payment->payerLabel() }}</td>
                                    <td class="px-4 py-2">
                                        {{ $payment->purposeLabel() }}
                                        <div class="text-xs text-gray-500">
                                            {{ $payment->name }}
                                            @if ($payment->regatta)
                                                · {{ $payment->regattaLabel() }}
                                            @endif
                                            @if ($payment->yacht)
                                                · {{ $payment->yachtLabel() }}
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 text-right whitespace-nowrap">{{ $money((float) $payment->amount) }}</td>
                                    <td class="px-4 py-2">{{ $payment->settlement()?->label() ?? '—' }}</td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        {{ $payment->confirmed_at?->format('d.m.Y H:i') ?? 'Не подтверждён' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @unless ($report->hasExpenses())
            <div class="border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                {{ $report->expenseNote }}
            </div>
        @endunless
    @endif
</x-filament-panels::page>
