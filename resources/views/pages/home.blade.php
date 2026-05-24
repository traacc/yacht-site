<x-public-layout>

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
        <h1 class="md:text-9xl text-5xl text-center tracking-tighter uppercase mt-0.5 mb-4 md:mb-0 a-font">
            Регаты CarterPro
        </h1>
    </div>
</div>



<livewire:home-closest-regatta />
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


{{-- ===== РЕЗУЛЬТАТЫ РЕГАТ ===== --}}
{{-- @livewire('regatta.results') --}}
<section class="py-12" x-data="{team_modal_open: false, activeTeam: null}">
    <div class="container mx-auto sm:px-6 py-4 bg-[#F8F8F8]">
        @if($regatta)
        <div class="flex items-center justify-between mb-6">
            <h2 class="section-title a-font">Результаты регат</h2>
            <a href="{{ route('ratings') }}" class="text-[#2E325C] text-lg font-semibold hover:underline hidden md:block">Все результаты →</a>
        </div>

        {{-- Таблица этапа --}}
        
        <section class="rating_1 mb-12">
            <div class=" bg-[#F8F8F8]">
                <div class="flex justify-between mb-6 flex-col md:flex-row">
                    <h3 class="a-font text-3xl">{{ $regatta->name }}</h3>
                </div>
                <div class="flex gap-6 items-center mb-6">
                    <div class="date flex gap-2 text-sm md:text-lg font-medium">
                        {!! file_get_contents(public_path('images/icons/calendar.svg')) !!}
                        {{ $regatta->dateRange() }}
                    </div>
                    @if($regatta->isFinished())
                        <div class="bg-[#15794933] px-3 py-1 text-[#157949] inline-block font-semibold max-w-[140px] w-full uppercase md:text-base text-sm">
                            Завершено
                        </div>
                    @else
                        <div class="bg-[#C2A36B26] px-3 py-1 text-[#C2A36B] inline-block font-semibold max-w-[350px] w-full uppercase md:text-base text-sm">
                            Предварительные результаты
                        </div>
                    @endif
                </div>
                @if(!$regatta->isFinished())
                <p class="mb-6">Таблица обновляется по мере обработки результатов. Финальные очки будут опубликованы после утверждения итогов соревнования.</p>
                @endif
                <div class="overflow-x-auto relative p-2 md:p-6 md:pt-0 bg-white responsive-table md:max-h-[220px]">
                    <table class="w-full">
                        <thead class="sticky top-0 bg-white">
                            <tr class="text-2xl text-[#2E325C] border-b border-[#EAEAEA] ">
                                <th class="pb-2 text-center font-medium pt-6 w-16 a-font">Место</th>
                                <th class="pb-2 text-center font-medium pt-6 a-font">Команда</th>
                                <th class="pb-2 text-center font-medium pt-6 a-font">Капитан</th>
                                <th class="pb-2 text-center font-medium pt-6 a-font">Яхта</th>
                                <th class="pb-2 text-center font-medium pt-6 a-font">Парус №</th>
                                <th class="pb-2 text-center font-medium pt-6 a-font">Участники</th>
                                <th class="pb-2 text-center font-medium pt-6 a-font">Очки</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-center font-medium">
                            @forelse($resultItems as $result)
                                <tr class="hover:bg-white transition-colors border-b border-[#EAEAEA]">
                                    <td data-label="Место" class="py-3">
                                        <div @class([
                                            'flex items-center justify-center gap-3',
                                            'text-[#C2A36B]' => $result->final_position == 1,
                                            'text-[#9FA6AD]' => $result->final_position == 2,
                                            'text-[#B56A3A]' => $result->final_position == 3,
                                            'text-transparent' => !in_array($result->final_position, [1, 2, 3]),
                                        ])>
                                            {!! file_get_contents(public_path('images/icons/cup.svg')) !!}
                                            <span class="text-brand-gray">{{ $result->final_position }}</span>
                                        </div>
                                    </td>
                                    <td data-label="Команда" class="py-3">{{ $result->team?->name ?? '—' }}</td>
                                    <td data-label="Капитан" class="py-3">{{ $result->team?->organizer?->full_name ?? '—' }}</td>
                                    <td data-label="Яхта" class="py-3">{{ $result->yacht?->name ?? '—' }}</td>
                                    <td data-label="Парус №" class="py-3">{{ $result->yacht?->vfps_number ?? '—' }}</td>
                                    <td data-label="Участники" class="py-3">
                                        <a @click.prevent="team_modal_open = true; activeTeam = {{ $result->team }} " href="#" class="text-[#2D92CE] font-medium underline hover:no-underline">
                                            {{ $result->team?->activeMembers?->count() ?? 0 }} участников
                                        </a>
                                    </td>
                                    <td data-label="Очки" class="py-3">{{ $result->total_points }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-6 text-gray-400">Нет результатов</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <a class="text-[#2E325C] text-sm font-semibold gap-2 items-center flex md:hidden justify-center mt-4">
                    <img src="{{ asset('images/icons/download.svg') }}" alt="">
                    <span>Скачать результаты PDF</span>
                </a>
            </div>
        </section>
        @endif


        {{-- Топ-3 рейтинги --}}
        

        
        <div class="grid md:grid-cols-2 gap-4">

            <div class="bg-brand-light rounded-xl md:p-4 md:pr-0">
                <h3 class="font-display  text-[#2E325C] text-3xl mb-4 a-font">ТОП-3 команд сезона</h3>
                <div class="overflow-auto md:p-6 md:pt-0 bg-white md:max-h-[220px]">
                    <table class="w-full text-sm md:text-base responsive-table">
                        <thead class="sticky bg-white top-0 pt-6">
                            <tr class="text-2xl text-[#2E325C] border-b border-[#EAEAEA] ">
                                <th class="pb-2 text-center font-medium a-font pt-6 w-16">Место</th>
                                <th class="pb-2 text-center font-medium a-font pt-6">Команда</th>
                                <th class="pb-2 text-center font-medium a-font pt-6">Очки</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-center font-medium">
                            <template x-data="{ teams: [['Барс', 7.0], ['Барс', 7.0], ['Барс', 7.0]] }" x-for="(team, i) in teams" :key="i">
                                <tr>
                                    <td class="py-2" data-label="Место">
                                        <div class="flex items-center md:justify-center gap-3">
                                            <span :class="i===0?'text-[#C2A36B]':i===1?'text-[#9FA6AD]':'text-[#B56A3A]'" class="font-bold text-sm">{!! file_get_contents(public_path('images/icons/cup.svg')) !!}</span><span x-text="i+1"></span>
                                        </div>
                                        
                                    </td>
                                    <td class="py-2" data-label="Участник" x-text="team[0]"></td>
                                    <td class="py-2" data-label="Очки" x-text="team[1]"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

            </div>
            <div class="bg-brand-light rounded-xl md:p-4">
                <h3 class="font-display  text-[#2E325C]  text-3xl mb-4 a-font">ТОП-3 участников</h3>
                <div class="overflow-x-auto md:p-6 md:pt-0 bg-white md:max-h-[220px]">
                    <table class="w-full text-sm md:text-base responsive-table">
                        <thead>
                            <tr class="text-2xl text-[#2E325C] border-b border-[#EAEAEA] bg-white sticky top-0">
                                <th class="pb-2 text-center font-medium a-font pt-6 w-16">Место</th>
                                <th class="pb-2 text-center font-medium a-font pt-6">Участник</th>
                                <th class="pb-2 text-center font-medium a-font pt-6">Очки</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-center font-medium">
                            <template x-data="{ participants: [['Игорь Скалин', 7.0], ['Игорь Скалин', 7.0], ['Игорь Скалин', 7.0]] }" x-for="(p, i) in participants" :key="i">
                                <tr>
                                    <td class="py-2" data-label="Место">
                                        <div class="flex items-center md:justify-center gap-3">
                                            <span :class="i===0?'text-[#C2A36B]':i===1?'text-[#9FA6AD]':'text-[#B56A3A]'" class="font-bold text-sm">{!! file_get_contents(public_path('images/icons/cup.svg')) !!}</span><span x-text="i+1"></span>
                                        </div>
                                        
                                    </td>
                                    <td class="py-2" data-label="Участник" x-text="p[0]"></td>
                                    <td class="py-2" data-label="Очки" x-text="p[1]"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

            </div>
            <a href="{{ route('ratings') }}" class="text-[#2E325C] text-sm text-center font-semibold hover:underline md:hidden">Все результаты →</a>
        </div>
    </div>
</section>

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
            <article class="overflow-hidden shadow-xs hover:shadow-md transition-shadow group flex md:flex-col">
                <div class="overflow-hidden md:h-52 shrink-0">
                    <img src="{{ $newsItem->cover_image_url ? Storage::url($newsItem->cover_image_url) : asset('images/news/news_1.png') }}"
                         alt="{{ $newsItem->title }}" class="w-full max-w-[150px] md:max-w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                </div>
                <div class="md:p-4 p-2 bg-[#F8F8F8]">
                    <h3 class="font-semibold text-[#2E325C] text-sm md:text-lg mb-2 md:h-14">
                        {{ $newsItem->title }}
                    </h3>
                    <p class="font-medium text-brand-gray mb-3 text-xs md:text-base">{{ Str::limit(strip_tags($newsItem->content), 60) }}</p>
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

{{-- ===== ПРОМО-БЛОКИ: КОМАНДЫ И ЯХТЫ ===== --}}
<section class="md:py-8 py-4">
    <div class="container mx-auto">
        <div class="grid md:grid-cols-2 gap-5">
            {{-- Команды --}}
            <div class="relative overflow-hidden min-h-[220px] group cursor-pointer">
                <img src="{{ asset('images/main/main-l.png') }}"
                     alt="Команды" class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                <div class="absolute inset-0 bg-linear-to-r from-brand-navy/90 to-brand-navy/40"></div>
                <div class="relative p-8 h-full flex flex-col justify-center text-white max-w-[500px]">
                    <h3 class="font-display text-3xl md:text-5xl font-medium mb-2 a-font">Команды ассоциации</h3>
                    <p class="md:text-lg text-sm max-w-xs leading-[1.3] mb-5">
                        Зарегистрированные экипажи Carter 30, состав команд и участие в регатах сезона.
                    </p>
                    <a href="{{ route('teams') }}"  class="md:text-lg text-sm font-semibold justify-start text-white">
                        Смотреть команды  →
                    </a>
                </div>
            </div>

            {{-- Яхты --}}
            <div class="relative overflow-hidden md:min-h-[440px] group cursor-pointer">
                <img src="{{ asset('images/main/main-r.png') }}"
                     alt="Яхты Carter Pro" class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                <div class="absolute inset-0 bg-linear-to-r from-[#F8F8F8]/0 to-[#F8F8F8]/40"></div>
                <div class="relative p-8 h-full flex flex-col justify-center text-[#2E325C] max-w-[500px]">
                    <h3 class="font-display text-3xl md:text-5xl font-medium mb-2 a-font">Яхты CarterPro</h3>
                    <p class="md:text-lg text-sm leading-[1.3] mb-5">
                        Список яхт ассоциации, технические параметры, владельцы и история участия в регатах.
                    </p>
                    <a href="{{ route('yachts') }}"  class="md:text-lg text-sm  font-semibold justify-start text-[#2E325C]">
                        Смотреть яхты  →
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ===== ГАЛЕРЕЯ ===== --}}
{{-- @livewire('gallery.preview') --}}
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
        images: [
            '{{ asset('images/news/news_1.png') }}',
            '{{ asset('images/news/news_2.png') }}',
            '{{ asset('images/news/news_3.png') }}',
            '{{ asset('images/news/news_1.png') }}',
            '{{ asset('images/news/news_2.png') }}',
            '{{ asset('images/news/news_1.png') }}',
            '{{ asset('images/news/news_2.png') }}',
            '{{ asset('images/news/news_3.png') }}',
            '{{ asset('images/news/news_1.png') }}',
            '{{ asset('images/news/news_2.png') }}',
            '{{ asset('images/news/news_2.png') }}',
            '{{ asset('images/news/news_3.png') }}',
            '{{ asset('images/news/news_1.png') }}',
            '{{ asset('images/news/news_2.png') }}',
        ],

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


{{-- ===== СПОНСОРЫ ===== --}}
<section class="py-10 bg-white">
    <div class="container mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h2 class="section-title a-font">Наши спонсоры</h2>
            <div class="flex gap-2">
                <button class="bg-[#2D92CE] hover:bg-[#0074CC] rounded-full w-8 h-8 flex items-center justify-center text-white transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <button class="bg-[#2D92CE] hover:bg-[#0074CC] rounded-full w-8 h-8 flex items-center justify-center text-white transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>
        </div>
        <div class="grid grid-cols-4 gap-4">
            <template x-data="{sponsors: [1,2,3,4]}" x-for="s in sponsors" :key="s">
                <div class="bg-[#E2E2E2] h-20 flex items-center justify-center hover:shadow-md transition-shadow cursor-pointer">
                    <span class="text-gray-300 font-display text-lg font-bold uppercase tracking-wider"></span>
                </div>
            </template>
        </div>
    </div>
</section>

</x-public-layout>