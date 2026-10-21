{{-- Список участников в окне админки: сезон («Личные рейтинги» → «Участники сезона»,
     $regattas — колонки регат) или регата («Регаты» → «Участники», $regattas = null). --}}
@php
    $points = fn (float $value): string => rtrim(rtrim(number_format($value, 3, ',', ''), '0'), ',');
    $dash = '<span class="text-gray-400">—</span>';
@endphp

<div class="space-y-4 text-sm">
    <div>Всего участников: <span class="font-semibold">{{ count($rows) }}</span></div>

    @if ($regattas)
        <ol class="grid gap-x-6 gap-y-1 text-xs sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($regattas as $regatta)
                <li>
                    <span class="font-semibold text-primary-600">{{ $regatta['number'] }}</span> — {{ $regatta['name'] }}@if ($regatta['dates'])<span class="text-gray-500">, {{ $regatta['dates'] }}</span>@endif
                </li>
            @endforeach
        </ol>
    @endif

    @if ($rows === [])
        <div class="py-6 text-center text-gray-500">Участников пока нет.</div>
    @else
        <div class="max-h-[65vh] overflow-auto rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full text-xs">
                <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                    <tr class="text-gray-700 dark:text-gray-200">
                        <th class="px-2 py-2 text-center">№</th>
                        <th class="px-2 py-2 text-left">ФИО</th>
                        <th class="px-2 py-2 text-center whitespace-nowrap">Дата рождения</th>
                        <th class="px-2 py-2 text-left">Команда</th>
                        @if ($regattas !== null)
                            @foreach ($regattas as $regatta)
                                <th class="px-2 py-2 text-center" title="{{ $regatta['name'] }}">{{ $regatta['number'] }}</th>
                            @endforeach
                            <th class="px-2 py-2 text-center">Сумма</th>
                            <th class="px-2 py-2 text-center">Место</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($rows as $row)
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                            <td class="px-2 py-1.5 text-center text-gray-500">{{ $loop->iteration }}</td>
                            <td class="px-2 py-1.5 font-medium whitespace-nowrap">{{ $row['name'] }}</td>
                            <td class="px-2 py-1.5 text-center">{!! $row['birth_date']?->format('d.m.Y') ?? $dash !!}</td>
                            <td class="px-2 py-1.5">{!! $row['team'] !== null ? e($row['team']) : $dash !!}</td>
                            @if ($regattas !== null)
                                @foreach ($regattas as $regatta)
                                    @php $cell = $row['cells'][$regatta['id']]; @endphp
                                    <td class="px-2 py-1.5 text-center">{!! $cell !== null ? $points($cell) : $dash !!}</td>
                                @endforeach
                                <td class="px-2 py-1.5 text-center font-semibold">{{ $points($row['total']) }}</td>
                                <td class="px-2 py-1.5 text-center font-semibold">{!! $row['rank'] ?? $dash !!}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
