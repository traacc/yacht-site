<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Календарь регат {{ $year }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 11px;
            color: #1a1a2e;
            background: #ffffff;
            padding: 24px 32px;
        }

        /* ── Шапка ── */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            border-bottom: 3px solid #2E325C;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }

        .header-left .org-name {
            font-size: 9px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .header-left .title {
            font-size: 20px;
            font-weight: bold;
            color: #2E325C;
        }

        .header-left .subtitle {
            font-size: 12px;
            color: #6b7280;
            margin-top: 2px;
        }

        .header-right {
            text-align: right;
        }

        .header-right .year-badge {
            font-size: 36px;
            font-weight: bold;
            color: #2D92CE;
            line-height: 1;
        }

        .header-right .generated {
            font-size: 8px;
            color: #9ca3af;
            margin-top: 4px;
        }

        /* ── Таблица ── */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        thead tr {
            background-color: #2E325C;
            color: #ffffff;
        }

        thead th {
            padding: 10px 12px;
            text-align: left;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        thead th.center {
            text-align: center;
        }

        tbody tr {
            border-bottom: 1px solid #e5e7eb;
        }

        tbody tr:nth-child(even) {
            background-color: #f8f9fb;
        }

        tbody tr:hover {
            background-color: #eef4fb;
        }

        tbody td {
            padding: 9px 12px;
            vertical-align: middle;
            font-size: 10.5px;
            color: #1f2937;
        }

        tbody td.center {
            text-align: center;
        }

        /* Статус-бейдж */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .badge-finished {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-active {
            background-color: #fef3c7;
            color: #92400e;
        }

        .badge-planned {
            background-color: #e5e7eb;
            color: #374151;
        }

        /* Коэффициент */
        .coeff {
            font-weight: bold;
            color: #2D92CE;
            font-size: 11px;
        }

        /* Нет данных */
        .empty-row td {
            text-align: center;
            padding: 32px;
            color: #9ca3af;
            font-style: italic;
        }

        /* ── Подвал ── */
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .footer-left {
            font-size: 8px;
            color: #9ca3af;
        }

        .footer-right {
            font-size: 8px;
            color: #9ca3af;
        }

        .total-count {
            font-size: 10px;
            color: #6b7280;
            margin-top: 12px;
        }

        .total-count strong {
            color: #2E325C;
        }
    </style>
</head>
<body>

    {{-- Шапка --}}
    <div class="header">
        <div class="header-left">
            <div class="title">Календарь регат</div>
            <div class="subtitle">Расписание соревновательного сезона</div>
        </div>
        <div class="header-right">
            <div class="year-badge">{{ $year }}</div>
            <div class="generated">Сформировано: {{ now()->isoFormat('D MMMM YYYY') }}</div>
        </div>
    </div>

    {{-- Таблица регат --}}
    <table>
        <thead>
            <tr>
                <th class="center" style="width: 36px;">#</th>
                <th style="width: 140px;">Дата проведения</th>
                <th>Название регаты</th>
                <th style="width: 180px;">Акватория проведения</th>
                <th class="center" style="width: 90px;">Рейтинговый коэффициент</th>
            </tr>
        </thead>
        <tbody>
            @forelse($regattas as $index => $regatta)
                <tr>
                    <td class="center" style="color: #9ca3af; font-size: 9px;">{{ $index + 1 }}</td>
                    <td>{{ $regatta->dateRange() }}</td>
                    <td>
                        <strong>{{ $regatta->name }}</strong>
                    </td>
                    <td>{{ $regatta->water_area ?: ($regatta->location ?: '—') }}</td>
                    <td class="center">
                        @if($regatta->level_coefficient)
                            <span class="coeff">{{ number_format((float) $regatta->level_coefficient, 2) }}</span>
                        @else
                            <span style="color: #9ca3af;">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr class="empty-row">
                    <td colspan="5">Регаты для сезона {{ $year }} не найдены</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Итого --}}
    @if($regattas->isNotEmpty())
        <div class="total-count">
            Всего регат в сезоне: <strong>{{ $regattas->count() }}</strong>
        </div>
    @endif

    {{-- Подвал --}}
    <div class="footer">
        <div class="footer-right">Документ сформирован автоматически</div>
    </div>

</body>
</html>
