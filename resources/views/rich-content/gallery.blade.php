{{--
    Блок «Галерея» внутри текста (App\Filament\RichEditor\CustomBlocks\GalleryBlock).

    Разметка статическая: результат проходит через Str::sanitizeHtml(), который вырезает
    x-data / @click / data-* / <template>. Интерактивность навешивает <x-rich-content>
    по классам .rich-gallery__link, поэтому без JS галерея остаётся рабочей —
    ссылки просто открывают полноразмерный снимок.
--}}
@php
    $gridClass = match ($columns) {
        2 => 'sm:grid-cols-2',
        4 => 'sm:grid-cols-2 lg:grid-cols-4',
        default => 'sm:grid-cols-2 lg:grid-cols-3',
    };
@endphp

<div class="rich-gallery not-prose my-8">
    @if ($title)
        <h3 class="section-title a-font text-2xl md:text-3xl mb-6">{{ $title }}</h3>
    @endif

    <div class="grid grid-cols-1 {{ $gridClass }} gap-4">
        @foreach ($images as $image)
            <a href="{{ $image['url'] }}"
               class="rich-gallery__link block overflow-hidden cursor-pointer group"
               target="_blank"
               rel="noopener">
                <img src="{{ $image['url'] }}"
                     alt="{{ $title ?? $image['name'] }}"
                     loading="lazy"
                     class="w-full h-52 object-cover transition-transform duration-500 group-hover:scale-105">
            </a>
        @endforeach
    </div>
</div>
