{{--
    Вывод контента из RichEditor + лайтбокс для блоков «Галерея».

    Слушатель кликов живёт здесь, а не в самом блоке: разметка блока проходит через
    санитайзер, который вырезает любые alpine-атрибуты (см. rich-content/gallery.blade.php).
--}}
@props(['content' => null])

@php
    $html = \App\Support\RichContent::render($content);
@endphp

@if (filled($html))
    <div x-data="{
        open: false,
        images: [],
        index: 0,
        get current() {
            return this.images[this.index] ?? '';
        },
        show(event) {
            const link = event.target.closest('.rich-gallery__link');

            if (! link) return;

            event.preventDefault();

            this.images = Array.from(this.$refs.body.querySelectorAll('.rich-gallery__link'))
                .map(item => item.getAttribute('href'));
            this.index = Math.max(0, this.images.indexOf(link.getAttribute('href')));
            this.open = true;
        },
        prev() {
            if (this.images.length === 0) return;
            this.index = (this.index - 1 + this.images.length) % this.images.length;
        },
        next() {
            if (this.images.length === 0) return;
            this.index = (this.index + 1) % this.images.length;
        },
    }">
        <div x-ref="body"
             @click="show($event)"
             {{ $attributes->merge(['class' => 'text prose max-w-none space-y-4 text-lg']) }}>
            {!! $html !!}
        </div>

        {{-- Лайтбокс --}}
        <div x-show="open"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/70"
             @keydown.escape.window="open = false"
             @keydown.left.window="if (open) prev()"
             @keydown.right.window="if (open) next()"
             x-trap.noscroll="open">
            <div @click.away="open = false" class="relative w-full max-w-[1000px] max-h-[90vh] mx-4">
                <button @click="open = false"
                        aria-label="Закрыть"
                        class="absolute -top-10 right-0 text-white text-3xl z-50 hover:opacity-70">&times;</button>

                <div class="relative flex items-center justify-center">
                    <button @click="prev()"
                            x-show="images.length > 1"
                            aria-label="Предыдущее"
                            class="absolute left-2 z-50 p-2 text-white bg-[#2D92CE]/80 hover:bg-[#2D92CE] rounded-full transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>

                    <img :src="current"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="scale-90 opacity-0"
                         x-transition:enter-end="scale-100 opacity-100"
                         class="w-full max-h-[80vh] object-contain"
                         alt="Изображение из галереи">

                    <button @click="next()"
                            x-show="images.length > 1"
                            aria-label="Следующее"
                            class="absolute right-2 z-50 p-2 text-white bg-[#2D92CE]/80 hover:bg-[#2D92CE] rounded-full transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>

                {{-- Миниатюры --}}
                <div class="flex gap-2 overflow-x-auto mt-4 justify-center pb-2" x-show="images.length > 1">
                    <template x-for="(image, idx) in images" :key="idx">
                        <div class="cursor-pointer shrink-0 max-w-[60px] aspect-square snap-center"
                             :class="idx === index ? 'ring-2 ring-[#2D92CE]' : ''"
                             @click="index = idx">
                            <img :src="image" class="object-cover h-full w-full" alt="Миниатюра">
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
@endif
