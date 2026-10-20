<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $documentTitle }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9px;
            color: #1a1a2e;
            background: #ffffff;
            padding: 18px 22px;
        }

        .page-break {
            page-break-before: always;
        }

        /* ── Шапка ── */
        .header {
            width: 100%;
            margin-bottom: 10px;
            border-collapse: collapse;
        }

        .header td {
            vertical-align: middle;
            color: #2E325C;
        }

        .header .logo-cell {
            text-align: left;
            width: 200px;
        }

        .header .logo-cell img {
            width: 180px;
        }

        .header .title-cell {
            text-align: right;
        }

        .header .title {
            font-size: 14px;
            font-weight: bold;
        }

        .header .subtitle {
            font-size: 9px;
            color: #6b7280;
            margin-top: 2px;
        }

        /* ── Легенда: номер колонки → регата ── */
        .legend {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .legend td {
            width: 33.33%;
            padding: 1.5px 4px 1.5px 0;
            font-size: 8px;
            vertical-align: top;
        }

        .legend .num {
            font-weight: bold;
            color: #2D92CE;
        }

        .legend .dates {
            color: #6b7280;
        }

        /* ── Таблица рейтинга ── */
        .standings {
            width: 100%;
            border-collapse: collapse;
        }

        .standings th {
            padding: 4px 3px;
            font-size: 8px;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            color: #ffffff;
            background-color: #2E325C;
            border: 1px solid #2E325C;
        }

        .standings td {
            padding: 2px 3px;
            font-size: 8px;
            text-align: center;
            vertical-align: middle;
            border: 1px solid #d1d5db;
        }

        .standings tr {
            page-break-inside: avoid;
        }

        .standings tbody tr:nth-child(even) td {
            background-color: #f3f6fa;
        }

        .standings .team {
            text-align: left;
            font-weight: bold;
        }

        .standings .rank,
        .standings .total {
            font-weight: bold;
        }

        /* Специфичность как у «зебры» выше — побеждает за счёт порядка. */
        .standings tbody tr td.total {
            background-color: #daf2d0;
        }

        .standings .points {
            color: #6b7280;
        }

        .standings .muted {
            color: #9ca3af;
        }

        /* Много регат — ужимаем колонки: очки уходят на вторую строку ячейки. */
        .dense th,
        .dense td {
            font-size: 7px;
            padding: 2px 1px;
        }

        .empty {
            margin-top: 30px;
            font-size: 11px;
            color: #6b7280;
            text-align: center;
        }

        .footer {
            margin-top: 10px;
            text-align: right;
            font-size: 7.5px;
            color: #9ca3af;
        }
    </style>
</head>
<body>

    @forelse($tables as $table)
        @php
            $regattas = $table['regattas'];
            $dense = count($regattas) > 14;
        @endphp

        <div @class(['page-break' => ! $loop->first])>
            {{-- Шапка --}}
            <table class="header">
                <tr>
                    <td class="logo-cell">
                        <img src="{{ public_path('images/logo-pdf.svg') }}" alt="logo">
                    </td>
                    <td class="title-cell">
                        <div class="title">{{ $table['title'] }}</div>
                        <div class="subtitle">Сезон {{ $season->year }} · по состоянию на {{ now()->format('d.m.Y') }}</div>
                    </td>
                </tr>
            </table>

            {{-- Легенда --}}
            <table class="legend">
                @foreach(array_chunk($regattas, 3) as $chunk)
                    <tr>
                        @foreach($chunk as $regatta)
                            <td>
                                <span class="num">{{ $regatta['number'] }}</span> — {{ $regatta['name'] }}@if($regatta['dates'])<span class="dates">, {{ $regatta['dates'] }}</span>@endif
                            </td>
                        @endforeach
                        @for($i = count($chunk); $i < 3; $i++)
                            <td></td>
                        @endfor
                    </tr>
                @endforeach
            </table>

            {{-- Таблица: место (очки) за каждую регату и итог --}}
            <table @class(['standings', 'dense' => $dense])>
                <thead>
                    <tr>
                        <th style="width: 30px;">Место</th>
                        <th>Команда</th>
                        @foreach($regattas as $regatta)
                            <th>{{ $regatta['number'] }}</th>
                        @endforeach
                        <th style="width: 42px;">Итого</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($table['standings'] as $row)
                        <tr>
                            <td class="rank">{{ $row['rank'] }}</td>
                            <td class="team">{{ $row['name'] }}</td>
                            @foreach($regattas as $regatta)
                                @php $cell = $row['cells'][$regatta['id']] ?? null; @endphp
                                <td>
                                    @if($cell)
                                        {{ implode(', ', $cell['places']) }}{!! $dense ? '<br>' : ' ' !!}<span class="points">({{ $cell['points'] }})</span>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="total">{{ $row['total'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="footer">
                В ячейке — место команды в регате, в скобках — рейтинговые очки. Сформировано автоматически {{ now()->format('d.m.Y H:i') }}
            </div>
        </div>
    @empty
        <table class="header">
            <tr>
                <td class="logo-cell">
                    <img src="{{ public_path('images/logo-pdf.svg') }}" alt="logo">
                </td>
                <td class="title-cell">
                    <div class="title">{{ $documentTitle }}</div>
                </td>
            </tr>
        </table>
        <div class="empty">Результатов за сезон {{ $season->year }} пока нет.</div>
    @endforelse

</body>
</html>
