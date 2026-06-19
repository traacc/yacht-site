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
                    <table class="w-full responsive-table">
                        <thead>
                            <tr class="text-2xl text-brand-dark border-b border-brand-border">
                                <th class="pb-2 text-center font-medium w-16 a-font">№</th>
                                <th class="pb-2 text-center font-medium a-font">Яхта</th>
                                <th class="pb-2 text-center font-medium a-font">Команда</th>
                                <th class="pb-2 text-center font-medium a-font">Рулевой</th>
                                <th class="pb-2 text-center font-medium a-font">Состав</th>
                                <th class="pb-2 text-center font-medium a-font">Дата подачи</th>
                                <th class="pb-2 text-center font-medium a-font">Статус</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-center font-medium">
                            @foreach($regatta->entries as $index => $entry)
                                <tr class="hover:bg-white transition-colors border-b border-brand-border pb-8! md:pb-0!">
                                    <td data-label="№" class="py-3">{{ $index + 1 }}</td>
                                    <td data-label="Яхта" class="py-3">{{ $entry->yacht?->name ?? '—' }}</td>
                                    <td data-label="Команда" class="py-3">{{ $entry->team?->name ?? '—' }}</td>
                                    <td data-label="Рулевой" class="py-3">{{ $entry->crew->firstWhere('role', 'captain')?->teamMember?->user?->name ?? '—' }}</td>
                                    <td data-label="Состав" class="py-3">{{ $entry->crew->count() }}</td>
                                    <td data-label="Дата подачи" class="py-3">{{ $entry->submitted_at?->format('d.m.Y') ?? '—' }}</td>
                                    <td data-label="Статус" class="py-3">
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

<x-feedback-section></x-feedback-section>
</x-public-layout>
