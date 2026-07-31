@props([
    // App\Models\Tour
    'tour',
    // Архивная карточка: без плашки мест и цены — поход уже прошёл.
    'past' => false,
])

{{--
    Карточка похода.

    Один компонент на витрину, архив «Прошедшие походы» и блок «другие походы»
    на странице тура — чтобы разметка не расходилась между списками.
--}}
@php
    $cover = $tour->getFirstMedia('cover');
    $seats = $tour->seatsLabel();
    $vessel = $tour->vesselLabel();
    $duration = $tour->durationLabel();
@endphp

<a href="{{ $tour->publicUrl() }}"
   class="group block bg-[#F8F8F8] overflow-hidden hover:shadow-md transition-shadow {{ $past ? 'opacity-90' : '' }}">
    <div class="overflow-hidden h-52">
        @if ($cover)
            <x-responsive-picture :media="$cover" :alt="$tour->title"
                img-class="w-full h-52 object-cover transition-transform duration-500 group-hover:scale-105" />
        @else
            <img class="w-full h-52 object-cover" src="{{ asset('images/gallery.png') }}" alt="{{ $tour->title }}">
        @endif
    </div>

    <div class="p-4">
        <div class="text-brand-gray-light text-sm mb-2">
            {{ $tour->dateRange() }}@if ($duration) · {{ $duration }}@endif
        </div>

        <h3 class="text-[#2E325C] text-lg font-semibold mb-2">{{ $tour->title }}</h3>

        @if ($tour->route_summary)
            <p class="text-brand-gray font-medium text-sm mb-2">{{ $tour->route_summary }}</p>
        @elseif ($tour->summary)
            <p class="text-brand-gray font-medium text-sm mb-2">{{ Str::limit($tour->summary, 120) }}</p>
        @endif

        @if ($vessel)
            <div class="text-brand-gray-light text-sm mb-2">На чём: {{ $vessel }}</div>
        @endif

        @unless ($past)
            @if ($tour->seatPriceLabel())
                <div class="text-[#2E325C] font-semibold text-sm mb-2">{{ $tour->seatPriceLabel() }}</div>
            @endif

            @if ($seats)
                <div class="inline-block text-xs px-2 py-1 mb-2 {{ $tour->hasSeats() ? 'bg-[#DCEBE3] text-[#2E325C]' : 'bg-gray-200 text-brand-gray-light' }}">
                    {{ $seats }}
                </div>
            @endif
        @endunless

        <span class="block text-[#2D92CE] font-semibold text-sm">Подробнее →</span>
    </div>
</a>
