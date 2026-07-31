@props([
    // list<string> — пути на диске public либо готовые URL.
    'photos' => [],
    'title' => null,
])

{{--
    Сетка фотографий с лайтбоксом.

    Тот же лайтбокс, что на страницах кейсов ремонта и галереи, но вынесенный в
    компонент: в «Услугах» галерея нужна сразу двум подразделам. Существующие
    страницы на компонент пока не переведены — это отдельный рефактор.
--}}
@php
    $urls = collect($photos)
        ->flatten()
        ->filter(fn ($photo): bool => is_string($photo) && $photo !== '')
        ->map(fn (string $photo): string => str_starts_with($photo, 'http') || str_starts_with($photo, '/')
            ? $photo
            : \Illuminate\Support\Facades\Storage::disk('public')->url($photo))
        ->values()
        ->all();
@endphp

@if (count($urls) > 0)
    <div x-data="{
            lightboxOpen: false,
            activeIndex: 0,
            images: {{ Js::from($urls) }},
            prevImage() {
                if (this.images.length === 0) return;
                this.activeIndex = (this.activeIndex - 1 + this.images.length) % this.images.length;
            },
            nextImage() {
                if (this.images.length === 0) return;
                this.activeIndex = (this.activeIndex + 1) % this.images.length;
            },
            open(index) {
                this.activeIndex = index;
                this.lightboxOpen = true;
            }
        }">
        @if ($title)
            <h2 class="section-title a-font text-2xl md:text-3xl mb-6">{{ $title }}</h2>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($urls as $index => $url)
                <div class="overflow-hidden cursor-pointer group" @click="open({{ $index }})">
                    <img src="{{ $url }}" alt="" loading="lazy"
                         class="w-full h-52 object-cover transition-transform duration-500 group-hover:scale-105">
                </div>
            @endforeach
        </div>

        {{-- Лайтбокс --}}
        <div x-show="lightboxOpen"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/70"
             @keydown.left.window="prevImage()"
             @keydown.right.window="nextImage()"
             @keydown.escape.window="lightboxOpen = false"
             x-trap.noscroll="lightboxOpen">
            <div @click.away="lightboxOpen = false"
                 class="relative w-full max-w-[1000px] max-h-[90vh] mx-4">
                <button @click="lightboxOpen = false"
                        class="absolute -top-10 right-0 text-white text-3xl z-50 hover:opacity-70">&times;</button>

                <div class="relative flex items-center justify-center">
                    <button @click="prevImage()"
                            aria-label="Предыдущее"
                            class="absolute left-2 z-50 p-2 text-white bg-[#2D92CE]/80 hover:bg-[#2D92CE] rounded-full transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>

                    <img :src="images[activeIndex]"
                         class="w-full max-h-[80vh] object-contain"
                         alt="">

                    <button @click="nextImage()"
                            aria-label="Следующее"
                            class="absolute right-2 z-50 p-2 text-white bg-[#2D92CE]/80 hover:bg-[#2D92CE] rounded-full transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>

                <div class="flex gap-2 overflow-x-auto mt-4 justify-center pb-2">
                    <template x-for="(img, idx) in images" :key="idx">
                        <div class="cursor-pointer shrink-0 max-w-[60px] aspect-square snap-center"
                             :class="activeIndex === idx ? 'ring-2 ring-[#2D92CE]' : ''"
                             @click="activeIndex = idx">
                            <img :src="img" class="object-cover h-full w-full" alt="">
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
@endif
