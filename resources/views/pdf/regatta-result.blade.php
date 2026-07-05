<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Результаты — {{ $regatta?->name }}</title>
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
            margin-bottom: 12px;
            border-collapse: collapse;
        }

        .header td {
            vertical-align: middle;
            font-size: 14px;
            font-weight: bold;
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
        /*
        .header .title {
            font-size: 15px;
            font-weight: bold;
            color: #2E325C;
        }

        .header .group {
            font-size: 10px;
            font-weight: bold;
            color: #2E325C;
            margin-top: 2px;
        }

        .header .subtitle {
            font-size: 9px;
            color: #6b7280;
            margin-top: 2px;
        }
        */
        /* ── Таблица ── */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        /*
        thead tr {
            background-color: #2E325C;
            color: #ffffff;
        }
        */
        thead th {
            padding: 4px 3px;
            text-align: center;
            font-size: 7.5px;
            font-weight: bold;
            border-bottom: 1px solid #43476e;
            vertical-align: middle;
        }

        tbody > td {
            padding: 3px 3px;
            font-size: 8px;
            color: #1f2937;
            /*border-bottom: 1px solid #d1d5db;*/
            vertical-align: middle;
            text-align: center;
        }

        tbody td.left {
            text-align: left;
        }

        tbody .item-start > td {
            border-top: 1.5px solid #2E325C;
            text-align: center; 
        }

        .pos-cell { font-weight: bold; /*color: #2D92CE;*/ }
        .team-cell { font-weight: bold; text-align: center; /*color: #2E325C;*/ }
        .total-cell { font-weight: bold; text-align: center;  }
        .captain { font-weight: bold; text-align: center;  }
        .muted { color: #9ca3af; }

        .score-cell {
            background-color: #daf2d0;
        }

        /* Объединённый столбец гонки: место сверху, очки снизу */
        .race-head, .race-cell { padding: 0; }

        .race-head .race-pos,
        .race-cell .race-pos {
            box-sizing: border-box;
            height: 15px;
            line-height: 9px;
            padding: 3px 3px;
        }

        .race-cell .race-pos {
            font-weight: bold;
        }

        .race-head .race-pts,
        .race-cell .race-pts {
            box-sizing: border-box;
            height: 15px;
            line-height: 9px;
            padding: 3px 3px;
            background-color: #daf2d0;
            border-top: 1px solid #c2e0b3;
        }

        /* Экипаж выводится вложенной таблицей, чтобы строки команды не
           разрывались между листами (dompdf теряет rowspan на разрыве). */
        .crew-cell { padding: 0; }

        .crew-table {
            width: 100%;
            border-collapse: collapse;
            border: none;
            margin: 0;
        }

        .crew-table td {
            border: none;
            /*border-bottom: 1px solid #eef0f2;*/
            padding: 2px 3px;
            vertical-align: middle;
        }

        .crew-table tr:last-child td {
            border-bottom: none;
        }

        .crew-name { text-align: left; font-size: 8px; border: none;}
        .crew-meta { font-size: 7.5px; text-align: center; border: none; }

        .footer {
            margin-top: 14px;
            padding-top: 8px;
            /*border-top: 1px solid #e5e7eb;*/
            text-align: right;
        }

        .footer .note {
            font-size: 7.5px;
            color: #9ca3af;
        }
    </style>
</head>
<body>

    {{-- Шапка --}}
    <table class="header">
        <tr>
            <td class="logo-cell">
                <img src="{{ public_path('images/logo-pdf.svg') }}" alt="logo">
            </td>
            <td class="title-cell">
                <div class="title">{{ $regatta?->name }}</div>
                <div class="group">Зачётная группа Carter 30</div>
                <div class="subtitle">{{ $regatta?->water_area }}@if($regatta?->water_area && $dateRange). @endif{{ $dateRange }}</div>
            </td>
        </tr>
    </table>

    {{-- Таблица результатов --}}
    <table>
        <thead>
            @php($headRowspan = $raceCount > 0 ? 2 : 1)
            <tr>
                <th rowspan="{{ $headRowspan }}" style="width: 28px;">Место</th>
                <th rowspan="{{ $headRowspan }}" style="width: 90px;">Команда</th>
                <th rowspan="{{ $headRowspan }}" style="width: 80px;">Яхта</th>
                <th rowspan="{{ $headRowspan }}" style="width: 40px;">Парус №</th>
                <th rowspan="{{ $headRowspan }}" style="width: 110px;">Экипаж</th>
                <th rowspan="{{ $headRowspan }}" style="width: 58px;">Дата рождения</th>
                <th rowspan="{{ $headRowspan }}" style="width: 42px;">Разряд</th>
                @for($n = 1; $n <= $raceCount; $n++)
                    <th style="width: 32px;">Гонка {{ $n }}</th>
                @endfor
                <th rowspan="{{ $headRowspan }}" style="width: 38px;">Итого очков</th>
            </tr>
            @if($raceCount > 0)
                <tr>
                    @for($n = 1; $n <= $raceCount; $n++)
                        <th class="race-head">
                            <div class="race-pos">Место</div>
                            <div class="race-pts">Очки</div>
                        </th>
                    @endfor
                </tr>
            @endif
        </thead>
        <tbody>
            @foreach($rows as $row)
                @php($crew = $row['crew'])
                <tr class="item-start" style="page-break-inside: avoid;">
                    <td class="pos-cell">{{ $row['position'] }}</td>
                    <td class="team-cell">{{ $row['team'] }}@if(!empty($row['not_participate'])) ({{ $row['not_participate'] }})@endif</td>
                    <td>{{ $row['yacht'] }}</td>
                    <td>{{ $row['sail'] }}</td>
                    <td colspan="3" class="crew-cell">
                        <table class="crew-table">
                            @forelse($crew as $i => $member)
                                <tr>
                                    <td class="crew-name {{ $i === 0 ? 'captain' : '' }}">{{ $member['name'] ?? '' }}</td>
                                    <td class="crew-meta" style="width: 58px;">{{ $member['birth'] ?? '' }}</td>
                                    <td class="crew-meta" style="width: 42px;">{{ $member['category'] ?? '' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="crew-name"></td>
                                    <td class="crew-meta" style="width: 58px;"></td>
                                    <td class="crew-meta" style="width: 42px;"></td>
                                </tr>
                            @endforelse
                        </table>
                    </td>

                    @foreach($row['races'] as $race)
                        <td class="race-cell">
                            <div class="race-pos">{{ $race['pos'] }}</div>
                            <div class="race-pts">{{ $race['pts'] }}</div>
                        </td>
                    @endforeach
                    <td class="total-cell">{{ $row['total'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Подвал --}}
    <div class="footer">
        <div class="note">Сформировано: {{ now()->isoFormat('D MMMM YYYY') }} · документ сформирован автоматически</div>
    </div>

</body>
</html>
