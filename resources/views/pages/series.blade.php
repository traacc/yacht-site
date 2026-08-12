<x-public-layout title="Серии регат — описание серий и результаты по этапам"
    description="Серии регат ассоциации: описание каждой серии, этапы с коэффициентами и результаты каждого этапа с сортировкой по рейтингу серии.">
<x-breadcrumbs_page title="Серии регат">
</x-breadcrumbs_page>
<x-hero-section title="Серии регат"
    desc="Описание каждой серии, её этапы с коэффициентами и результаты по каждому этапу."
    bgImage="{{ asset('images/bg/competitions.webp') }}"
>
</x-hero-section>

@php
    // Палитра серий — та же, что в карточках серий на вкладке «Календарь».
    $palette = [
        '#2d92ce', // brand-blue
        '#f24842', // brand-red
        '#1f9d57', // green
        '#7c5cbf', // purple
        '#e8833a', // orange
        '#1aa6a0', // teal
        '#d4467f', // pink
        '#5b6bd6', // indigo
    ];
@endphp

<div class="container mx-auto">
    {{-- Навигация подразделов «Соревнования» — как на странице календаря. --}}
    <nav class="flex flex-col sm:flex-row border-b border-[#EAEAEA] mb-8 mt-8" role="tablist">
        <a href="{{ route('competitions') }}"
            class="px-3 py-2 text-sm sm:px-6 sm:py-3 sm:text-lg font-semibold border-l-2 sm:border-l-0 sm:border-b-2 border-transparent text-[#2E325C] hover:text-[#2D92CE] hover:border-[#C6C6C6] transition-colors duration-200 text-center"
            role="tab">
            Календарь регат
        </a>
        <a href="{{ route('competitions') }}#results"
            class="px-3 py-2 text-sm sm:px-6 sm:py-3 sm:text-lg font-semibold border-l-2 sm:border-l-0 sm:border-b-2 border-transparent text-[#2E325C] hover:text-[#2D92CE] hover:border-[#C6C6C6] transition-colors duration-200 text-center"
            role="tab">
            Результаты
        </a>
        <span class="px-3 py-2 text-sm sm:px-6 sm:py-3 sm:text-lg font-semibold border-l-2 sm:border-l-0 sm:border-b-2 border-[#2D92CE] text-[#2D92CE] text-center"
            role="tab" aria-selected="true">
            Серии
        </span>
        <a href="{{ route('regatta-entries') }}"
            class="px-3 py-2 text-sm sm:px-6 sm:py-3 sm:text-lg font-semibold border-l-2 sm:border-l-0 sm:border-b-2 border-transparent text-[#2E325C] hover:text-[#2D92CE] hover:border-[#C6C6C6] transition-colors duration-200 text-center"
            role="tab">
            Заявки
        </a>
    </nav>

    <p class="text-brand-gray-light mb-8">
        В таблице каждого этапа команды идут в порядке их положения в рейтинге серии
        (сумма очков всех этапов с учётом коэффициента), а не по месту на самом этапе.
        <a href="{{ route('series-results') }}" class="text-brand-blue font-semibold hover:underline whitespace-nowrap">Рейтинг серий →</a>
    </p>

    @forelse($series as $serie)
        @php $color = $palette[$loop->index % count($palette)]; @endphp

        <section class="mb-12 bg-white border border-brand-border overflow-hidden"
                 style="border-left: 4px solid {{ $color }}">
            {{-- Шапка серии: название, сезон и описание — как в карточке на «Календаре». --}}
            <div class="px-4 md:px-6 py-5" style="background-color: {{ $color }}14">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <h2 class="text-2xl md:text-3xl font-semibold a-font leading-tight" style="color: {{ $color }}">
                        <a href="{{ $serie['url'] }}" class="hover:underline">{{ $serie['name'] }}</a>
                    </h2>
                    @if($serie['season'])
                        <span class="text-brand-dark text-lg font-semibold whitespace-nowrap">Сезон {{ $serie['season'] }}</span>
                    @endif
                </div>
                @if($serie['description'])
                    {{-- Описание вводят многострочным (календарь этапов) — сохраняем переносы. --}}
                    <p class="text-brand-gray-light mt-2 max-w-4xl whitespace-pre-line">{{ $serie['description'] }}</p>
                @endif
                <p class="text-sm text-brand-gray-light mt-2">Этапов в серии: {{ $serie['stages']->count() }}</p>
            </div>

            {{-- Этапы серии --}}
            @forelse($serie['stages'] as $stage)
                @php $regatta = $stage['regatta']; @endphp
                <div class="border-t border-brand-border px-3 md:px-6 py-6">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2 mb-4">
                        <h3 class="text-xl md:text-2xl font-semibold a-font text-brand-dark leading-tight">
                            Этап {{ $stage['number'] }} —
                            <a href="{{ route('competition-details', $regatta) }}" class="hover:text-brand-blue hover:underline transition-colors">{{ $regatta->name }}</a>
                        </h3>
                        <div class="flex flex-wrap items-center gap-3 text-sm text-brand-gray-light">
                            @if($regatta->dateRange())
                                <span class="whitespace-nowrap">{{ $regatta->dateRange() }}</span>
                            @endif
                            @if($regatta->location)
                                <span>{{ $regatta->location }}</span>
                            @endif
                            @if($stage['coefficient'])
                                {{-- Коэффициент этапа: на него умножаются очки, идущие в зачёт серии. --}}
                                <span class="whitespace-nowrap font-semibold text-brand-dark px-3 py-1"
                                      style="background-color: {{ $color }}1f">
                                    Коэффициент {{ $stage['coefficient'] }}
                                </span>
                            @endif
                        </div>
                    </div>

                    @if($stage['rows']->isNotEmpty())
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm md:text-base">
                                <thead>
                                    <tr class="text-lg md:text-2xl text-brand-dark border-b border-brand-border">
                                        <th class="pb-2 px-2 text-center font-medium a-font w-16" title="Место в рейтинге серии">Рейтинг</th>
                                        <th class="pb-2 px-2 text-center font-medium a-font w-16">Место</th>
                                        <th class="pb-2 px-2 text-center font-medium a-font hidden md:table-cell">Яхта</th>
                                        <th class="pb-2 px-2 text-center font-medium a-font hidden lg:table-cell">Парус №</th>
                                        <th class="pb-2 px-2 text-left font-medium a-font">Команда</th>
                                        <th class="pb-2 px-2 text-center font-medium a-font hidden lg:table-cell">Рулевой</th>
                                        <th class="pb-2 px-2 text-center font-medium a-font hidden md:table-cell">Очки этапа</th>
                                        <th class="pb-2 px-2 text-center font-medium a-font" title="Очки этапа в зачёт серии с учётом коэффициента">В зачёт серии</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y font-medium text-center">
                                    @foreach($stage['rows'] as $row)
                                        <tr class="border-b border-brand-border">
                                            <td class="py-3 px-2 text-brand-dark">{{ $row['series_rank'] ?? '—' }}</td>
                                            <td class="py-3 px-2 text-brand-gray">{{ $row['place'] ?: '—' }}</td>
                                            <td class="py-3 px-2 hidden md:table-cell">{{ $row['yacht'] }}</td>
                                            <td class="py-3 px-2 hidden lg:table-cell">{{ $row['sail_number'] }}</td>
                                            <td class="py-3 px-2 text-left">
                                                {{ $row['team_name'] }}
                                                <span class="md:hidden block text-xs text-brand-gray-light">{{ $row['yacht'] }}</span>
                                            </td>
                                            {{-- Страница не Livewire-компонент, поэтому карточку рулевого
                                                 открываем через Livewire.dispatch, а не wire:click. --}}
                                            <td class="py-3 px-2 hidden lg:table-cell">
                                                @if($row['captain_id'] && $row['captain_name'])
                                                    <button type="button"
                                                            class="text-[#2D92CE] hover:underline cursor-pointer"
                                                            onclick="Livewire.dispatch('open-user-card', { userId: '{{ $row['captain_id'] }}' })">{{ $row['captain_name'] }}</button>
                                                @else
                                                    {{ $row['captain_name'] ?: '—' }}
                                                @endif
                                            </td>
                                            <td class="py-3 px-2 hidden md:table-cell">{{ $row['points'] }}</td>
                                            <td class="py-3 px-2 font-bold text-brand-blue">{{ $row['series_points'] ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-brand-gray-light py-4">Результаты этапа пока не опубликованы.</p>
                    @endif
                </div>
            @empty
                <div class="border-t border-brand-border px-4 md:px-6 py-6">
                    <p class="text-brand-gray-light">В этой серии пока нет этапов.</p>
                </div>
            @endforelse
        </section>
    @empty
        <p class="text-center text-brand-gray-light py-20 text-lg">Серии регат пока не опубликованы.</p>
    @endforelse
</div>

<x-feedback-section></x-feedback-section>
</x-public-layout>
