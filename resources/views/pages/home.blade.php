<x-public-layout title="Регаты и парусный спорт - CarterPro" description="Календарь гонок, рейтинги, правила и новости парусного спорта. Официальный сайт CarterPro: регистрация на гонки!">

<x-capture-window></x-capture-window>

{{-- ===== ШАПКА С ПОДЗАГОЛОВКОМ ===== --}}
<div class="">
    <div class="container mx-auto py-3 flex flex-col md:flex-row items-start md:items-center gap-4 justify-center text-[#2E325C] ">

        <h3 class="w-full md:w-auto text-xl block lg:text-4xl uppercase tracking-widest font-medium a-font text-center md:text-left">Ассоциация класса</h3>

        <div class="border-l-2 border-[#2D92CE] md:border-[#2E325C] pl-3 md:pl-6">
            <p class="text-sm md:text-xl leading-relaxed max-w-2xl">
                Календарь соревнований, заявки на участие, результаты
                и новости сообщества владельцев и экипажей Carter 30
            </p>
        </div>
    </div>
    <div class="container mx-auto">
        <h1 class="md:text-9xl text-5xl text-center uppercase mt-0.5 mb-4 md:mb-0 a-font">
            РЕГАТЫ Carter Pro
        </h1>
    </div>
</div>



<livewire:home-closest-regatta />

{{-- Мастер участия: подбирает регату, лодку или места и доводит до заявки.
     Кнопка «Заявка →» в блоке выше ведёт сразу в ближайшую регату, а здесь
     человек выбирает, как именно он хочет участвовать. --}}
<div class="container mx-auto pt-8 md:pt-12 text-center" x-data>
    <button type="button" @click="$dispatch('open-participation-wizard')"
            class="inline-block bg-brand-blue text-white text-xl md:text-3xl font-semibold px-10 md:px-16 py-3 md:py-4 hover:opacity-90 transition-opacity cursor-pointer uppercase a-font">
        Хочу участвовать
    </button>
    <p class="text-brand-gray-light text-sm md:text-base mt-3">
        Подберём регату, лодку или место в экипаже
    </p>
</div>

<livewire:home-regatta-timer />

{{-- ===== КАЛЕНДАРЬ РЕГАТ ===== --}}
{{-- @livewire('regatta.calendar') --}}
  <style>
    /*
      Minimal custom CSS — only what Tailwind cannot express:
      1. Responsive calc()-based flex-basis for slider cards
      2. Slide transition cubic-bezier
      3. Pagination dot active-width animation
    */
    .month-card { flex: 0 0 calc((100% - 4 * 1rem) / 5); }
    @media (max-width: 1023px) { .month-card { flex: 0 0 calc((100% - 2 * 1rem) / 3); } }
    @media (max-width: 639px)  { .month-card { flex: 0 0 calc((100% - 1 * 1rem) / 2); } }
 
    .slides   { transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
    .page-dot { transition: width 0.3s ease, background-color 0.3s ease; }
  </style>

<div class="mx-auto container">
<livewire:regattas-calendar :show-selector="false" />

</div>

{{-- ===== БЛИЖАЙШИЕ ДНИ РОЖДЕНИЯ ===== --}}
@if($birthdays->isNotEmpty())
<section class="py-12 bg-white">
    <div class="container mx-auto">
        <h2 class="section-title a-font mb-6">Ближайшие дни рождения</h2>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($birthdays as $person)
            <div class="flex items-center justify-between gap-4 bg-[#F8F8F8] p-4 shadow-xs hover:shadow-md transition-shadow">
                <div>
                    <div class="font-semibold text-lg">
                        <button type="button" class="text-[#2E325C] hover:text-[#2D92CE] hover:underline cursor-pointer text-left" @click="Livewire.dispatch('open-user-card', { userId: '{{ $person->id }}' })">{{ $person->name }}</button>
                    </div>
                    <div class="text-brand-gray text-sm">
                        {{ $person->nextBirthday?->locale('ru')->translatedFormat('d F') ?? '—' }}
                        @if($person->birth_date && $person->nextBirthday)
                            · {{ $person->nextBirthday->year - $person->birth_date->year }} лет
                        @endif
                    </div>
                </div>
                <div class="shrink-0">
                    @if($person->daysUntilBirthday === 0)
                        <span class="inline-block bg-[#2D92CE] text-white text-sm font-semibold px-3 py-1 rounded-full">Сегодня!</span>
                    @else
                        <span class="inline-block text-[#2E325C] text-sm font-medium">через {{ $person->daysUntilBirthday }} дн.</span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ===== РЕЗУЛЬТАТЫ РЕГАТ ===== --}}
