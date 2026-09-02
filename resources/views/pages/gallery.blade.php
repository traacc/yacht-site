@php
    // Страница открыта по адресу альбома (/gallery/{slug}) — тогда и заголовок,
    // и og:image берём у альбома: ссылкой делятся, превью должно быть его.
    $openAlbum = $openAlbum ?? null;
    $metaTitle = $openAlbum
        ? trim(($openAlbum->regatta?->name ? $openAlbum->regatta->name.' — ' : '').$openAlbum->name).' — фото и видео'
        : 'Фото и видео с регат - галерея парусных гонок';
    $albumDate = $openAlbum?->regatta?->dateRange() ?? $openAlbum?->date?->isoFormat('D MMMM YYYY');
    $metaDescription = $openAlbum
        ? collect(['Фотографии альбома «'.$openAlbum->name.'»', $openAlbum->water_area, $albumDate])
            ->filter()
            ->implode(', ')
        : 'Профессиональные фотографии и репортажи с гонок. Старты, финиши, атмосфера регат и яркие моменты парусных соревнований';
@endphp
<x-public-layout :title="$metaTitle" :description="$metaDescription" :og-image="$openAlbum?->cover_path">
<x-breadcrumbs_page title="Галерея">
</x-breadcrumbs_page>
@php
    $years = $galleries->keys()->sortDesc()->values();
    $waterAreas = $galleries->flatten()->pluck('water_area')->filter()->unique()->values();

    // Карта «год галереи» по её id — тем же способом, что и группировка выше,
    // чтобы фильтр по регате мог автоматически переключить нужный год.
    $galleryYears = $galleries->flatten()
        ->mapWithKeys(fn ($g) => [$g->id => (string) ($g->season?->year ?? $g->date?->year ?? now()->year)]);

    // Уникальные регаты, у которых есть альбомы, отсортированные по дате (свежие сверху).
    $regattas = $galleries->flatten()
        ->filter(fn ($g) => $g->regatta)
        ->map(fn ($g) => $g->regatta)
        ->unique('id')
        ->sortByDesc('date_start')
        ->values();

    // Карта «id регаты → год», чтобы при выборе регаты выставить соответствующий год.
    $regattaYears = $galleries->flatten()
        ->filter(fn ($g) => $g->regatta)
        ->mapWithKeys(fn ($g) => [$g->regatta->id => $galleryYears[$g->id]])
        ->toArray();
