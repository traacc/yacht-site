<x-public-layout title="Заявки на регаты — список всех заявок по каждой регате"
    description="Полный список поданных заявок по каждой регате: яхта, команда, рулевой и статус заявки.">
<x-breadcrumbs_page title="Заявки на регаты">
</x-breadcrumbs_page>
<x-hero-section title="Заявки на регаты"
    desc="Список всех поданных заявок по каждой регате: яхта, команда, рулевой и статус рассмотрения."
    bgImage="{{ asset('images/bg/competitions.webp') }}"
>
</x-hero-section>

@php
    $initials = function (?string $name): string {
        $parts = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);
        if (empty($parts)) {
            return '?';
        }
        return mb_strtoupper(implode('', array_map(
            fn ($p) => mb_substr($p, 0, 1),
            array_slice($parts, 0, 2)
        )));
    };

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
@endphp

<div class="container mx-auto py-10">
    <x-ratings-tabs :tabs="[
        'calendar' => ['label' => 'Календарь регат', 'url' => route('competitions')],
        'results' => ['label' => 'Результаты', 'url' => route('competitions') . '#results'],
        'entries' => ['label' => 'Заявки', 'url' => route('regatta-entries'), 'active' => true],
    ]" />

    <div class="flex justify-end mb-8" x-data>
        <button @click="$dispatch('open-join-regatta-modal')"
                class="bg-brand-blue text-white py-2 px-6 hover:bg-brand-blue transition-colors text-lg font-semibold cursor-pointer">
            Подать заявку →
        </button>
    </div>

    @forelse($regattas as $regatta)
        <section class="mb-12 teams">
            <div class="lg:p-6 bg-brand-light-bg">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
                    <div>
                        <h2 class="section-title a-font">
                            <a href="{{ route('competition-details', $regatta) }}" class="hover:underline">
                                {{ $regatta->name }}
                            </a>
                        </h2>
                        <p class="text-brand-gray-light mt-1">
                            {{ $regatta->dateRange() }}
                            @if($regatta->water_area) · {{ $regatta->water_area }} @endif
                            · <span class="font-medium">{{ $regatta->regatta_status?->getLabel() }}</span>
                        </p>
                    </div>
                    <span class="text-brand-dark text-lg font-semibold">
                        Всего заявок: {{ $regatta->entries->count() }}
                    </span>
                </div>

                <div class="overflow-x-auto p-3 md:p-6 bg-white">
                    <table class="w-full">
                        <thead>
                            <tr class="text-lg md:text-2xl text-brand-dark border-b border-brand-border">
                                <th class="pb-2 text-center font-medium w-10 md:w-16 a-font">№</th>
                                <th class="pb-2 text-center font-medium a-font">Яхта</th>
                                <!--<th class="pb-2 text-center font-medium a-font hidden md:table-cell">Команда</th>-->
                                <th class="pb-2 text-center font-medium a-font hidden md:table-cell">Рулевой</th>
                                <th class="pb-2 text-center font-medium a-font hidden md:table-cell">Состав</th>
                                <th class="pb-2 text-center font-medium a-font">Статус</th>
                                <th class="pb-2 text-center font-medium a-font"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-center font-medium">
                            @foreach($regatta->entries as $index => $entry)
                                <tr class="hover:bg-gray-50 transition-colors border-b border-brand-border">
                                    <td class="py-3">{{ $index + 1 }}</td>
                                    <td class="py-3">
                                        @if($entry->yacht && $entry->crew->count())
                                            <button type="button"
                                                    onclick="Livewire.dispatch('open-entry-crew', { entryId: '{{ $entry->id }}' })"
                                                    class="text-[#2D92CE] hover:underline cursor-pointer"
                                                    title="Экипаж яхты">
                                                {{ $entry->yacht->name }}
                                            </button>
                                        @else
                                            {{ $entry->yacht?->name ?? '—' }}
                                        @endif
                                    </td>
                                    <!--<td class="py-3 hidden md:table-cell">{{ $entry->team?->name ?? '—' }}</td>-->
                                    <td class="py-3 hidden md:table-cell">{{ $entry->crew->firstWhere('role', 'captain')?->displayName() ?? '—' }}</td>
                                    <td class="py-3 hidden md:table-cell">
                                        @if($entry->crew->count())
                                            <div class="flex items-center justify-center -space-x-2">
                                                @foreach($entry->crew as $crewMember)
                                                    {{-- Сборный экипаж: участник привязан к пользователю напрямую либо описан контактами --}}
                                                    @php $user = $crewMember->teamMember?->user ?? $crewMember->user; @endphp
                                                    <{{ $user ? 'button' : 'div' }}
                                                         @if($user) type="button" onclick="Livewire.dispatch('open-user-card', { userId: '{{ $user->id }}' })" @endif
                                                         class="w-8 h-8 rounded-full overflow-hidden flex items-center justify-center bg-[#2E325C] text-white text-[10px] font-bold flex-shrink-0 ring-2 {{ $crewMember->role === 'captain' ? 'ring-[#2D92CE]' : 'ring-white' }} {{ $user ? 'cursor-pointer hover:ring-[#2D92CE] transition-all' : '' }}"
                                                         title="{{ $user?->short_name ?? $crewMember->displayName() }}">
                                                        @if($user?->photo_url)
                                                            <img src="{{ asset('storage/'.$user->photo_url) }}" alt="{{ $user?->short_name }}" class="w-full h-full object-cover">
                                                        @else
                                                            <span>{{ $initials($user?->name ?? $crewMember->full_name) }}</span>
                                                        @endif
                                                    </{{ $user ? 'button' : 'div' }}>
                                                @endforeach
                                            </div>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="py-3">
                                        <span class="inline-block rounded-full px-3 py-1 text-sm font-medium {{ $statusClasses[$entry->status] ?? 'bg-gray-100 text-gray-600' }}">
                                            {{ $statusLabels[$entry->status] ?? $entry->status }}
                                        </span>
                                    </td>
                                    {{-- Добор людей со стороны: экипаж включает его сам при подаче заявки --}}
                                    <td class="py-3">
                                        @if($entry->isOpenForJoin())
                                            <button type="button"
                                                    onclick="Livewire.dispatch('open-crew-join', { entryId: '{{ $entry->id }}' })"
                                                    class="bg-brand-blue text-white py-1.5 px-4 text-sm font-medium hover:opacity-90 transition-opacity cursor-pointer whitespace-nowrap">
                                                Хочу в этот экипаж
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @empty
        <p class="text-center text-brand-gray-light py-20 text-lg">Заявок пока нет.</p>
    @endforelse
</div>

<livewire:join-regatta-modal />

<x-feedback-section></x-feedback-section>
</x-public-layout>