<livewire:regatta-results mode="home" />



{{-- ===== НОВОСТИ ===== --}}
{{-- @livewire('news.list') --}}
<section class="py-12 bg-brand-light">
    <div class="container mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h2 class="section-title a-font">Новости ассоциации</h2>
            <a href="{{ route('news') }}"  class="text-[#2E325C] text-lg font-semibold hover:underline hidden md:block">Все новости →</a>
        </div>

        <div class="grid md:grid-cols-3 gap-6">
            @forelse($latestNews as $newsItem)
            @php
                {{-- Переносы строк (<br>, </p> и т.п.) сохраняем как \n перед strip_tags,
                     иначе абзацы склеятся в одно слово --}}
                $newsExcerpt = Str::of($newsItem->content)
                    ->replaceMatches('/<br\s*\/?>/i', "\n")
                    ->replaceMatches('#</(p|div|li|h[1-6])>#i', "\n")
                    ->stripTags()
                    ->replaceMatches('/[ \t]+/', ' ')
                    ->replaceMatches('/\n{3,}/', "\n\n")
                    ->trim()
                    ->limit(85, '…');
            @endphp
            <article class="overflow-hidden shadow-xs hover:shadow-md transition-shadow group flex md:flex-col">
                <div class="overflow-hidden md:h-52 shrink-0">
                    <img src="{{ $newsItem->cover_image_url ? Storage::url($newsItem->cover_image_url) : asset('images/news/news_1.webp') }}"
                         alt="{{ $newsItem->title }}" class="w-full max-w-[150px] md:max-w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                </div>
                <div class="md:p-4 p-2 bg-[#F8F8F8]">
                    <h3 class="font-semibold text-[#2E325C] text-sm md:text-lg mb-2 min-h-[2.5em] leading-snug line-clamp-2">
                        {{ $newsItem->title }}
                    </h3>
                    <p class="font-medium text-brand-gray mb-3 text-xs md:text-base whitespace-pre-line">{{ $newsExcerpt }}</p>
                    <div class="mb-2 text-brand-gray-light text-xs md:text-base">{{ $newsItem->published_at->translatedFormat('j F Y') }}</div>
                    <div class="">
                        <a href="{{ route('news-details', $newsItem) }}" class="text-[#2E325C] font-semibold hover:underline text-xs md:text-lg">Читать далее →</a>
                    </div>
                </div>
            </article>
            @empty
            <p class="col-span-3 text-center text-brand-gray py-6">Новостей пока нет</p>
            @endforelse
        </div>
        <a href="{{ route('news') }}"  class="text-[#2E325C] text-center block mt-8 text-sm font-semibold hover:underline md:hidden">Все новости →</a>
    </div>
</section>

{{-- ===== НОВОСТИ ПАРУСНОГО МИРА ===== --}}
@if ($worldNews->isNotEmpty())
<section class="py-12 bg-white">
    <div class="container mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h2 class="section-title a-font">Новости парусного мира</h2>
            <a href="{{ route('world-news') }}" class="text-[#2E325C] text-lg font-semibold hover:underline hidden md:block">Все новости →</a>
        </div>

        <div class="grid md:grid-cols-3 gap-6">
            @foreach ($worldNews as $newsItem)
                <x-world-news-card :news="$newsItem" />
            @endforeach
        </div>

        <a href="{{ route('world-news') }}" class="text-[#2E325C] text-center block mt-8 text-sm font-semibold hover:underline md:hidden">Все новости →</a>
    </div>
</section>
@endif

{{-- ===== ПРЕССА О НАС ===== --}}
@if($pressMentions->isNotEmpty())
<section class="py-12 bg-white">
    <div class="container mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h2 class="section-title a-font">Пресса о нас</h2>
            <a href="{{ route('press') }}" class="text-[#2E325C] text-lg font-semibold hover:underline hidden md:block">Все публикации →</a>
        </div>

        <div class="grid md:grid-cols-3 gap-6">
            @foreach($pressMentions as $mention)
                <x-press-card :mention="$mention" />
            @endforeach
        </div>

        <a href="{{ route('press') }}" class="text-[#2E325C] text-center block mt-8 text-sm font-semibold hover:underline md:hidden">Все публикации →</a>
    </div>
