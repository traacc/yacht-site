<x-public-layout>
<x-breadcrumbs_page title="Галерея">
</x-breadcrumbs_page>
@php
    $years = $galleries->keys()->sortDesc()->values();
    $waterAreas = $galleries->flatten()->pluck('water_area')->filter()->unique()->values();
@endphp
<main x-data="{
    gallery_modal_open: false,
    lightbox_open: false,
    activeImage: '',
    lbImages: [],
    activeGallery: null,
    activeTab: 'video',
    selectedYear: '{{ $years->first() }}',
    selectedWater: '',
    touchStartX: 0,
    get currentIndex() {
        return this.lbImages.indexOf(this.activeImage);
    },
    prevImage() {
        if (this.lbImages.length === 0) return;
        const newIndex = (this.currentIndex - 1 + this.lbImages.length) % this.lbImages.length;
        this.activeImage = this.lbImages[newIndex];
        this.$nextTick(() => this.scrollToThumb());
    },
    nextImage() {
        if (this.lbImages.length === 0) return;
        const newIndex = (this.currentIndex + 1) % this.lbImages.length;
        this.activeImage = this.lbImages[newIndex];
        this.$nextTick(() => this.scrollToThumb());
    },
    scrollToThumb() {
        const container = this.$refs.thumbStrip;
        if (!container) return;
        const activeThumb = container.querySelector('.thumb-item[aria-current]');
        if (activeThumb) {
            activeThumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }
    },
    thumbScroll(dir) {
        const container = this.$refs.thumbStrip;
        if (!container) return;
        container.scrollBy({ left: dir * 200, behavior: 'smooth' });
    },
}" class="main">
    <section class="md:py-12 py-4 reggata-list px-2 2xl:px-0">
        <div class="container mx-auto">
            <div class="flex justify-between mb-6 flex-col md:flex-row">
                <h2 class="section-title a-font text-3xl md:text-5xl mb-4 md:mb-0">Галерея</h2>
                <div class="controls flex gap-4">
                    <div class="calendar-icon">
                        <select x-model="selectedYear" class="border-[#C6C6C6] focus:outline-hidden h-full focus:ring-2 text-[#2E325C] pl-5 min-w-[140px]" name="year" id="">
                            @foreach($years as $year)
                                <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>

                    <select x-model="selectedWater" name="team_filter" id="team_filter" class="team_filter">
                        <option value="">Все акватории</option>
                        @foreach($waterAreas as $wa)
                            <option value="{{ $wa }}">{{ $wa }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </section>

    @foreach($galleries as $year => $items)
    <section x-show="selectedYear == '{{ $year }}' && (selectedWater === '' || selectedWater === '{{ $items->first()?->water_area }}')">
        <div class="container mx-auto pb-12 mb-4 border-b border-b-[#EAEAEA]">
            <h2 class="section-title a-font text-5xl">{{ $year }}</h2>
            <div class="grid md:grid-cols-3 gap-6 mt-6">
                @foreach($items as $gallery)
                <div class="cursor-pointer group relative"
                     @click="gallery_modal_open = true; activeGallery = {{ Js::from([
                         'name'       => $gallery->name,
                         'date'       => $gallery->date?->isoFormat('D MMMM YYYY'),
                         'date_short' => $gallery->date?->isoFormat('D–D MMMM'),
                         'water_area' => $gallery->water_area,
                         'location'   => $gallery->regatta?->location,
                         // ★ ИЗМЕНЕНО: аксессор cover_path теперь возвращает готовый URL (см. Gallery::getCoverPathAttribute)
                         'cover'      => $gallery->cover_path ?: asset('images/news/news_1.png'),
                         // ★ ИЗМЕНЕНО: аксессор images теперь возвращает массив готовых URL (см. Gallery::getImagesAttribute)
                         'images'     => $gallery->images,
                         // ★ ДОБАВЛЕНО: аксессор videos — массив готовых URL видеофайлов
                         'videos'     => $gallery->videos,
                     ]) }}">

                    {{-- ★ ИЗМЕНЕНО: аксессор cover_path уже возвращает готовый URL, Storage::disk()->url() не нужен --}}
                    <img src="{{ $gallery->cover_path ?: asset('images/news/news_1.png') }}"
                         alt="{{ $gallery->name }}"
                         class="absolute w-full h-full object-cover z-10 transition-transform duration-500 group-hover:scale-105">
                    <div class="bg-[#2E325C] opacity-90 absolute z-15 w-full h-full transition-transform duration-500 group-hover:scale-105"></div>
                    <div class="info relative z-20 p-6 pt-56 text-white">
                        <h4 class="title a-font text-2xl mb-3">{{ $gallery->name }}</h4>
                        <p class="mb-3 flex gap-3">{!! file_get_contents(public_path('images/icons/calendar.svg')) !!} {{ $gallery->date?->isoFormat('D–D MMMM') }} · {{ $gallery->regatta?->location ?? '' }}</p>
                        <p class="flex gap-3">{!! file_get_contents(public_path('images/icons/waves.svg')) !!} {{ $gallery->water_area }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endforeach

    <div x-show="gallery_modal_open"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 team-modal">
        <!-- Модальное окно для подробной информации о галерее -->
        <div @click.away="gallery_modal_open = false"  class="relative p-6 max-w-[1200px] max-h-[80vh] overflow-y-auto bg-white gap-6"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
        >
            <div class="flex justify-between mb-4">
                <h3 class="text-3xl a-font" x-text="activeGallery?.name ?? ''"></h3>
                <div class="close">
                    <button @click="gallery_modal_open = false" class="text-2xl font-bold">&times;</button>
                </div>
            </div>
            <p class="text-lg mb-6" x-text="(activeGallery?.date_short ?? '') + ' · ' + (activeGallery?.water_area ?? '')"></p>
            <div class="flex gap-4 font-medium text-lg mb-6">
                <button @click="activeTab = 'video'" :class="activeTab === 'video' ? 'bg-[#2D92CE] text-white' : 'bg-[#F8F8F8] text-[#2E325C]'" class="p-4 text-center">Видео</button>
                <button @click="activeTab = 'photo'" :class="activeTab === 'photo' ? 'bg-[#2D92CE] text-white' : 'bg-[#F8F8F8] text-[#2E325C]'" class="p-4 text-center">Фотографии</button>
            </div>
            {{-- ★ ИЗМЕНЕНО: таб «Видео» теперь использует activeGallery.videos вместо activeGallery.images --}}
            <div x-show="activeTab === 'video'">
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <template x-for="item in activeGallery?.videos ?? []">
                        <div class="card bg-[#F8F8F8]">
                            <video class="h-full object-cover w-full" :src="item" controls preload="metadata"></video>
                        </div>
                    </template>
                    {{-- Если видео нет, показываем сообщение --}}
                    <div x-show="(activeGallery?.videos ?? []).length === 0" class="col-span-full text-center text-gray-500 py-8">
                        Видео пока нет
                    </div>
                </div>
            </div>
            {{-- Таб «Фотографии» — images уже возвращает готовые URL через аксессор --}}
            <div x-show="activeTab === 'photo'">
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <template x-for="item in activeGallery?.images ?? []">
                        <div class="card bg-[#F8F8F8]"  @click="lightbox_open = true; gallery_modal_open = false; activeImage = item; lbImages = activeGallery?.images ?? []">
                            <img class="h-full object-cover" :src="item" alt="">
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
    <div x-show="lightbox_open"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
        @keydown.left.window="prevImage()"
        @keydown.right.window="nextImage()"
        x-trap.noscroll="lightbox_open"
        @touchstart.passive="touchStartX = $event.touches[0].clientX"
        @touchend.passive="Math.abs($event.changedTouches[0].clientX - touchStartX) > 50 && ($event.changedTouches[0].clientX > touchStartX ? prevImage() : nextImage())"
        >
        <div @click.away="lightbox_open = false; gallery_modal_open = true" class="relative w-full max-w-[1200px] max-h-[90vh] px-2 md:px-0"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
        >
            <!-- Кнопка закрытия -->
            <button @click="lightbox_open = false; gallery_modal_open = true" class="absolute -top-10 right-2 md:-top-10 md:-right-4 text-white text-3xl z-50 hover:opacity-70">&times;</button>

            <!-- Контейнер с изображением и стрелками -->
            <div class="relative flex items-center justify-center">
                <!-- Стрелка назад -->
                <button @click="prevImage()"
                    aria-label="Предыдущее"
                    class="absolute left-1 md:-left-6 z-50 p-2 md:p-3 text-white bg-[#2D92CE]/80 hover:bg-[#2D92CE] rounded-full transition-all">
                    <svg class="w-4 h-4 md:w-5 md:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>

                <img :src="activeImage"
                    x-show="lightbox_open"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="scale-90 opacity-0"
                    x-transition:enter-end="scale-100 opacity-100"
                    class="w-[90vw] md:w-[75vw] h-[40vh] md:h-[60vh] object-contain"
                    alt="Full size">

                <!-- Стрелка вперёд -->
                <button @click="nextImage()"
                    aria-label="Следующее"
                    class="absolute right-1 md:-right-6 z-50 p-2 md:p-3 text-white bg-[#2D92CE]/80 hover:bg-[#2D92CE] rounded-full transition-all">
                    <svg class="w-4 h-4 md:w-5 md:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>

            <!-- Превью (миниатюры) -->
            <div class="relative flex items-center">
                <!-- Кнопка скролла влево -->
                <button @click="thumbScroll(-1)"
                    x-show="lbImages.length > 1"
                    aria-label="Прокрутить миниатюры влево"
                    class="absolute -left-4 z-50 p-1.5 text-white bg-[#2D92CE]/80 hover:bg-[#2D92CE] rounded-full transition-all hidden md:block">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>

                <div x-ref="thumbStrip"
                    class="flex gap-2 md:gap-4 overflow-x-auto mt-4 justify-start md:justify-center px-1 pb-2 no-scrollbar scroll-smooth snap-x snap-mandatory">
                    <template x-for="img in lbImages">
                        <div class="thumb-item cursor-pointer shadow-hover transition-all shrink-0 max-w-[60px] md:max-w-[100px] aspect-square snap-center"
                            :class="activeImage === img ? 'ring-2 ring-[#2D92CE]' : ''"
                            :aria-current="activeImage === img ? 'true' : undefined"
                            @click="activeImage = img; $nextTick(() => scrollToThumb())">
                            <img :src="img"
                                class="object-cover h-full w-full"
                                alt="Preview">
                        </div>
                    </template>
                </div>

                <!-- Кнопка скролла вправо -->
                <button @click="thumbScroll(1)"
                    x-show="lbImages.length > 1"
                    aria-label="Прокрутить миниатюры вправо"
                    class="absolute -right-4 z-50 p-1.5 text-white bg-[#2D92CE]/80 hover:bg-[#2D92CE] rounded-full transition-all hidden md:block">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>
        </div>
    </div>
    
</main>



<x-feedback-section>
    
</x-feedback-section>
</x-public-layout>