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

    С ценами наоборот: унаследованные здесь не печатаются — общие цены
    дивизиона стоят один раз над списком лодок, и повторять их в каждой
    карточке значит спорить с самим собой. В карточке остаются только свои
    (@see App\Models\ForeignRegattaYacht::ownPriceLabels()).

    Кнопки выводятся из заполненных цен, а не задаются отдельно: одна лодка
    может одновременно продавать места, каюты и себя целиком
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

    // Только свои цены: общие цены дивизиона-флота уже напечатаны над списком
    // лодок (@see App\Models\ForeignRegattaYacht::ownPriceLabels()).
    $prices = $yacht->ownPriceLabels();
    $hasPrices = count(array_filter([$prices['charter'], $prices['fee'], $prices['deposit'], $prices['note']])) > 0;
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
                <img src="{{ $cover['src'] }}" alt="{{ $yacht->shortTitle() }}" loading="lazy"
                     class="w-full h-48 object-cover">
            </picture>
        </button>
    @endif

    <div class="p-5 flex flex-col grow">
        <h3 class="a-font text-xl text-[#2E325C] mb-1">{{ $yacht->shortTitle() }}</h3>

        @if (count($specs) > 0)
            <div class="text-brand-gray-light text-sm mb-3">{{ implode(' · ', $specs) }}</div>
        @endif

        {{-- ===== Стоимость чартера ===== --}}
        @if ($hasPrices)
            <div class="text-sm space-y-1 mb-3">
                @if ($prices['charter'])
                    <div class="text-[#2E325C] font-semibold">{{ $prices['charter'] }}</div>
                @endif
                @if ($prices['fee'])
                    <div class="text-brand-gray-light">Сборы чартерной компании — {{ $prices['fee'] }}</div>
                @endif
                @if ($prices['deposit'])
                    <div class="text-brand-gray-light">Депозит — {{ $prices['deposit'] }}</div>
                @endif
                @if ($prices['note'])
                    <div class="text-brand-gray-light text-xs">{{ $prices['note'] }}</div>
                @endif
            </div>
        @endif

        {{-- ===== Шкипер и места =====
             Места продаются и без шкипера, а шкипер бывает и у лодки, которую
             берут целиком, — поэтому блок показывается по любому из поводов. --}}
        @if ($yacht->hasSkipper() || $yacht->sellsSeats() || $yacht->sellsCabins())
            <div class="text-sm border-t border-[#EAEAEA] pt-3 mb-3">
                @if ($yacht->hasSkipper())
                    <div class="text-[#2E325C]">Шкипер — {{ $yacht->skipper_name }}</div>
                    @if ($yacht->skipper_note)
                        <div class="text-brand-gray-light text-xs mt-1">{{ $yacht->skipper_note }}</div>
                    @endif
                @endif

                @if ($yacht->sellsSeats())
                    <div class="text-brand-gray-light mt-2">
                        {{ ucfirst($yacht->freeSeatsLabel()) }}@if ($prices['seat']) по {{ $prices['seat'] }}@endif
                    </div>
                @elseif ($yacht->sellsCabins())
                    <div class="text-brand-gray-light mt-2">{{ ucfirst($yacht->freeSeatsLabel()) }}</div>
                @elseif ($yacht->hasSkipper())
                    {{-- Места могут быть свободны, но без цены не продаются. --}}
                    <div class="text-brand-gray-light mt-2">Мест в продаже нет</div>
                @endif

                @if ($yacht->sellsCabins() && $prices['cabin'])
                    <div class="text-brand-gray-light mt-1">Каюта целиком — {{ $prices['cabin'] }}</div>
                @endif

                @if ($yacht->seat_note && ($yacht->sellsSeats() || $yacht->sellsCabins()))
                    <div class="text-brand-gray-light text-xs mt-1">{{ $yacht->seat_note }}</div>
                @endif
            </div>
        @endif

        <div class="mt-auto pt-2 flex flex-col gap-3">
            <x-foreign-yacht-cta :yacht="$yacht" :request-event="$requestEvent" />

            <div class="flex flex-wrap items-center gap-3">
                {{-- Занятость показывается сама по себе: она про лодку целиком,
                     а места у неё могут продаваться и дальше. --}}
                @unless ($yacht->isAvailable())
                    <span class="inline-block text-xs px-2 py-1 bg-gray-200 text-brand-gray-light">Целиком — {{ mb_strtolower($yacht->status->label()) }}</span>
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
