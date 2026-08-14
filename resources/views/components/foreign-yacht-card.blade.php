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

    Кнопка выводится из данных, а не задаётся отдельно: есть шкипер — лодка
    продаёт места в экипаж, нет шкипера — сдаётся целиком.
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

    $cta = $requestEvent === null ? null : $yacht->ctaLabel();
    $ctaPayload = $cta === null ? [] : [
        'participation' => $yacht->offeredParticipation()->value,
        $yacht->ctaPayloadField() => (string) $yacht->getKey(),
    ];
    $ctaDetail = $cta === null ? '' : $yacht->title().' — '.mb_strtolower($yacht->offeredParticipation()->label());
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
                    @if ($yacht->seat_note)
                        <div class="text-brand-gray-light text-xs mt-1">{{ $yacht->seat_note }}</div>
                    @endif
                @else
                    <div class="text-brand-gray-light mt-2">Мест в экипаже нет</div>
                @endif
            </div>
        @endif

        <div class="mt-auto pt-2 flex flex-wrap items-center gap-3">
            @if ($cta)
                <button type="button"
                        @click="$dispatch('{{ $requestEvent }}', { payload: @js($ctaPayload), label: @js($ctaDetail) })"
                        class="bg-[#2D92CE] text-white py-2 px-5 hover:bg-[#0074CC] transition-colors text-sm font-semibold">
                    {{ $cta }} →
                </button>
            @elseif (! $yacht->hasSkipper())
                <span class="inline-block text-xs px-2 py-1 bg-gray-200 text-brand-gray-light">{{ $yacht->status->label() }}</span>
            @endif

            @if ($description !== '' || count($photos) > 1)
                <button type="button" @click="details = true"
                        class="text-[#2D92CE] font-semibold text-sm hover:underline">Подробнее</button>
            @endif
        </div>
    </div>

    {{-- ===== Подробности лодки ===== --}}
    <div x-show="details" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-black/50 z-20"></div>

        <div @click.outside="details = false"
             class="px-3 py-3 relative overflow-y-auto max-h-[90vh] bg-white w-full max-w-[720px] z-30 top-1/2 left-1/2 -translate-1/2">
            <div class="p-3.5 md:p-4">
                <div class="flex items-start justify-between gap-4 mb-3">
                    <h3 class="a-font text-2xl md:text-3xl text-[#2E325C]">{{ $yacht->title() }}</h3>
                    <button type="button" @click="details = false"
                            class="text-gray-400 hover:text-gray-500 text-2xl font-bold leading-none">&times;</button>
                </div>

                @if (count($specs) > 0)
                    <div class="text-brand-gray-light text-sm mb-4">{{ implode(' · ', $specs) }}</div>
                @endif

                @if ($description !== '')
                    <p class="text-brand-gray whitespace-pre-line mb-5">{{ $description }}</p>
                @endif

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm mb-5">
                    @if ($yacht->priceLabel())
                        <div class="flex justify-between border-b border-[#EAEAEA] pb-1">
                            <dt class="text-brand-gray-light">Стоимость чартера</dt>
                            <dd class="text-[#2E325C] font-semibold">{{ $yacht->priceLabel() }}</dd>
                        </div>
                    @endif
                    @if ($yacht->charterFeeLabel())
                        <div class="flex justify-between border-b border-[#EAEAEA] pb-1">
                            <dt class="text-brand-gray-light">Сборы чартерной компании</dt>
                            <dd class="text-[#2E325C]">{{ $yacht->charterFeeLabel() }}</dd>
                        </div>
                    @endif
                    @if ($yacht->depositLabel())
                        <div class="flex justify-between border-b border-[#EAEAEA] pb-1">
                            <dt class="text-brand-gray-light">Депозит</dt>
                            <dd class="text-[#2E325C]">{{ $yacht->depositLabel() }}</dd>
                        </div>
                    @endif
                    @if ($yacht->cabinsLabel())
                        <div class="flex justify-between border-b border-[#EAEAEA] pb-1">
                            <dt class="text-brand-gray-light">Каюты</dt>
                            <dd class="text-[#2E325C]">{{ $yacht->cabinsLabel() }}</dd>
                        </div>
                    @endif
                    @if ($yacht->effectiveDownwindSail())
                        <div class="flex justify-between border-b border-[#EAEAEA] pb-1">
                            <dt class="text-brand-gray-light">Парус полных курсов</dt>
                            <dd class="text-[#2E325C]">{{ $yacht->effectiveDownwindSail()->label() }}</dd>
                        </div>
                    @endif
                    @if ($yacht->hasSkipper())
                        <div class="flex justify-between border-b border-[#EAEAEA] pb-1">
                            <dt class="text-brand-gray-light">Шкипер</dt>
                            <dd class="text-[#2E325C]">{{ $yacht->skipper_name }}</dd>
                        </div>
                    @endif
                    @if ($yacht->sellsSeats() && $yacht->seatPriceLabel())
                        <div class="flex justify-between border-b border-[#EAEAEA] pb-1">
                            <dt class="text-brand-gray-light">Место в экипаже</dt>
                            <dd class="text-[#2E325C] font-semibold">{{ $yacht->seatPriceLabel() }}</dd>
                        </div>
                    @endif
                </dl>

                @if (count($photos) > 0)
                    <x-photo-gallery :photos="$photos" />
                @endif

                @if ($cta)
                    <div class="mt-5">
                        <button type="button"
                                @click="details = false; $dispatch('{{ $requestEvent }}', { payload: @js($ctaPayload), label: @js($ctaDetail) })"
                                class="bg-[#2D92CE] text-white py-3 px-8 hover:bg-[#0074CC] transition-colors font-semibold">
                            {{ $cta }} →
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
