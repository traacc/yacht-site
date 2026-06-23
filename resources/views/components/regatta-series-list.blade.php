@props(['series'])

{{--
    Список серий регат: каждая серия — отдельная карточка со своим цветом.
    Внутри карточки перечислены входящие в серию регаты с датами.
--}}

@php
    // Палитра цветов для серий (циклически по индексу).
    // Используем arbitrary-значения, чтобы Tailwind не вырезал классы.
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

@if($series->isNotEmpty())
    <section class="mb-12">
        <h2 class="section-title a-font mb-6">Серии регат</h2>

        <div class="grid gap-6 md:grid-cols-2">
            @foreach($series as $serie)
                @php $color = $palette[$loop->index % count($palette)]; @endphp

                <div class="bg-white border border-brand-border overflow-hidden"
                     style="border-left: 4px solid {{ $color }}">
                    {{-- Шапка серии --}}
                    <a href="{{ route('series-details', $serie) }}"
                       class="block px-5 py-4 hover:brightness-95 transition-all" style="background-color: {{ $color }}14">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-xl font-semibold a-font leading-tight hover:underline" style="color: {{ $color }}">
                                {{ $serie->name }}
                            </h3>
                            @if($serie->season)
                                <span class="text-sm text-brand-gray-light whitespace-nowrap mt-1">
                                    Сезон {{ $serie->season->year }}
                                </span>
                            @endif
                        </div>
                        @if($serie->description)
                            <p class="text-sm text-brand-gray-light mt-1">{{ $serie->description }}</p>
                        @endif
                    </a>

                    {{-- Регаты серии --}}
                    <ul class="divide-y divide-brand-border">
                        @forelse($serie->regattas as $regatta)
                            <li>
                                <a href="{{ route('competition-details', $regatta) }}"
                                   class="flex items-center justify-between gap-4 px-5 py-3 hover:bg-brand-light-bg transition-colors">
                                    <span class="flex items-center gap-3 min-w-0">
                                        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0"
                                              style="background-color: {{ $color }}"></span>
                                        <span class="min-w-0">
                                            <span class="block font-medium text-brand-dark truncate">{{ $regatta->name }}</span>
                                            @if($regatta->location)
                                                <span class="block text-xs text-brand-gray-light truncate">{{ $regatta->location }}</span>
                                            @endif
                                        </span>
                                    </span>
                                    <span class="text-sm text-brand-gray-light whitespace-nowrap text-right">
                                        {{ $regatta->dateRange() }}
                                    </span>
                                </a>
                            </li>
                        @empty
                            <li class="px-5 py-3 text-sm text-brand-gray-light">В этой серии пока нет регат.</li>
                        @endforelse
                    </ul>
                </div>
            @endforeach
        </div>
    </section>
@endif
