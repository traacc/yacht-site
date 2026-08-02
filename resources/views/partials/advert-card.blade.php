{{-- Карточка объявления в списке. Ожидает: $advert. --}}
@php($cover = $advert->firstPhoto())

<a href="{{ $advert->publicUrl() }}"
   class="group block bg-[#F8F8F8] overflow-hidden hover:shadow-md transition-shadow">
    <div class="relative overflow-hidden h-48">
        @if ($cover)
            <x-responsive-picture :media="$cover"
                :alt="$advert->title"
                img-class="w-full h-48 object-cover transition-transform duration-500 group-hover:scale-105" />
        @else
            <img class="w-full h-48 object-cover" src="{{ asset('images/gallery.png') }}" alt="{{ $advert->title }}">
        @endif

        @if ($advert->isSold())
            <span class="absolute top-2 left-2 bg-[#2E325C] text-white text-xs font-semibold px-3 py-1">
                Продано
            </span>
        @endif

        @if ($advert->kindLabel())
            <span class="absolute top-2 right-2 bg-[#2D92CE] text-white text-xs font-semibold px-3 py-1">
                {{ $advert->kindLabel() }}
            </span>
        @endif
    </div>

    <div class="p-4">
        <div class="text-[#2E325C] text-lg font-semibold mb-2">{{ $advert->priceLabel() }}</div>

        <h3 class="text-brand-gray font-medium mb-2 text-sm md:text-base">
            {{ Str::limit($advert->title, 70) }}
        </h3>

        <div class="text-brand-gray-light text-xs space-y-1">
            @if ($advert->position || $advert->sport_category)
                <div>
                    {{ $advert->position?->label() }}
                    @if ($advert->position && $advert->sport_category) · @endif
                    {{ $advert->sport_category?->getLabel() }}
                </div>
            @endif
            @if ($advert->category)
                <div>{{ $advert->category->title }}</div>
            @endif
            @if ($advert->datesLabel())
                <div>{{ $advert->datesLabel() }}</div>
            @endif
            @if ($advert->city)
                <div>{{ $advert->city }}</div>
            @endif
            @if ($advert->published_at)
                <div>{{ $advert->published_at->translatedFormat('j F Y') }}</div>
            @endif
        </div>
    </div>
</a>
