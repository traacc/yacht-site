<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>История яхты — {{ $yacht->name }}</title>
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

        .header-right .generated {
            font-size: 8px;
            color: #9ca3af;
            margin-top: 4px;
        }

        /* ── Блоки информации ── */
        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #2E325C;
            margin: 18px 0 8px;
        }

        .info-table td {
            padding: 4px 10px 4px 0;
            font-size: 10px;
            vertical-align: top;
        }

        .info-table td.label {
            color: #6b7280;
            width: 150px;
        }

        .info-table td.value {
            color: #1f2937;
            font-weight: bold;
        }

        /* ── Таблицы ── */
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        table.data thead tr {
            background-color: #2E325C;
            color: #ffffff;
        }

        table.data thead th {
            padding: 8px 10px;
            text-align: left;
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        table.data thead th.center {
            text-align: center;
        }

        table.data tbody td {
            padding: 7px 10px;
            font-size: 10px;
            color: #1f2937;
            border-bottom: 1px solid #e5e7eb;
        }

        table.data tbody td.center {
            text-align: center;
        }

        table.data tbody tr:nth-child(even) td {
            background-color: #f7f9fc;
        }

        .muted {
            color: #9ca3af;
        }

        /* ── Подвал ── */
        .footer {
            margin-top: 18px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
            text-align: right;
        }

        .footer .note {
            font-size: 8px;
            color: #9ca3af;
        }
    </style>
</head>
<body>

    {{-- Шапка --}}
    <div class="header">
        <div class="header-left">
            <div class="title">История участия яхты</div>
            <div class="subtitle">{{ $yacht->name }}{{ $yacht->vfps_number ? ' — парус № '.$yacht->vfps_number : '' }}</div>
        </div>
        <div class="header-right">
            <div class="generated">Сформировано: {{ now()->isoFormat('D MMMM YYYY') }}</div>
        </div>
    </div>

    {{-- Информация о яхте --}}
    <div class="section-title">Информация о яхте</div>
    <table class="info-table">
        <tr>
            <td class="label">Класс</td>
            <td class="value">{{ $yacht->class ?: 'Carter 30' }}</td>
        </tr>
        <tr>
            <td class="label">Парус №</td>
            <td class="value">{{ $yacht->vfps_number ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Год выпуска</td>
            <td class="value">{{ $yacht->year ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Место регистрации</td>
            <td class="value">{{ $yacht->reg_place ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Регион базирования</td>
            <td class="value">{{ $yacht->home_region ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Место стоянки</td>
            <td class="value">{{ $yacht->mooring_place ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Владелец</td>
            <td class="value">{{ $owner }}</td>
        </tr>
        <tr>
            <td class="label">Участие в регатах</td>
            <td class="value">{{ $participation->count() }}</td>
        </tr>
    </table>

    {{-- Участие в регатах --}}
    <div class="section-title">Участие в регатах</div>
    <table class="data">
        <thead>
            <tr>
                <th>Регата</th>
                <th style="width: 130px;">Дата</th>
                <th style="width: 120px;">Команда</th>
                <th style="width: 95px;">Статус</th>
                <th class="center" style="width: 55px;">Место</th>
            </tr>
        </thead>
        <tbody>
            @forelse($participation as $entry)
                <tr>
                    <td>{{ $entry['regatta'] }}</td>
                    <td>{{ $entry['date_event'] }}</td>
                    <td>{{ $entry['team'] }}</td>
                    <td>{{ $entry['status'] }}</td>
                    <td class="center">{{ $entry['place'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="muted">Нет данных</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Подвал --}}
    <div class="footer">
        <div class="note">Документ сформирован автоматически</div>
    </div>

</body>
</html>
