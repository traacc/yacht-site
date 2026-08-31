{{--
    Карточка яхты на витрине бронирования.

    Ожидает: $yacht (с агрегатом price_per_day из App\Services\YachtBooking),
    $days, $from, $to.
--}}
@php
    $photo = $yacht->getMedia('gallery')->first();
    $perDay = $yacht->price_per_day !== null ? (float) $yacht->price_per_day : null;
    $total = $perDay !== null && ($days ?? 0) > 0 ? $perDay * $days : null;

    $url = route('services.yacht-rental-item', array_filter([
        'yacht' => $yacht,
        'date_start' => $from?->toDateString(),
        'date_end' => $to?->toDateString(),
    ]));
@endphp

<a href="{{ $url }}" class="group flex flex-col bg-[#F8F8F8] overflow-hidden hover:shadow-md transition-shadow">
    <div class="relative overflow-hidden h-52">
        @if ($photo)
            <x-responsive-picture :media="$photo" conversion="thumb" :alt="$yacht->name"
                img-class="w-full h-52 object-cover transition-transform duration-500 group-hover:scale-105" />
        @else
            <img class="w-full h-52 object-cover" src="{{ asset('images/gallery.webp') }}" alt="{{ $yacht->name }}">
        @endif

        @if (($days ?? 0) > 0)
            <span class="absolute top-2 left-2 bg-[#2E325C] text-white text-xs font-semibold px-3 py-1">
                Свободна на выбранные даты
            </span>
        @endif
    </div>

    <div class="p-4 flex flex-col grow">
        <h3 class="a-font text-xl mb-1 text-[#2E325C]">{{ $yacht->name }}</h3>

        <p class="text-sm text-brand-gray-light">
            {{ $yacht->class ?? 'Carter 30' }}@if ($yacht->year), {{ $yacht->year }} г.@endif
        </p>

        @if ($yacht->home_region || $yacht->mooring_place)
            <p class="text-sm text-brand-gray-light mb-3">
                {{ $yacht->home_region ?: $yacht->mooring_place }}
            </p>
        @endif

        @if (is_array($yacht->suitable_for) && $yacht->suitable_for !== [])
            <div class="flex flex-wrap gap-1 mb-3">
                @foreach (array_slice($yacht->suitable_for, 0, 3) as $purpose)
                    <span class="bg-white text-brand-gray-light text-xs px-2 py-1">{{ $purpose }}</span>
                @endforeach
            </div>
        @endif

        <div class="mt-auto pt-3 border-t border-[#EAEAEA]">
            @if ($perDay !== null)
                <div class="text-[#2E325C] text-lg font-semibold">
                    от {{ number_format($perDay, 0, '.', ' ') }} ₽<span class="text-sm font-normal text-brand-gray-light">/сутки</span>
                </div>
                @if ($total !== null)
                    <div class="text-sm text-brand-gray-light">
                        {{ number_format($total, 0, '.', ' ') }} ₽ за {{ \App\Support\Plural::with($days, 'день', 'дня', 'дней') }}
                    </div>
                @endif
            @else
                <div class="text-[#2E325C] text-lg font-semibold">Стоимость по запросу</div>
            @endif
        </div>
    </div>
</a>
