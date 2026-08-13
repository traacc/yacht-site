@php
    $crewEntry = $this->userCrewEntry;
    $entry = $crewEntry?->regattaEntry;
    $statusLabels = [
        'pending'   => 'На рассмотрении',
        'approved'  => 'Одобрена',
        'rejected'  => 'Отклонена',
        'withdrawn' => 'Отозвана',
    ];
    $statusClasses = [
        'pending'   => 'bg-yellow-100 text-yellow-800',
        'approved'  => 'bg-green-100 text-green-800',
        'rejected'  => 'bg-red-100 text-red-800',
        'withdrawn' => 'bg-gray-100 text-gray-600',
    ];
    $selfRoleLabels = [
        'captain' => 'Рулевой',
        'reserve' => 'Запасной состав',
        'main'    => 'Основной состав',
    ];
@endphp

@if ($entry)
    <div class="mb-5 border border-gray-200 bg-gray-50 p-4 space-y-2">
        <div class="flex items-center justify-between gap-3 text-sm">
            <span class="text-gray-500">Команда</span>
            <span class="font-medium text-[#2E325C] text-right">{{ $entry->team?->name ?? '—' }}</span>
        </div>
        <div class="flex items-center justify-between gap-3 text-sm">
            <span class="text-gray-500">Яхта</span>
            <span class="font-medium text-[#2E325C] text-right">
                {{ $entry->yacht?->name ?? '—' }}
                @if ($entry->yacht?->vfps_number)
                    <span class="text-gray-400">({{ $entry->yacht->vfps_number }})</span>
                @endif
            </span>
        </div>
        <div class="flex items-center justify-between gap-3 text-sm">
            <span class="text-gray-500">Ваша роль</span>
            <span class="font-medium text-[#2E325C] text-right">{{ $selfRoleLabels[$crewEntry->role] ?? 'Основной состав' }}</span>
        </div>
        <div class="flex items-center justify-between gap-3 text-sm">
            <span class="text-gray-500">Статус заявки</span>
            <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium {{ $statusClasses[$entry->status] ?? 'bg-gray-100 text-gray-600' }}">
                {{ $statusLabels[$entry->status] ?? $entry->status }}
            </span>
        </div>

        @php
            $crewMembers = $entry->crew
                ->sortBy(fn ($c) => $c->role === 'captain' ? 0 : ($c->role === 'reserve' ? 2 : 1))
                ->values();
            $crewRoleLabels = [
                'captain' => 'Рулевой',
                'main'    => 'Основной',
                'reserve' => 'Запасной',
            ];
        @endphp
        @if ($crewMembers->isNotEmpty())
            <div class="border-t border-gray-200 pt-2">
                <p class="text-gray-500 text-sm mb-2">Экипаж</p>
                <ul class="space-y-1">
                    @foreach ($crewMembers as $member)
                        <li class="flex items-center justify-between gap-3 text-sm">
                            <span class="font-medium text-[#2E325C]">{{ $member->displayName() }}</span>
                            <span class="text-gray-400">{{ $crewRoleLabels[$member->role] ?? $member->role }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif
