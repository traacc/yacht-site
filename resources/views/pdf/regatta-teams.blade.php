<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Список команд — {{ $regatta->name }}</title>
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

        .header-right {
            text-align: right;
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
            padding: 8px 10px;
            text-align: left;
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        thead th.center {
            text-align: center;
        }

        tbody td {
            padding: 7px 10px;
            vertical-align: top;
            font-size: 10px;
            color: #1f2937;
            border-bottom: 1px solid #e5e7eb;
        }

        tbody td.center {
            text-align: center;
        }

        /* Группа команды */
        .team-row td {
            border-top: 2px solid #2E325C;
            background-color: #eef4fb;
        }

        .team-row .team-num {
            font-weight: bold;
            color: #2D92CE;
        }

        .team-row .team-name {
            font-weight: bold;
            color: #2E325C;
        }

        .member-row td {
            color: #374151;
        }

        .member-row .member-name {
            padding-left: 18px;
        }

        .muted {
            color: #9ca3af;
        }

        /* ── Подвал ── */
        .total-count {
            font-size: 10px;
            color: #6b7280;
            margin-top: 14px;
        }

        .total-count strong {
            color: #2E325C;
        }

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
            <div class="title">Заявленные команды</div>
            <div class="subtitle">{{ $regatta->name }}</div>
        </div>
        <div class="header-right">
            <div class="generated">Сформировано: {{ now()->isoFormat('D MMMM YYYY') }}</div>
        </div>
    </div>

    {{-- Таблица команд и состава --}}
    <table>
        <thead>
            <tr>
                <th class="center" style="width: 32px;">№</th>
                <th style="width: 120px;">Команда / Яхта</th>
                <th>Участник</th>
                <th style="width: 90px;">Дата рождения</th>
                <th class="center" style="width: 70px;">Разряд</th>
            </tr>
        </thead>
        <tbody>
            @foreach($entries as $index => $entry)
                @php($crew = $entry->crew ?? collect())
                @php($captain = $crew->firstWhere('role', 'captain'))
                @php($captainUser = $captain?->teamMember?->user ?? $captain?->user)
                {{-- Сортируем по отображаемому имени: у сборного экипажа участник без team_member --}}
                @php($members = $crew->reject(fn ($c) => $c->role === 'captain')->sortBy(fn ($c) => $c->displayName(), SORT_NATURAL | SORT_FLAG_CASE)->values())
                <tr class="team-row">
                    <td class="center team-num">{{ $index + 1 }}</td>
                    <td>
                        <span class="team-name">{{ $entry->team?->name ?? '—' }}</span><br>
                        <span class="muted">{{ $entry->yacht?->name ?? '—' }}</span>
                    </td>
                    <td class="member-name">Рулевой: <strong>{{ $captainUser?->short_name ?? $captain?->displayName() ?? '—' }}</strong></td>
                    <td>{{ $captainUser?->birth_date?->format('d.m.Y') ?? '—' }}</td>
                    <td class="center">{{ \App\Enums\SportCategory::labelOrNone($captainUser?->sport_category) }}</td>
                </tr>
                @forelse($members as $crewMember)
                    @php($user = $crewMember->teamMember?->user ?? $crewMember->user)
                    <tr class="member-row">
                        <td></td>
                        <td></td>
                        <td class="member-name">{{ $user?->short_name ?? $crewMember->displayName() }}</td>
                        <td>{{ $user?->birth_date?->format('d.m.Y') ?? '—' }}</td>
                        <td class="center">{{ \App\Enums\SportCategory::labelOrNone($user?->sport_category) }}</td>
                    </tr>
                @empty
                    <tr class="member-row">
                        <td></td>
                        <td></td>
                        <td colspan="3" class="muted">Других участников нет</td>
                    </tr>
                @endforelse
            @endforeach
        </tbody>
    </table>

    {{-- Итого --}}
    <div class="total-count">
        Всего команд: <strong>{{ $entries->count() }}</strong>
    </div>

    {{-- Подвал --}}
    <div class="footer">
        <div class="note">Документ сформирован автоматически</div>
    </div>

</body>
</html>