</section>
@endif



{{-- ===== ПРОМО-БЛОКИ: КОМАНДЫ И ЯХТЫ ===== --}}
<section class="md:py-8 py-4">
    <div class="container mx-auto">
        <div class="grid md:grid-cols-2 gap-5">
            {{-- Команды --}}
            <a href="{{ route('teams') }}" class="relative overflow-hidden min-h-[220px] group cursor-pointer">
                <img src="{{ asset('images/main/main-l.webp') }}"
                     alt="Команды" class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                <div class="absolute inset-0 bg-linear-to-r from-brand-navy/90 to-brand-navy/40"></div>
                <div class="relative p-8 h-full flex flex-col justify-center text-white max-w-[500px]">
                    <h3 class="font-display text-3xl md:text-5xl font-medium mb-2 a-font">Команды ассоциации</h3>
                    <p class="md:text-lg text-sm max-w-xs leading-[1.3] mb-5">
                        Зарегистрированные экипажи Carter 30, состав команд и участие в регатах сезона.
                    </p>
                    <div  class="md:text-lg text-sm font-semibold justify-start text-white">
                        Смотреть команды  →
                    </div>
                </div>
            </a>

            {{-- Яхты --}}
            <a href="{{ route('yachts') }}" class="relative overflow-hidden md:min-h-[440px] group cursor-pointer">
                <img src="{{ asset('images/main/main-r.webp') }}"
                     alt="Яхты Carter Pro" class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                <div class="absolute inset-0 bg-linear-to-r from-[#F8F8F8]/100 to-[#F8F8F8]/40"></div>
                <div class="relative p-8 h-full flex flex-col justify-center text-[#2E325C] max-w-[500px]">
                    <h3 class="font-display text-3xl md:text-5xl font-medium mb-2 a-font">Яхты CarterPro</h3>
                    <p class="md:text-lg text-sm leading-[1.3] mb-5">
                        Список яхт ассоциации, технические параметры, владельцы и история участия в регатах.
                    </p>
                    <div class="md:text-lg text-sm  font-semibold justify-start text-[#2E325C]">
                        Смотреть яхты  →
                    </div>
                </div>
            </a>
        </div>
    </div>
</section>

{{-- ===== ГАЛЕРЕЯ ===== --}}
@if($galleryPhotos->isNotEmpty())
<section class="py-12 bg-brand-light"
    x-data="gallerySlider()"
    x-init="init()"
    @resize.window.debounce.100ms="calcDimensions()">

    <div class="container mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h2 class="section-title a-font">Галерея</h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('gallery') }}" class="text-lg font-semibold hover:underline hidden md:block">Все галерея →</a>
                <div class="hidden md:flex items-center gap-2">
                    <button @click="prev()"
                        :disabled="current === 0"
                        :class="current === 0 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-[#0074CC] cursor-pointer'"
                        class="bg-[#2D92CE] rounded-full w-8 h-8 flex items-center justify-center shadow-sm text-white transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button @click="next()"
                        :disabled="current >= maxIndex"
                        :class="current >= maxIndex ? 'opacity-40 cursor-not-allowed' : 'hover:bg-[#0074CC] cursor-pointer'"
                        class="bg-[#2D92CE] rounded-full w-8 h-8 flex items-center justify-center shadow-sm text-white transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- Трек слайдера --}}
        <div class="overflow-hidden"
            x-ref="track"
            @touchstart.passive="touchStart($event)"
            @touchmove.passive="touchMove($event)"
            @touchend="touchEnd($event)"
        >
            <div
                class="flex"
                :style="`gap: ${gap}px; transform: translateX(-${offset}px); transition: ${dragging ? 'none' : 'transform 0.4s cubic-bezier(0.4,0,0.2,1)'}; will-change: transform;`"
            >
                <template x-for="(img, idx) in images" :key="idx">
                    <div
                        class="shrink-0 overflow-hidden cursor-pointer group"
                        :style="`width: ${cardWidth}px; height: ${cardHeight}px`"
                    >
                        <img :src="img" alt="" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                    </div>
                </template>
            </div>
        </div>

        {{-- Точки пагинации --}}
        <div class="flex justify-center gap-1.5 mt-5">
            <template x-for="(_, idx) in Array.from({ length: maxIndex + 1 })" :key="idx">
                <button
                    class="page-dot h-1.5 rounded-full border-0 cursor-pointer transition-all duration-300"
                    :class="idx === current ? 'w-5 bg-[#2D92CE]' : 'w-1.5 bg-slate-300'"
                    @click="goTo(idx)"
                    :aria-label="`Слайд ${idx + 1}`">
                </button>
            </template>
        </div>

        <a href="{{ route('gallery') }}" class="text-[#2E325C] text-center block mt-6 text-sm font-semibold hover:underline md:hidden">Все галерея →</a>
    </div>