@endphp
<main x-data="{
    gallery_modal_open: false,
    lightbox_open: false,
    activeImage: '',
    lbImages: [],
    activeGallery: null,
    activeTab: 'photo',
    {{-- Вкладка списка. Переключается из меню: ссылка «Видео» ведёт на #video,
         а на самой странице приходит событие switch-gallery-tab. --}}
    listTab: window.location.hash === '#video' ? 'video' : 'photo',
    selectedYear: '{{ $years->first() }}',
    selectedWater: '',
    selectedRegatta: '',
    regattaYears: {{ Js::from($regattaYears) }},
    touchStartX: 0,
    copied: false,
    {{-- Slug альбома, открытого по адресу /gallery/{slug} (см. route gallery.album). --}}
    initialAlbum: {{ Js::from($openAlbum?->slug ?? $openAlbum?->getKey()) }},
    {{-- Держит адрес в браузере в согласии с открытым альбомом и фильтром:
         /gallery/<slug-альбома>?regatta=<id>. Такую ссылку можно скопировать
         и позже открыть тот же альбом с тем же фильтром. --}}
    updateUrl() {
        const path = (this.gallery_modal_open && this.activeGallery?.slug)
            ? '{{ url('/gallery') }}/' + this.activeGallery.slug
            : '{{ route('gallery') }}';
        const params = new URLSearchParams();
        if (this.selectedRegatta) {
            params.set('regatta', this.selectedRegatta);
        }
        const qs = params.toString();
        history.replaceState(null, '', path + (qs ? '?' + qs : ''));
    },
    {{-- Выбор регаты: подстраиваем год под регату, сбрасываем акваторию и пишем фильтр в URL. --}}
    onRegattaChange() {
        if (this.selectedRegatta && this.regattaYears[this.selectedRegatta]) {
            this.selectedYear = this.regattaYears[this.selectedRegatta];
            this.selectedWater = '';
        }
        this.updateUrl();
    },
    {{-- При загрузке страницы: если в URL есть ?regatta=<id>, применяем фильтр по регате. --}}
    openFilterFromUrl() {
        const regatta = new URLSearchParams(window.location.search).get('regatta');
        if (!regatta) return;
        this.selectedRegatta = regatta;
        if (this.regattaYears[regatta]) this.selectedYear = this.regattaYears[regatta];
    },
    {{-- Открывает альбом: проставляет состояние модалки и пишет адрес альбома в URL,
         чтобы текущую ссылку можно было скопировать/расшарить. --}}
    openAlbum(gallery) {
        this.activeGallery = gallery;
        this.activeTab = this.listTab;
        this.gallery_modal_open = true;
        this.updateUrl();
    },
    {{-- Карточка альбома — обычная ссылка на /gallery/{slug}: её видно в статусной
         строке, можно скопировать и открыть в новой вкладке. Обычный клик
         перехватываем и открываем модалку без перезагрузки страницы. --}}
    openAlbumFromLink(event, gallery) {
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        this.openAlbum(gallery);
    },
    {{-- Закрывает модалку альбома и убирает ?album из URL, сохраняя активный фильтр. --}}
    closeAlbum() {
        this.gallery_modal_open = false;
        this.updateUrl();
    },
    {{-- Копирует ссылку на альбом / вызывает нативный «Поделиться» на мобильных. --}}
    shareAlbum() {
        const url = this.activeGallery?.url ?? '{{ route('gallery') }}';
        /*
        if (navigator.share) {
            navigator.share({ title: this.activeGallery?.name ?? 'Галерея', url }).catch(() => {});
            return;
        }
        */
        navigator.clipboard.writeText(url).then(() => {
            this.copied = true;
            //setTimeout(() => this.copied = false, 2000);
        });
    },
    {{-- При загрузке страницы открываем альбом: либо из адреса /gallery/{slug},
         либо из старой ссылки /gallery?album=<uuid|slug>. --}}
    openAlbumFromUrl() {
        const id = this.initialAlbum ?? new URLSearchParams(window.location.search).get('album');
        if (!id) return;
        const key = JSON.stringify(id);
        const card = document.querySelector('[data-album-slug=' + key + ']')
            ?? document.querySelector('[data-album-id=' + key + ']');
        if (!card) return;
        if (card.dataset.albumYear) this.selectedYear = card.dataset.albumYear;
        this.$nextTick(() => {
            card.click();
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    },
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
}" x-init="openFilterFromUrl(); openAlbumFromUrl()"
   x-on:switch-gallery-tab.window="listTab = $event.detail.tab"
   class="main">
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

                    @if($regattas->isNotEmpty())
                    <select x-model="selectedRegatta" @change="onRegattaChange()" name="regatta_filter" id="regatta_filter" class="team_filter">
                        <option value="">Все регаты</option>
                        @foreach($regattas as $regatta)
                            <option value="{{ $regatta->id }}">{{ $regatta->name }}{{ isset($regattaYears[$regatta->id]) ? ' ('.$regattaYears[$regatta->id].')' : '' }}</option>
                        @endforeach
                    </select>
                    @endif
                </div>
            </div>

            {{-- Табы «Фотографии / Видео» над списком. Управляют тем, на какую вкладку
                 откроется модальное окно при клике по карточке галереи. --}}
            <div class="flex gap-4 font-medium text-lg">
                <button @click="listTab = 'photo'" :class="listTab === 'photo' ? 'bg-[#2D92CE] text-white' : 'bg-[#F8F8F8] text-[#2E325C]'" class="p-4 text-center">Фотографии</button>
                <button @click="listTab = 'video'" :class="listTab === 'video' ? 'bg-[#2D92CE] text-white' : 'bg-[#F8F8F8] text-[#2E325C]'" class="p-4 text-center">Видео</button>
            </div>
        </div>
    </section>

    @foreach($galleries as $year => $items)
    {{-- ★ В режиме «Видео» скрываем год целиком, если в нём нет ни одного альбома с видео --}}
    <section x-show="selectedYear == '{{ $year }}' && (selectedWater === '' || selectedWater === '{{ $items->first()?->water_area }}') && (listTab === 'photo' || {{ $items->contains(fn ($g) => $g->videoLinks->isNotEmpty()) ? 'true' : 'false' }})">
        <div class="container mx-auto pb-12 mb-4 border-b border-b-[#EAEAEA]">
            <h2 class="section-title a-font text-5xl">{{ $year }}</h2>
            <div class="grid md:grid-cols-3 gap-6 mt-6">
                @foreach($items as $gallery)
                @php
                    // Данные альбома для модалки. Дублируются в JS, чтобы открытие
                    // альбома не ходило на сервер.
                    $albumData = [
                        'id'          => $gallery->id,
                        // slug и url — для адресной строки и кнопки «Поделиться».
                        'slug'        => $gallery->getRouteKey(),
                        'url'         => $gallery->publicUrl(),
                        'name'        => $gallery->name,
                        'date'        => $gallery->regatta?->dateRange() ?? $gallery->date?->isoFormat('D MMMM YYYY'),
                        'date_short'  => $gallery->regatta
                            ? ($gallery->regatta->date_start?->isSameDay($gallery->regatta->date_end ?? $gallery->regatta->date_start)
                                ? $gallery->regatta->date_start->isoFormat('D MMMM')
                                : $gallery->regatta->date_start->isoFormat('D').'–'.$gallery->regatta->date_end->isoFormat('D MMMM'))
                            : $gallery->date?->isoFormat('D MMMM'),
                        'water_area'  => $gallery->water_area,
                        'location'    => $gallery->regatta?->location,
                        // ★ ИЗМЕНЕНО: аксессор cover_path теперь возвращает готовый URL (см. Gallery::getCoverPathAttribute)
                        'cover'       => $gallery->cover_path ?: asset('images/news/news_1.webp'),
                        // ★ ИЗМЕНЕНО: аксессор images теперь возвращает массив готовых URL (см. Gallery::getImagesAttribute)
                        // Используется для лайтбокса (полноэкранный просмотр — оригинал).
                        'images'      => $gallery->images,
                        // Наборы URL {url, webp, avif} для грида-превью через <picture>.
                        'images_responsive' => $gallery->imagesResponsive(),
                        // ★ ДОБАВЛЕНО: video_links из таблицы video_links (embed-блоки)
                        'video_links' => $gallery->videoLinks->map(fn ($vl) => [
                            'url'       => $vl->url,
                            'title'     => $vl->title,
                            'embed_url' => $vl->embed_url,
                        ])->values()->toArray(),
                    ];
                @endphp
                {{-- ★ В режиме вкладки «Видео» показываем только альбомы с видео-ссылками --}}
                <a href="{{ $gallery->publicUrl() }}"
                     class="block cursor-pointer group relative"
                     data-album-id="{{ $gallery->id }}"
                     data-album-slug="{{ $gallery->getRouteKey() }}"
                     data-album-year="{{ $year }}"
                     x-show="(listTab === 'photo' || {{ $gallery->videoLinks->isNotEmpty() ? 'true' : 'false' }}) && (selectedRegatta === '' || selectedRegatta == '{{ $gallery->regatta?->id }}')"
                     @click="openAlbumFromLink($event, {{ Js::from($albumData) }})">

                    {{-- Обложка через <picture> (avif/webp + оригинал-фолбэк); при отсутствии обложки — статичный плейсхолдер --}}
                    @if($coverMedia = $gallery->coverMedia())
                        <x-responsive-picture :media="$coverMedia"
                            alt="{{ $gallery->name }}"
                            img-class="absolute w-full h-full object-cover z-10 transition-transform duration-500 group-hover:scale-105" />
                    @else
                        <img src="{{ asset('images/news/news_1.webp') }}"
                             alt="{{ $gallery->name }}"
                             class="absolute w-full h-full object-cover z-10 transition-transform duration-500 group-hover:scale-105">
                    @endif
                    <div class="bg-[#2E325C] opacity-30 absolute z-15 w-full h-full transition-transform duration-500 group-hover:scale-105"></div>
                    <div class="info relative z-20 p-6 pt-56 text-white">
                        <h4 class="title a-font text-2xl mb-1">{{ $gallery->regatta?->name ?? $gallery->name ?? $gallery->date?->isoFormat('D MMMM') }}</h4>
                        @if($gallery->regatta)
                            <p class="text-lg mb-3 opacity-90">{{ $gallery->name }}</p>
                        @endif
                        <p class="mb-3 flex gap-3">{!! file_get_contents(public_path('images/icons/calendar.svg')) !!} {{ $gallery->regatta?->dateRange() ?? $gallery->date?->isoFormat('D MMMM') }}</p>
                        <p class="flex gap-3">{!! file_get_contents(public_path('images/icons/waves.svg')) !!} {{ $gallery->water_area }}</p>
                    </div>
                </a>
                @endforeach
            </div>
        </div>
    </section>
    @endforeach

    <div x-show="gallery_modal_open"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 team-modal">
        <!-- Модальное окно для подробной информации о галерее -->
        <div @click.away="closeAlbum()"  class="relative p-6 max-w-[1200px] w-full max-h-[80vh] overflow-y-auto bg-white gap-6"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
        >
            <div class="flex justify-between mb-4">
                <h3 class="text-3xl a-font" x-text="activeGallery?.name ?? ''"></h3>
                <div class="flex items-center gap-3">
                    {{-- Кнопка «Поделиться»: нативный share на мобильных, копирование ссылки на десктопе --}}
                    <button @click="shareAlbum()"
                            class="inline-flex items-center gap-2 text-[#2D92CE] hover:text-[#247fb3] transition-colors"
                            :title="copied ? 'Ссылка скопирована' : 'Поделиться альбомом'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                        <span class="text-sm" x-text="copied ? 'Ссылка скопирована' : 'Поделиться'"></span>
                    </button>
                    <button @click="closeAlbum()" class="text-2xl font-bold">&times;</button>
                </div>
            </div>
            <p class="text-lg mb-6" x-text="(activeGallery?.date_short ?? '') + ' · ' + (activeGallery?.water_area ?? '')"></p>
            <div class="flex gap-4 font-medium text-lg mb-6">
                <button @click="activeTab = 'photo'" :class="activeTab === 'photo' ? 'bg-[#2D92CE] text-white' : 'bg-[#F8F8F8] text-[#2E325C]'" class="p-4 text-center">Фотографии</button>
                <button @click="activeTab = 'video'" :class="activeTab === 'video' ? 'bg-[#2D92CE] text-white' : 'bg-[#F8F8F8] text-[#2E325C]'" class="p-4 text-center">Видео</button>
            </div>
            {{-- ★ ИЗМЕНЕНО: таб «Видео» теперь использует video_links из БД с embed-блоками.
                 x-if, а не x-show: при уходе с вкладки или закрытии модалки iframe
                 должен исчезнуть из DOM, иначе видео продолжает проигрываться. --}}
            <template x-if="gallery_modal_open && activeTab === 'video'">
                <div class="grid grid-cols-1 md:grid-cols-1 gap-6">
                    <template x-for="item in activeGallery?.video_links ?? []">
                        <div class="bg-[#F8F8F8] overflow-hidden">
                            <div class="relative pt-[56.25%]">
                                <iframe
                                    class="absolute inset-0 w-full h-full"
                                    :src="item.embed_url"
                                    :title="item.title ?? ''"
                                    frameborder="0"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen
                                ></iframe>
                            </div>
                            <div class="p-3" x-show="item.title">
                                <p class="text-sm font-medium text-[#2E325C]" x-text="item.title"></p>
                            </div>
                        </div>
                    </template>
                    {{-- Если видео-ссылок нет, показываем сообщение --}}
                    <div x-show="(activeGallery?.video_links ?? []).length === 0" class="col-span-full text-center text-gray-500 py-8">
                        Видео пока нет
                    </div>
                </div>
            </template>
            {{-- Таб «Фотографии» — images уже возвращает готовые URL через аксессор --}}
            <div x-show="activeTab === 'photo'">
                {{-- Кнопка скачивания всех фото галереи одним ZIP-архивом --}}
                <div class="flex justify-end mb-4" x-show="(activeGallery?.images ?? []).length > 0">
                    <a :href="'{{ url('/gallery') }}/' + (activeGallery?.slug ?? '') + '/download'"
                       class="inline-flex items-center gap-2 bg-[#2D92CE] hover:bg-[#247fb3] text-white px-4 py-2 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                        Скачать все фото
                    </a>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <template x-for="(item, idx) in activeGallery?.images_responsive ?? []" :key="idx">
                        <div class="card bg-[#F8F8F8]"  @click="lightbox_open = true; gallery_modal_open = false; activeImage = item.src; lbImages = activeGallery?.images ?? []">
                            <picture>
                                <template x-if="item.avif"><source :srcset="item.avif" type="image/avif"></template>
                                <template x-if="item.webp"><source :srcset="item.webp" type="image/webp"></template>
                                <img class="h-full object-cover" :src="item.src" alt="">
                            </picture>
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