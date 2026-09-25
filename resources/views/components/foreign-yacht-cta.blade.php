@props([
    // App\Models\ForeignRegattaYacht или App\Models\ForeignRegattaDivision —
    // кто предлагает вариант: конкретная лодка или дивизион, продающий общим
    // пулом (@see App\Models\ForeignRegattaDivision::sellsDirectly()).
    'seller',
    // Подпись источника в шапке формы заявки.
    'sellerLabel' => null,
    // Имя window-события общей формы заявки; null — заявки регата не принимает.
    'requestEvent' => null,
    // Кнопка стоит внутри модалки лодки — её нужно закрыть перед открытием формы.
    'closeDetails' => false,
    // Узкие кнопки для строки таблицы.
    'compact' => false,
])

{{--
    Кнопки вариантов участия.

    Кнопок столько, сколько вариантов предлагается: место, место в одноместной
    каюте, каюта, яхта целиком — каждый со своей ценой и своим остатком
    (@see App\Models\ForeignRegattaYacht::offeredParticipations()). Цена стоит
    прямо на кнопке: посетитель выбирает ценой, а не разбирается в форме.

    Нажатие открывает одну общую форму заявки событием и подставляет в неё
    вариант и источник (@see components/service-request-button).
--}}
@php
    $options = $requestEvent === null ? [] : $seller->offeredParticipations();
    $sizeClasses = $compact ? 'py-1.5 px-3 text-xs' : 'py-2 px-5 text-sm';
    $sellerLabel ??= $seller->title();
@endphp

@if (count($options) > 0)
    <div {{ $attributes->merge(['class' => 'flex flex-wrap gap-2']) }}>
        @foreach ($options as $option)
            @php
                $price = $seller->participationPriceLabel($option);
                $left = $seller->occupancyLabel($option);

                $payload = [
                    'participation' => $option->value,
                    $option->payloadField() => \App\Models\ForeignRegatta::offerKey($seller),
                ];

                $detail = $sellerLabel.' — '.mb_strtolower($option->label())
                    .($price === null ? '' : ', '.$price)
                    .($left === null ? '' : ', '.$left);
            @endphp

            <button type="button"
                    @click="{{ $closeDetails ? 'details = false; ' : '' }}$dispatch('{{ $requestEvent }}', { payload: @js($payload), label: @js($detail) })"
                    class="bg-[#2D92CE] text-white {{ $sizeClasses }} hover:bg-[#0074CC] transition-colors font-semibold">
                {{ $option->shortLabel() }}@if ($price) — {{ $price }}@endif
            </button>
        @endforeach
    </div>
@endif
