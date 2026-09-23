@props([
    // App\Models\ForeignRegattaYacht
    'yacht',
    // Имя window-события общей формы заявки; null — заявки регата не принимает.
    'requestEvent' => null,
])

{{--
    Модалка «Подробнее» по лодке: характеристики, цены, галерея и кнопки заявки.

    Общая для карточки лодки и строки таблицы флота, поэтому видимостью не
    управляет — открывается из переменной `details` родительского x-data.
--}}
@php
    $photos = $yacht->effectivePhotos();
    $description = trim((string) $yacht->effectiveDescription());

    $specs = array_values(array_filter([
        $yacht->cabinsLabel(),
        $yacht->effectiveYear() ? $yacht->effectiveYear().' г.' : null,
        $yacht->effectiveDownwindSail()?->label(),
    ]));

    // Порядок как на кнопках: место, каюта, яхта целиком, затем сопутствующее.
    $rows = array_filter([
        'Место в экипаже' => $yacht->sellsSeats() ? $yacht->seatPriceLabel() : null,
        'Двухместная каюта' => $yacht->sellsCabins() ? $yacht->cabinPriceLabel() : null,
        'Стоимость чартера' => $yacht->priceLabel(),
        'Сборы чартерной компании' => $yacht->charterFeeLabel(),
        'Депозит' => $yacht->depositLabel(),
        'Каюты' => $yacht->cabinsLabel(),
        'Парус полных курсов' => $yacht->effectiveDownwindSail()?->label(),
        'Шкипер' => $yacht->hasSkipper() ? $yacht->skipper_name : null,
        // Занятость — про лодку целиком; места у неё могут продаваться дальше.
        'Яхта целиком' => $yacht->isAvailable() ? null : $yacht->status->label(),
        'Свободных мест' => $yacht->sellsSeats() || $yacht->sellsCabins()
            ? (string) $yacht->freeSeats()
            : null,
    ], fn (?string $value): bool => $value !== null && $value !== '');
@endphp

<div x-show="details" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-black/50 z-20"></div>

    <div @click.outside="details = false"
         class="px-3 py-3 relative overflow-y-auto max-h-[90vh] bg-white w-full max-w-[720px] z-30 top-1/2 left-1/2 -translate-1/2">
        <div class="p-3.5 md:p-4">
            <div class="flex items-start justify-between gap-4 mb-3">
                <h3 class="a-font text-2xl md:text-3xl text-[#2E325C]">{{ $yacht->shortTitle() }}</h3>
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
                @foreach ($rows as $term => $value)
                    <div class="flex justify-between border-b border-[#EAEAEA] pb-1">
                        <dt class="text-brand-gray-light">{{ $term }}</dt>
                        <dd class="text-[#2E325C]">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($yacht->effectivePriceNote())
                <p class="text-brand-gray-light text-xs mb-5">{{ $yacht->effectivePriceNote() }}</p>
            @endif

            @if (count($photos) > 0)
                <x-photo-gallery :photos="$photos" />
            @endif

            <x-foreign-yacht-cta :yacht="$yacht" :request-event="$requestEvent" close-details class="mt-5" />
        </div>
    </div>
</div>
