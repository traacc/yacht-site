{{--
    Что изменит замена пользователя (окно подтверждения) или что изменила (журнал).
    $plan — ReplaceUserAction::preview(); $done — true для записи журнала.
--}}
@php
    $done ??= false;
    $plan ??= null;
@endphp

@if ($plan === null)
    <p class="text-sm text-gray-500">Выберите обе учётки, чтобы увидеть, что изменится.</p>
@else
    <div class="space-y-3 text-sm">
        <div class="rounded-lg border border-gray-200 p-3">
            <div><span class="text-gray-500">Кого:</span> <span class="font-medium">{{ $fromLabel }}</span></div>
            <div class="mt-1"><span class="text-gray-500">На кого:</span> <span class="font-medium">{{ $toLabel }}</span></div>
            @if ($warning ?? null)
                <div class="mt-2 text-warning-600">{{ $warning }}</div>
            @endif
        </div>

        <div class="rounded-lg border border-gray-200 p-3">
            <span class="font-medium">Экипажи заявок: {{ count($plan['entries']) }}</span>
            @if ($plan['entries'] !== [])
                <ul class="mt-2 space-y-1 text-gray-600">
                    @foreach ($plan['entries'] as $row)
                        <li>
                            {{ $row['date'] ?? '—' }} · {{ $row['regatta'] }}@if ($row['team']) · {{ $row['team'] }}@endif
                            · {{ $row['role'] ?: 'без роли' }}
                            @if ($row['archived'] ?? false)
                                <x-filament::badge color="gray" size="sm" class="inline-flex">архив</x-filament::badge>
                            @endif
                            @if ($row['merged'])
                                <span class="text-warning-600">— оба в экипаже, останется одна строка со старшей ролью</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="rounded-lg border border-gray-200 p-3">
            <span class="font-medium">Команды: {{ count($plan['teams']) }}</span>
            @if ($plan['teams'] !== [])
                <ul class="mt-2 space-y-1 text-gray-600">
                    @foreach ($plan['teams'] as $row)
                        <li>
                            {{ $row['team'] }}
                            @if ($row['merged'])
                                <span class="text-gray-500">— уже состоит, членства объединятся</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
            <dl class="mt-2 space-y-1 text-gray-500">
                <div>Подал заявок: {{ $plan['submitted'] }}</div>
                <div>Протоколы удалённых заявок (снимок состава): {{ $plan['snapshots'] }}</div>
                <div>Отклики «Хочу в экипаж»: {{ $plan['join_requests'] }}, приглашения в команды: {{ $plan['invitations'] }}</div>
                <div>Пересчёт личного рейтинга: {{ $plan['seasons'] === [] ? 'не нужен' : 'сезоны '.implode(', ', $plan['seasons']) }}</div>
            </dl>
        </div>

        @if ($plan['blockers'] === [])
            <div class="rounded-lg border border-success-300 bg-success-50 p-3 text-success-700">
                Учётка «{{ $fromName }}» {{ $done ? 'удалена' : 'будет удалена' }} (её можно восстановить из удалённых).
            </div>
        @else
            <div class="rounded-lg border border-warning-300 bg-warning-50 p-3 text-warning-700">
                Учётка «{{ $fromName }}» {{ $done ? 'не удалена' : 'не будет удалена' }} — на неё ссылаются данные, которые замена не переносит:
                {{ implode('; ', $plan['blockers']) }}.
            </div>
        @endif
    </div>
@endif