</section>

<script>
function gallerySlider() {
    return {
        images: {!! json_encode($galleryPhotos->values()->all()) !!},

        // Брейкпоинты: сколько карточек видно
        breakpoints: [
            { maxWidth: 639,  visible: 1.2 },
            { maxWidth: 1023, visible: 2.3 },
            { maxWidth: Infinity, visible: 5 },
        ],

        visible: 5,
        gap: 16,
        cardWidth: 0,
        cardHeight: 208, // h-52 = 208px
        trackWidth: 0,
        current: 0,

        // Touch
        touchStartX: 0,
        touchDeltaX: 0,
        dragging: false,

        get maxIndex() {
            return Math.max(0, this.images.length - Math.floor(this.visible));
        },
        get offset() {
            return this.current * (this.cardWidth + this.gap);
        },

        init() {
            this.$nextTick(() => this.calcDimensions());
        },

        calcDimensions() {
            const track = this.$refs.track;
            if (!track) return;

            const w = window.innerWidth;
            const bp = this.breakpoints.find(b => w <= b.maxWidth);
            this.visible = bp ? bp.visible : 5;

            // На мобильных уменьшаем высоту карточки
            this.cardHeight = w < 640 ? 160 : 208;

            this.trackWidth = track.clientWidth;
            this.cardWidth = (this.trackWidth - this.gap * (this.visible - 1)) / this.visible;

            if (this.current > this.maxIndex) this.current = this.maxIndex;
        },

        prev() {
            if (this.current > 0) this.current--;
        },
        next() {
            if (this.current < this.maxIndex) this.current++;
        },
        goTo(idx) {
            this.current = Math.min(Math.max(0, idx), this.maxIndex);
        },

        // Touch-свайп
        touchStart(e) {
            this.touchStartX = e.touches[0].clientX;
            this.touchDeltaX = 0;
            this.dragging = true;
        },
        touchMove(e) {
            this.touchDeltaX = e.touches[0].clientX - this.touchStartX;
        },
        touchEnd() {
            this.dragging = false;
            const threshold = this.cardWidth * 0.25;
            if (this.touchDeltaX < -threshold) this.next();
            else if (this.touchDeltaX > threshold) this.prev();
            this.touchDeltaX = 0;
        },
    };
}
</script>

@endif



