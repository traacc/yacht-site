@props([
    // App\Models\ForeignRegatta
    'regatta',
    // Архивная карточка: без цены — регата уже прошла.
    'past' => false,
])

{{--
    Карточка зарубежной регаты.

    Один компонент на витрину, архив «Прошедшие регаты» и блок «другие регаты»
    на странице регаты — чтобы разметка не расходилась между списками.
--}}
@php
    $cover = $regatta->getFirstMedia('cover');
    $place = $regatta->placeLabel();
    $duration = $regatta->durationLabel();
@endphp

<a href="{{ $regatta->publicUrl() }}"
   class="group block bg-[#F8F8F8] overflow-hidden hover:shadow-md transition-shadow {{ $past ? 'opacity-90' : '' }}">
    <div class="overflow-hidden h-52">
        @if ($cover)
            <x-responsive-picture :media="$cover" :alt="$regatta->title"
                img-class="w-full h-52 object-cover transition-transform duration-500 group-hover:scale-105" />
        @else
            <img class="w-full h-52 object-cover" src="{{ asset('images/gallery.png') }}" alt="{{ $regatta->title }}">
        @endif
    </div>

    <div class="p-4">
        <div class="text-brand-gray-light text-sm mb-2">
            {{ $regatta->dateRange() }}@if ($duration) · {{ $duration }}@endif
        </div>

        <h3 class="text-[#2E325C] text-lg font-semibold mb-2">{{ $regatta->title }}</h3>

        @if ($place)
            <p class="text-brand-gray font-medium text-sm mb-2">{{ $place }}</p>
        @elseif ($regatta->summary)
            <p class="text-brand-gray font-medium text-sm mb-2">{{ Str::limit($regatta->summary, 120) }}</p>
        @endif

        @if ($regatta->route_summary)
            <div class="text-brand-gray-light text-sm mb-2">{{ $regatta->route_summary }}</div>
        @endif

        @unless ($past)
            @if ($regatta->seatPriceLabel())
                <div class="text-[#2E325C] font-semibold text-sm mb-2">от {{ $regatta->seatPriceLabel() }}</div>
            @endif

            @php $free = $regatta->showsCharterFleet() ? $regatta->availableCharterYachts()->count() : null; @endphp

            @if ($free !== null)
                <div class="inline-block text-xs px-2 py-1 mb-2 {{ $free > 0 ? 'bg-[#DCEBE3] text-[#2E325C]' : 'bg-gray-200 text-brand-gray-light' }}">
                    {{ $free > 0
                        ? 'Свободно '.\App\Support\Plural::with($free, 'яхта', 'яхты', 'яхт')
                        : 'Весь чартер разобран' }}
                </div>
            @endif
        @endunless

        <span class="block text-[#2D92CE] font-semibold text-sm">Подробнее →</span>
    </div>
</a>
