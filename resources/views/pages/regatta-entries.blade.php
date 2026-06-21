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
                            @if($regatta->location) · {{ $regatta->location }} @endif
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
                                <th class="pb-2 text-center font-medium a-font hidden md:table-cell">Команда</th>
                                <th class="pb-2 text-center font-medium a-font hidden md:table-cell">Рулевой</th>
                                <th class="pb-2 text-center font-medium a-font hidden md:table-cell">Состав</th>
                                <th class="pb-2 text-center font-medium a-font hidden md:table-cell">Дата подачи</th>
                                <th class="pb-2 text-center font-medium a-font">Статус</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-center font-medium">
                            @foreach($regatta->entries as $index => $entry)
                                <tr class="hover:bg-gray-50 transition-colors border-b border-brand-border">
                                    <td class="py-3">{{ $index + 1 }}</td>
                                    <td class="py-3">{{ $entry->yacht?->name ?? '—' }}</td>
                                    <td class="py-3 hidden md:table-cell">{{ $entry->team?->name ?? '—' }}</td>
                                    <td class="py-3 hidden md:table-cell">{{ $entry->crew->firstWhere('role', 'captain')?->teamMember?->user?->short_name ?? '—' }}</td>
                                    <td class="py-3 hidden md:table-cell">{{ $entry->crew->count() }}</td>
                                    <td class="py-3 hidden md:table-cell">{{ $entry->submitted_at?->format('d.m.Y') ?? '—' }}</td>
                                    <td class="py-3">
                                        <span class="inline-block rounded-full px-3 py-1 text-sm font-medium {{ $statusClasses[$entry->status] ?? 'bg-gray-100 text-gray-600' }}">
                                            {{ $statusLabels[$entry->status] ?? $entry->status }}
                                        </span>
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
