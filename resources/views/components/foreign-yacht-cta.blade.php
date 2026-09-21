@props([
    // App\Models\ForeignRegattaYacht
    'yacht',
    // Имя window-события общей формы заявки; null — заявки регата не принимает.
    'requestEvent' => null,
    // Кнопка стоит внутри модалки лодки — её нужно закрыть перед открытием формы.
    'closeDetails' => false,
    // Узкие кнопки для строки таблицы.
    'compact' => false,
])

{{--
    Кнопки вариантов участия по одной лодке.

    Кнопок столько, сколько вариантов лодка предлагает: со шкипером продаются
    места и каюты, без шкипера лодка сдаётся целиком
    (@see App\Models\ForeignRegattaYacht::offeredParticipations()). Цена варианта
    стоит прямо на кнопке — по ТЗ посетитель выбирает ценой, а не разбирается в
    форме, что ему доступно.

    Нажатие открывает одну общую форму заявки событием и подставляет в неё
    вариант и лодку (@see components/service-request-button).
--}}
@php
    $options = $requestEvent === null ? [] : $yacht->offeredParticipations();
    $sizeClasses = $compact ? 'py-1.5 px-3 text-xs' : 'py-2 px-5 text-sm';
@endphp

@if (count($options) > 0)
    <div {{ $attributes->merge(['class' => 'flex flex-wrap gap-2']) }}>
        @foreach ($options as $option)
            @php
                $price = $yacht->participationPriceLabel($option);

                $payload = [
                    'participation' => $option->value,
                    $option->payloadField() => (string) $yacht->getKey(),
                ];

                $detail = $yacht->title().' — '.mb_strtolower($option->label())
                    .($price === null ? '' : ', '.$price);
            @endphp

            <button type="button"
                    @click="{{ $closeDetails ? 'details = false; ' : '' }}$dispatch('{{ $requestEvent }}', { payload: @js($payload), label: @js($detail) })"
                    class="bg-[#2D92CE] text-white {{ $sizeClasses }} hover:bg-[#0074CC] transition-colors font-semibold">
                {{ $option->shortLabel() }}@if ($price) — {{ $price }}@endif
            </button>
        @endforeach
    </div>
@endif
