@props([
    // App\Models\ForeignRegattaYacht
    'yacht',
    // Имя window-события общей формы заявки; null — заявки регата не принимает.
    'requestEvent' => null,
])

{{--
    Карточка лодки зарубежной регаты.

    Характеристики берутся «эффективными»: у лодки из дивизиона-флота своих нет,
    они наследуются от дивизиона (@see App\Models\ForeignRegattaYacht::spec()),
    поэтому все карточки такого дивизиона выглядят одинаково — кроме шкипера и
    свободных мест, которые у каждой лодки свои.

    Кнопки выводятся из данных, а не задаются отдельно: со шкипером лодка
    продаёт места и каюты, без шкипера — сдаётся целиком
    (@see components/foreign-yacht-cta).

    Длинный список разных лодок показывается таблицей, а не карточками
    (@see components/foreign-fleet-table).
--}}
@php
    $photos = $yacht->effectivePhotos();
    $cover = $photos[0] ?? null;
    $description = trim((string) $yacht->effectiveDescription());

    $specs = array_values(array_filter([
        $yacht->cabinsLabel(),
        $yacht->effectiveYear() ? $yacht->effectiveYear().' г.' : null,
        $yacht->effectiveDownwindSail()?->label(),
    ]));

    $hasOffers = $requestEvent !== null && count($yacht->offeredParticipations()) > 0;
@endphp

<div x-data="{ details: false }" class="border border-[#C6C6C6] flex flex-col">
    @if ($cover)
        <button type="button" @click="details = true" class="block w-full">
            <picture>
                @if (! empty($cover['avif']))
                    <source srcset="{{ $cover['avif'] }}" type="image/avif">
                @endif
                @if (! empty($cover['webp']))
                    <source srcset="{{ $cover['webp'] }}" type="image/webp">
                @endif
                <img src="{{ $cover['src'] }}" alt="{{ $yacht->title() }}" loading="lazy"
                     class="w-full h-48 object-cover">
            </picture>
        </button>
    @endif

    <div class="p-5 flex flex-col grow">
        <h3 class="a-font text-xl text-[#2E325C] mb-1">{{ $yacht->title() }}</h3>

        @if (count($specs) > 0)
            <div class="text-brand-gray-light text-sm mb-3">{{ implode(' · ', $specs) }}</div>
        @endif

        {{-- ===== Стоимость чартера ===== --}}
        <div class="text-sm space-y-1 mb-3">
            @if ($yacht->priceLabel())
                <div class="text-[#2E325C] font-semibold">{{ $yacht->priceLabel() }}</div>
            @endif
            @if ($yacht->charterFeeLabel())
                <div class="text-brand-gray-light">Сборы чартерной компании — {{ $yacht->charterFeeLabel() }}</div>
            @endif
            @if ($yacht->depositLabel())
                <div class="text-brand-gray-light">Депозит — {{ $yacht->depositLabel() }}</div>
            @endif
            @if ($yacht->effectivePriceNote())
                <div class="text-brand-gray-light text-xs">{{ $yacht->effectivePriceNote() }}</div>
            @endif
        </div>

        {{-- ===== Шкипер и места ===== --}}
        @if ($yacht->hasSkipper())
            <div class="text-sm border-t border-[#EAEAEA] pt-3 mb-3">
                <div class="text-[#2E325C]">Шкипер — {{ $yacht->skipper_name }}</div>
                @if ($yacht->skipper_note)
                    <div class="text-brand-gray-light text-xs mt-1">{{ $yacht->skipper_note }}</div>
                @endif

                @if ($yacht->sellsSeats())
                    <div class="text-brand-gray-light mt-2">
                        {{ ucfirst($yacht->freeSeatsLabel()) }}@if ($yacht->seatPriceLabel()) по {{ $yacht->seatPriceLabel() }}@endif
                    </div>
                    @if ($yacht->sellsCabins())
                        <div class="text-brand-gray-light mt-1">Каюта целиком — {{ $yacht->cabinPriceLabel() }}</div>
                    @endif
                    @if ($yacht->seat_note)
                        <div class="text-brand-gray-light text-xs mt-1">{{ $yacht->seat_note }}</div>
                    @endif
                @else
                    <div class="text-brand-gray-light mt-2">Мест в экипаже нет</div>
                @endif
            </div>
        @endif

        <div class="mt-auto pt-2 flex flex-col gap-3">
            <x-foreign-yacht-cta :yacht="$yacht" :request-event="$requestEvent" />

            <div class="flex flex-wrap items-center gap-3">
                @unless ($hasOffers)
                    @unless ($yacht->hasSkipper())
                        <span class="inline-block text-xs px-2 py-1 bg-gray-200 text-brand-gray-light">{{ $yacht->status->label() }}</span>
                    @endunless
                @endunless

                @if ($description !== '' || count($photos) > 1)
                    <button type="button" @click="details = true"
                            class="text-[#2D92CE] font-semibold text-sm hover:underline">Подробнее</button>
                @endif
            </div>
        </div>
    </div>

    <x-foreign-yacht-details :yacht="$yacht" :request-event="$requestEvent" />
</div>
