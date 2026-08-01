@props([
    // App\Models\GiftCertificate
    'certificate',
    // App\Enums\ServiceType — нужен кнопке заказа.
    'type',
])

{{--
    Карточка подарочного сертификата.

    Отдельной страницы у сертификата нет: описание и условия раскрываются прямо
    в карточке, поэтому её id — цель ссылки GiftCertificate::subjectUrl() из
    админки. Кнопка заказа передаёт сам сертификат объектом заявки: от него
    зависят номиналы в форме.
--}}
@php
    $cover = $certificate->getFirstMedia('cover');
    $validity = $certificate->validityLabel();
    $details = trim(strip_tags((string) $certificate->content, '<img>')) !== ''
        || trim((string) $certificate->terms) !== '';
@endphp

<div id="{{ $certificate->anchor() }}" class="flex flex-col bg-[#F8F8F8] overflow-hidden scroll-mt-24">
    <div class="overflow-hidden h-52">
        @if ($cover)
            <x-responsive-picture :media="$cover" :alt="$certificate->title"
                img-class="w-full h-52 object-cover" />
        @else
            <img class="w-full h-52 object-cover" src="{{ asset('images/gallery.png') }}" alt="{{ $certificate->title }}">
        @endif
    </div>

    <div class="p-4 flex flex-col grow">
        <h3 class="text-[#2E325C] text-lg font-semibold mb-2">{{ $certificate->title }}</h3>

        <div class="text-[#2E325C] font-semibold mb-2">{{ $certificate->priceLabel() }}</div>

        @if ($certificate->summary)
            <p class="text-brand-gray font-medium text-sm mb-2">{{ $certificate->summary }}</p>
        @endif

        @if ($validity)
            <div class="inline-block self-start text-xs px-2 py-1 mb-2 bg-[#DCEBE3] text-[#2E325C]">{{ $validity }}</div>
        @endif

        @if ($certificate->price_note)
            <p class="text-brand-gray-light text-sm mb-2">{{ $certificate->price_note }}</p>
        @endif

        @if ($details)
            <div x-data="{ open: false }" class="mb-4">
                <button type="button" @click="open = !open"
                        class="flex items-center gap-1 text-[#2D92CE] font-semibold text-sm">
                    <span x-text="open ? 'Свернуть' : 'Подробнее о сертификате'"></span>
                    <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div x-show="open" x-cloak x-transition class="mt-3">
                    @if ($certificate->content)
                        <div class="prose max-w-none text-brand-gray text-sm">
                            <x-rich-content :content="$certificate->content" />
                        </div>
                    @endif

                    @if ($certificate->terms)
                        <div class="mt-3">
                            <div class="text-[#2E325C] font-semibold text-sm mb-1">Условия использования</div>
                            <p class="text-brand-gray-light text-sm whitespace-pre-line">{{ $certificate->terms }}</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <div class="mt-auto pt-2">
            <x-service-request-button
                :type="$type"
                :subject="$certificate"
                label="Заказать"
                heading="Заказ подарочного сертификата" />
        </div>
    </div>
</div>
