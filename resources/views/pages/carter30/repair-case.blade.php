@php
    $photos = $case->galleryPhotos();
    $videos = $case->videos();
    $drawings = $case->getMedia('drawings');
@endphp
<x-public-layout :title="$case->title . ' — ремонт и модернизация Carter 30'" :description="Str::limit(strip_tags($case->summary ?: $case->content), 160)">
<x-breadcrumbs_page :title="$case->title">
</x-breadcrumbs_page>

<main class="main">
    <section class="py-12 px-4 sm:px-6 lg:px-8">
        <div class="container mx-auto max-w-[1000px]">
            <h1 class="section-title a-font text-3xl md:text-5xl mb-4">{{ $case->title }}</h1>

            @if ($case->yacht)
                <p class="text-brand-gray-light mb-4">Яхта: {{ $case->yacht->name }}</p>
            @endif

            @php($cover = $case->getFirstMedia('cover'))
            @if ($cover)
                <div class="img mb-6">
                    <x-responsive-picture :media="$cover" :alt="$case->title" img-class="w-full" />
                </div>
            @endif

            @if ($case->summary)
                <p class="text-brand-gray font-medium text-lg mb-6">{{ $case->summary }}</p>
            @endif

            @if (trim(strip_tags($case->content ?? '', '<img>')) !== '')
                <div class="text prose max-w-none space-y-4 text-lg">
                    {!! $case->content !!}
                </div>
            @endif

            {{-- ===== Чертежи и документы ===== --}}
            @if ($drawings->isNotEmpty())
                <div class="mt-10">
                    <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Чертежи и документы</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach ($drawings as $drawing)
                            <a href="{{ $drawing->getUrl() }}" target="_blank"
                               class="bg-[#F8F8F8] flex gap-4 items-center p-4 hover:shadow-md transition-shadow">
                                <img class="max-w-12" src="{{ asset('images/icons/pdf.png') }}" alt="">
                                <span class="text-[#2E325C] font-semibold">{{ $drawing->name ?: $drawing->file_name }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ===== Фотографии с подписями ===== --}}
            @if (count($photos) > 0)
                <div class="mt-10" x-data="{
                    lightboxOpen: false,
                    activeIndex: 0,
                    images: {{ Js::from(array_column($photos, 'src')) }},
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
                    <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Фотографии</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach ($photos as $index => $photo)
                            <figure class="group">
                                <div class="overflow-hidden cursor-pointer" @click="open({{ $index }})">
                                    <picture>
                                        @if ($photo['avif'])<source srcset="{{ $photo['avif'] }}" type="image/avif">@endif
                                        @if ($photo['webp'])<source srcset="{{ $photo['webp'] }}" type="image/webp">@endif
                                        <img src="{{ $photo['src'] }}" alt="{{ $photo['caption'] }}"
                                             class="w-full h-52 object-cover transition-transform duration-500 group-hover:scale-105">
                                    </picture>
                                </div>
                                @if ($photo['caption'] !== '')
                                    <figcaption class="text-sm text-brand-gray-light mt-2">{{ $photo['caption'] }}</figcaption>
                                @endif
                            </figure>
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

            {{-- ===== Видео с подписями ===== --}}
            @if (count($videos) > 0)
                <div class="mt-10">
                    <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Видео</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @foreach ($videos as $video)
                            <figure>
                                <div class="aspect-video bg-black">
                                    <iframe src="{{ $video['embed_url'] }}"
                                            class="w-full h-full"
                                            frameborder="0"
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                            allowfullscreen></iframe>
                                </div>
                                @if ($video['caption'] !== '')
                                    <figcaption class="text-sm text-brand-gray-light mt-2">{{ $video['caption'] }}</figcaption>
                                @endif
                            </figure>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ===== Кнопка заявки ===== --}}
            <div class="mt-12 bg-[#F8F8F8] p-8 text-center">
                <h2 class="section-title a-font text-[#2E325C] md:text-3xl text-2xl mb-4">Хотите такой ремонт?</h2>
                <p class="text-brand-gray font-medium md:text-lg text-sm mb-6">Оставьте заявку — обсудим объём работ и сроки по вашей яхте.</p>
                <x-repair-request-button :case="$case" />
            </div>

            <div class="mt-8">
                <a href="{{ route('carter30.repair') }}" class="text-[#2D92CE] font-semibold hover:underline">← Все проекты</a>
            </div>
        </div>
    </section>
</main>
</x-public-layout>
