<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title }}</title>
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

        .summary {
            margin-bottom: 8px;
            font-size: 10px;
            color: #2E325C;
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

        /* ── Список участников ── */
        .participants {
            width: 100%;
            border-collapse: collapse;
        }

        .participants th {
            padding: 4px 3px;
            font-size: 8px;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            color: #ffffff;
            background-color: #2E325C;
            border: 1px solid #2E325C;
        }

        .participants td {
            padding: 2px 3px;
            font-size: 8px;
            text-align: center;
            vertical-align: middle;
            border: 1px solid #d1d5db;
        }

        .participants tr {
            page-break-inside: avoid;
        }

        .participants tbody tr:nth-child(even) td {
            background-color: #f3f6fa;
        }

        .participants .name,
        .participants .team {
            text-align: left;
        }

        .participants .name,
        .participants .rank,
        .participants .total {
            font-weight: bold;
        }

        /* Специфичность как у «зебры» выше — побеждает за счёт порядка. */
        .participants tbody tr td.total {
            background-color: #daf2d0;
        }

        .participants .muted {
            color: #9ca3af;
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
    @php
        $points = fn (float $value): string => rtrim(rtrim(number_format($value, 3, ',', ''), '0'), ',');
    @endphp

    <table class="header">
        <tr>
            <td class="logo-cell">
                <img src="{{ public_path('images/logo-pdf.svg') }}" alt="logo">
            </td>
            <td class="title-cell">
                <div class="title">{{ $title }}</div>
                <div class="subtitle">{{ $subtitle }}</div>
            </td>
        </tr>
    </table>

    @if($rows === [])
        <div class="empty">Участников пока нет.</div>
    @else
        <div class="summary">Всего участников: <b>{{ count($rows) }}</b> · {{ mb_strtolower($sort->getLabel()) }}</div>

        @if($regattas !== null)
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
        @endif

        <table class="participants">
            <thead>
                <tr>
                    <th style="width: 22px;">№</th>
                    <th>ФИО</th>
                    <th style="width: 58px;">Дата рождения</th>
                    <th>Команда</th>
                    @if($regattas !== null)
                        @foreach($regattas as $regatta)
                            <th style="width: 26px;">{{ $regatta['number'] }}</th>
                        @endforeach
                        <th style="width: 38px;">Сумма</th>
                        <th style="width: 32px;">Место</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td class="name">{{ $row['name'] }}</td>
                        <td>{!! $row['birth_date']?->format('d.m.Y') ?? '<span class="muted">—</span>' !!}</td>
                        <td class="team">{!! $row['team'] !== null ? e($row['team']) : '<span class="muted">—</span>' !!}</td>
                        @if($regattas !== null)
                            @foreach($regattas as $regatta)
                                @php $cell = $row['cells'][$regatta['id']]; @endphp
                                <td>{!! $cell !== null ? $points($cell) : '<span class="muted">—</span>' !!}</td>
                            @endforeach
                            <td class="total">{{ $points($row['total']) }}</td>
                            <td class="rank">{!! $row['rank'] ?? '<span class="muted">—</span>' !!}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        @if($regattas !== null)
            В колонках регат — рейтинговые очки, «—» — не участвовал.
        @endif
        Сформировано автоматически {{ now()->format('d.m.Y H:i') }}
    </div>
</body>
</html>
