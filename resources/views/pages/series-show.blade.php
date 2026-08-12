<x-public-layout :title="$series->name . ' — серия регат'"
    :description="$series->description ?: 'Регаты серии ' . $series->name . ($series->season ? ', сезон ' . $series->season->year : '') . '.'">
<x-breadcrumbs_page :title="$series->name">
</x-breadcrumbs_page>
<x-hero-section :title="$series->name"
    :desc="$series->description ?: ($series->season ? 'Сезон ' . $series->season->year : 'Серия регат')"
    bgImage="{{ asset('images/bg/competitions.webp') }}"
>
</x-hero-section>

<div class="container mx-auto py-10">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-8">
        <div>
            <h2 class="section-title a-font">Регаты серии</h2>
            @if($series->description)
                <p class="text-brand-gray-light mt-1 max-w-3xl">{{ $series->description }}</p>
            @endif
        </div>
        <div class="flex items-center gap-4">
            @if($series->season)
                <span class="text-brand-dark text-lg font-semibold">Сезон {{ $series->season->year }}</span>
            @endif
            <a href="{{ route('series') }}"
               class="text-brand-blue font-semibold hover:underline whitespace-nowrap">
                Результаты по этапам →
            </a>
            <a href="{{ route('series-results') }}"
               class="text-brand-blue font-semibold hover:underline whitespace-nowrap">
                Рейтинг серий →
            </a>
        </div>
    </div>

    @if($series->regattas->isNotEmpty())
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($series->regattas as $regatta)
                <div class="bg-brand-light-bg overflow-hidden font-sans regatta-card">
                    <div class="relative">
                        <img src="{{ $regatta->background_image ? '/storage/' . $regatta->background_image : asset('images/news/news_1.webp') }}"
                             alt="{{ $regatta->name }}"
                             class="w-full h-64 object-cover" />
                        <div class="absolute top-0 right-0 bg-brand-bg-secondary px-4 py-2">
                            <span class="text-brand-gray-light font-bold text-sm uppercase">
                                {{ $regatta->regatta_status->getLabel() }}
                            </span>
                        </div>
                        @if($regatta->dateRange())
                            <div class="absolute bottom-0 left-0 bg-brand-light-bg text-brand-dark px-4 py-2">
                                <span class="font-bold text-sm tracking-wide">{{ $regatta->dateRange() }}</span>
                            </div>
                        @endif
                    </div>
                    <div class="px-6 pt-6 pb-7 space-y-4">
                        <h2 class="text-brand-navy font-semibold text-lg leading-tight">{{ $regatta->name }}</h2>
                        @if($regatta->location)
                            <div class="flex items-center gap-3 text-gray-600">
                                <x-icon-2 name="marker" /> <span>{{ $regatta->location }}</span>
                            </div>
                        @endif
                        @if($regatta->water_area)
                            <div class="flex items-center gap-3 text-gray-600">
                                <x-icon-2 name="waves" /> <span>{{ $regatta->water_area }}</span>
                            </div>
                        @endif
                        <a href="{{ route('competition-details', $regatta) }}"
                           class="flex items-center gap-2 text-brand-navy font-bold text-lg hover:gap-3 transition-all duration-200 group">
                            Подробнее  →
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-center text-brand-gray-light py-20 text-lg">В этой серии пока нет регат.</p>
    @endif
</div>

<x-feedback-section></x-feedback-section>
</x-public-layout>
