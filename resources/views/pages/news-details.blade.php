<x-public-layout>
<x-breadcrumbs_page :title="$news->title">
</x-breadcrumbs_page>
<main class="main">
    <section class="py-12">
        <div class="container mx-auto flex flex-col md:flex-row gap-12 justify-between">
            <div class="content max-w-[902px]">
                <h2 class="section-title a-font text-5xl mb-4">{{ $news->title }}</h2>
                <p class="date text-brand-gray-light mb-4">{{ $news->published_at->isoFormat('D MMMM Y') }}</p>

                @if($news->cover_image_url)
                    <div class="img mb-4">
                        <img class="w-full" src="{{ asset('storage/' . $news->cover_image_url) }}" alt="{{ $news->title }}">
                    </div>
                @endif

                <div class="text prose max-w-none space-y-4 text-lg">
                    {!! $news->content !!}
                </div>

                {{-- Галерея новости — изображения из медиа-коллекции 'gallery' --}}
                @php
                    $galleryImages = $news->getMedia('gallery');
                @endphp

                @if($galleryImages->isNotEmpty())
                    <div class="mt-10" x-data="{
                        lightboxOpen: false,
                        activeImage: '',
                        imagesList: [],
                        get currentIndex() {
                            return this.imagesList.indexOf(this.activeImage);
                        },
                        prevImage() {
                            if (this.imagesList.length === 0) return;
                            const newIndex = (this.currentIndex - 1 + this.imagesList.length) % this.imagesList.length;
                            this.activeImage = this.imagesList[newIndex];
                        },
                        nextImage() {
                            if (this.imagesList.length === 0) return;
                            const newIndex = (this.currentIndex + 1) % this.imagesList.length;
                            this.activeImage = this.imagesList[newIndex];
                        },
                        open(img) {
                            this.imagesList = {{ Js::from($galleryImages->map(fn($m) => $m->getUrl())->values()->toArray()) }};
                            this.activeImage = img;
                            this.lightboxOpen = true;
                        }
                    }">
                        <h3 class="section-title a-font text-3xl mb-6">Фотогалерея</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($galleryImages as $media)
                                <div class="overflow-hidden cursor-pointer group"
                                     @click="open('{{ $media->getUrl() }}')">
                                    <x-responsive-picture :media="$media"
                                        alt="{{ $media->name }}"
                                        img-class="w-full h-52 object-cover transition-transform duration-500 group-hover:scale-105" />
                                </div>
                            @endforeach
                        </div>

                        {{-- Лайтбокс --}}
                        <div x-show="lightboxOpen"
                             x-cloak
                             class="fixed inset-0 z-50 flex items-center justify-center bg-black/70"
                             @keydown.left.window="prevImage()"
                             @keydown.right.window="nextImage()"
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

                                    <img :src="activeImage"
                                         x-transition:enter="transition ease-out duration-300"
                                         x-transition:enter-start="scale-90 opacity-0"
                                         x-transition:enter-end="scale-100 opacity-100"
                                         class="w-full max-h-[80vh] object-contain"
                                         alt="Full size">

                                    <button @click="nextImage()"
                                            aria-label="Следующее"
                                            class="absolute right-2 z-50 p-2 text-white bg-[#2D92CE]/80 hover:bg-[#2D92CE] rounded-full transition-all">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </button>
                                </div>

                                {{-- Миниатюры --}}
                                <div class="flex gap-2 overflow-x-auto mt-4 justify-center pb-2">
                                    <template x-for="(img, idx) in imagesList" :key="idx">
                                        <div class="cursor-pointer shrink-0 max-w-[60px] aspect-square snap-center"
                                             :class="activeImage === img ? 'ring-2 ring-[#2D92CE]' : ''"
                                             @click="activeImage = img">
                                            <img :src="img"
                                                 class="object-cover h-full w-full"
                                                 alt="Preview">
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
                
            </div>

            {{-- Другие новости (сайдбар) --}}
            <div class="aside max-w-full md:max-w-[490px] flex-1">
                <h3 class="section-title a-font text-lg md:text-3xl mb-4 text-center">Другие новости</h3>
                @if($otherNews->isNotEmpty())
                    <div class="col flex flex-col gap-8">
                        @foreach($otherNews as $other)
                            <div class="item flex">
                                <div class="overflow-hidden md:h-52 shrink-0 max-w-[200px]">
                                    <img
                                        class="w-full max-w-[150px] md:max-w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                        style="object-position: {{ $other->cover_object_position ?? 'center' }}"
                                        src="{{ $other->cover_image_url ? asset('storage/' . $other->cover_image_url) : asset('images/gallery.png') }}"
                                        alt="{{ $other->title }}"
                                    >
                                </div>
                                <div class="info p-2 bg-[#F8F8F8]">
                                    <h4 class="text-sm md:text-lg font-semibold mb-3">{{ $other->title }}</h4>
                                    <p class="mb-3 font-medium text-xs md:text-base">{{ Str::limit(strip_tags($other->content), 30) }}</p>
                                    <div class="date mb-3 text-brand-gray-light text-xs md:text-base">{{ $other->published_at->isoFormat('D MMMM Y') }}</div>
                                    <a href="{{ route('news-details', $other) }}" class="text-xs md:text-lg font-semibold hover:underline">Читать далее →</a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <a href="{{ route('news') }}" class="mx-auto mt-6 block text-lg font-semibold hover:underline text-center">Показать все →</a>
                @else
                    <p class="text-center text-brand-gray-light">Других новостей пока нет.</p>
                @endif
            </div>
        </div>
    </section>
</main>
</x-public-layout>