{{-- ===== СПОНСОРЫ ===== --}}
@if($sponsors->isNotEmpty())
<section class="py-10 bg-white" x-data="sponsorsList()">

    <div class="container mx-auto">
        <h2 class="section-title a-font mb-6">Партнёры ассоциации</h2>

        <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            <template x-for="(s, idx) in sponsors" :key="idx">
                <button
                    type="button"
                    @click="openSponsor(s)"
                    :disabled="! hasDetails(s)"
                    class="md:h-64 flex flex-col items-center gap-2 p-2 transition-shadow text-left"
                    :class="hasDetails(s) ? 'cursor-pointer' : 'cursor-default'"
                >
                    {{-- w-full: логотип занимает всю ширину карточки, object-contain сохраняет пропорции --}}
                    <img :src="s.logo" :alt="s.name || ''" class="flex-1 md:min-h-32 w-full object-contain">
                    <div class="w-full bg-[#F8F8F8] p-2 md:p-3">
                        <span
                            x-show="s.name"
                            x-text="s.name"
                            class="w-full block md:min-h-[2.5em] text-lg md:text-lg font-semibold leading-tight tracking-wide text-[#2E325C] line-clamp-2"
                        ></span>
                        <span
                            x-show="s.description"
                            x-text="excerpt(s)"
                            class="w-full block md:min-h-[2.75em] text-sm leading-snug text-brand-gray line-clamp-2 whitespace-pre-line"
                        ></span>
                    </div>
                </button>
            </template>
        </div>
    </div>

    {{-- Модальное окно с описанием партнёра --}}
    <div
        x-show="modalOpen"
        x-cloak
        x-transition.opacity
        @keydown.escape.window="closeSponsor()"
        x-trap.noscroll="modalOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
    >
        <div
            @click.outside="closeSponsor()"
            class="relative bg-white w-full max-w-lg max-h-[85vh] overflow-y-auto p-6 md:p-8 shadow-xl"
            role="dialog"
            aria-modal="true"
        >
            <button
                type="button"
                @click="closeSponsor()"
                class="absolute top-3 right-3 w-8 h-8 flex items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition-colors cursor-pointer"
                aria-label="Закрыть"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>

            <div class="flex items-center justify-center h-42 p-3 mb-5">
                <img :src="active.logo" :alt="active.name || ''" class="max-h-full max-w-full object-contain">
            </div>

            <h3 x-show="active.name" x-text="active.name" class="text-xl md:text-xl text-[#2E325C] mb-3"></h3>

            <div
                x-show="active.description"
                x-html="active.description"
                class="text prose max-w-none text-sm md:text-base leading-relaxed text-brand-gray"
            ></div>

            <a
                x-show="active.url"
                :href="active.url"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-2 mt-6 bg-[#2D92CE] hover:bg-[#0074CC] text-white text-sm px-5 py-2.5 transition-colors"
            >
                Перейти на сайт
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </a>
        </div>
    </div>
</section>

<script>
function sponsorsList() {
    return {
        sponsors: {!! json_encode($sponsors->values()->all()) !!},

        // Модалка с описанием партнёра
        modalOpen: false,
        active: {},

        // Модалка: открываем, только если есть что показать
        hasDetails(s) {
            return !! (s && (s.description || s.url));
        },
        // Краткий текст описания для карточки: description хранит HTML, здесь нужен только текст,
        // обрезанный до 85 символов; переносы строк (<br>, </p> и т.п.) сохраняем как \n,
        // иначе textContent склеит абзацы в одно слово
        excerpt(s) {
            if (! s || ! s.description) return '';
            const html = s.description
                .replace(/<br\s*\/?>/gi, '\n')
                .replace(/<\/(p|div|li|h[1-6])>/gi, '\n');
            const div = document.createElement('div');
            div.innerHTML = html;
            const text = (div.textContent || '')
                .replace(/[ \t]+/g, ' ')
                .replace(/\n{3,}/g, '\n\n')
                .trim();
            return text.length > 85 ? text.slice(0, 85).trim() + '…' : text;
        },
        openSponsor(s) {
            if (! this.hasDetails(s)) return;
            this.active = s;
            this.modalOpen = true;
        },
        closeSponsor() {
            this.modalOpen = false;
        },
    };
}
</script>
@endif

{{-- ===== FAQ ===== --}}
@if($faq->isNotEmpty())
<section class="py-12 bg-white">
    <div class="container mx-auto">
        <h2 class="section-title a-font mb-8">Часто задаваемые вопросы</h2>

        <div class="px-3">
            <x-faq-accordion :items="$faq" />
        </div>
    </div>
</section>
@endif

{{-- ===== Задать вопрос администрации ===== --}}
<section class="py-12 bg-brand-light">
    <div class="container mx-auto">
        <div class="bg-white px-6 py-10 md:px-12 md:py-12 flex flex-col md:flex-row items-center justify-between gap-6 text-center md:text-left">
            <div>
                <h2 class="section-title a-font mb-2">Не нашли ответ на свой вопрос?</h2>
                <p class="text-brand-gray text-sm md:text-base">Задайте вопрос нам — мы ответим вам в ближайшее время.</p>
            </div>
            <button @click="isQuestionModalOpen = true"
                    class="shrink-0 bg-[#2D92CE] text-white px-8 py-4 font-semibold hover:bg-[#2D92CE]/90 transition-colors">
                Задать вопрос
            </button>
        </div>
    </div>
</section>

</x-public-layout>
