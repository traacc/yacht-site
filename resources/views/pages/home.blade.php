<x-public-layout>

<x-capture-window></x-capture-window>

{{-- ===== ШАПКА С ПОДЗАГОЛОВКОМ ===== --}}
<div class="">
    <div class="max-w-(--breakpoint-2xl) mx-auto px-4 sm:px-6 lg:px-8 py-3 flex flex-col md:flex-row items-start md:items-center gap-4 justify-center text-[#2E325C] ">

        <h3 class="w-full md:w-auto text-xl block lg:text-4xl uppercase tracking-widest font-medium a-font text-center md:text-left">Ассоциация класса</h3>

        <div class="border-l-2 border-[#2D92CE] md:border-[#2E325C] pl-3 md:pl-6">
            <p class="text-sm md:text-xl leading-relaxed max-w-2xl">
                Календарь соревнований, заявки на участие, результаты
                и новости сообщества владельцев и экипажей Carter 30
            </p>
        </div>
    </div>
    <div class="max-w-(--breakpoint-2xl) mx-auto">
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

<div class="mx-auto max-w-(--breakpoint-2xl)">
<livewire:regattas-calendar :show-selector="false" />

</div>


{{-- ===== РЕЗУЛЬТАТЫ РЕГАТ ===== --}}
{{-- @livewire('regatta.results') --}}
<section class="py-12">
    <div class="max-w-(--breakpoint-2xl) mx-auto px-4 sm:px-6 lg:px-8 py-4 bg-[#F8F8F8]">
        <div class="flex items-center justify-between mb-6">
            <h2 class="section-title a-font">Результаты регат</h2>
            <a href="{{ route('ratings') }}" class="text-[#2E325C] text-lg font-semibold hover:underline hidden md:block">Все результаты →</a>
        </div>

        {{-- Таблица этапа --}}


        {{-- Топ-3 рейтинги --}}
        

        
        <div class="grid md:grid-cols-2 gap-4">

            <div class="bg-brand-light rounded-xl md:p-4 md:pr-0">
                <h3 class="font-display  text-[#2E325C] text-3xl mb-4 a-font">ТОП-3 команд сезона</h3>
                <div class="overflow-x-auto md:p-6 bg-white">
                    <table class="w-full text-sm responsive-table md:max-h-[220px]">
                        <thead>
                            <tr class="text-2xl text-[#2E325C] border-b border-[#EAEAEA] sticky top-0">
                                <th class="pb-2 text-center font-medium a-font w-16">Место</th>
                                <th class="pb-2 text-center font-medium a-font">Команда</th>
                                <th class="pb-2 text-center font-medium a-font">Очки</th>
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
                <div class="overflow-x-auto md:p-6 bg-white">
                    <table class="w-full text-sm responsive-table md:max-h-[220px]">
                        <thead>
                            <tr class="text-2xl text-[#2E325C] border-b border-[#EAEAEA] sticky top-0">
                                <th class="pb-2 text-center font-medium a-font w-16">Место</th>
                                <th class="pb-2 text-center font-medium a-font">Участник</th>
                                <th class="pb-2 text-center font-medium a-font">Очки</th>
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
    <div class="max-w-(--breakpoint-2xl) mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
            <h2 class="section-title a-font">Новости ассоциации</h2>
            <a href="{{ route('news') }}" wire:navigate class="text-[#2E325C] text-lg font-semibold hover:underline hidden md:block">Все новости →</a>
        </div>

        <div class="grid md:grid-cols-3 gap-6">
            <article class="overflow-hidden shadow-xs hover:shadow-md transition-shadow group flex md:flex-col">
                <div class="overflow-hidden md:h-52">
                    <img src="{{ asset('images/news/news_1.png') }}"
                         alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                </div>
                <div class="p-4 bg-[#F8F8F8]">
                    <h3 class="font-semibold text-[#2E325C] text-sm md:text-lg mb-2">
                        Открыта регистрация на Кубок Carter 30 Pro
                    </h3>
                    <p class="font-medium text-brand-gray mb-3 text-xs md:text-base">Организаторы напоминают, что экипажи могут подать заявку с...</p>
                    <div class="mb-2 text-brand-gray-light text-xs md:text-base">16 апреля 2026</div>
                    <div class="">
                        <a href="#" class="text-[#2E325C] font-semibold hover:underline text-xs md:text-lg">Читать далее →</a>
                    </div>
                </div>
            </article>

            <article class="overflow-hidden shadow-xs hover:shadow-md transition-shadow group flex md:flex-col">
                <div class="overflow-hidden md:h-52">
                    <img src="{{ asset('images/news/news_2.png') }}"
                         alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                </div>
                <div class="p-4 bg-[#F8F8F8]">
                    <h3 class="font-semibold text-[#2E325C] text-sm md:text-lg mb-2">
                        Опубликован календарь майских регат
                    </h3>
                    <p class="font-medium text-brand-gray mb-3 text-xs md:text-base">В расписание сезона добавлены даты старта, те самые, м...</p>
                    <div class="mb-2 text-brand-gray-light text-xs md:text-base">9 апреля 2026</div>
                    <div class="">
                        <a href="#" class="text-[#2E325C] font-semibold hover:underline text-xs md:text-lg">Читать далее →</a>
                    </div>
                </div>
            </article>

            <article class="overflow-hidden shadow-xs hover:shadow-md transition-shadow group flex md:flex-col">
                <div class="overflow-hidden md:h-52">
                    <img src="{{ asset('images/news/news_3.png') }}"
                         alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                </div>
                <div class="p-4 bg-[#F8F8F8]">
                    <h3 class="font-semibold text-[#2E325C] text-sm md:text-lg mb-2">
                        Обновлены правила подачи заявок
                    </h3>
                    <p class="font-medium text-brand-gray mb-3 text-xs md:text-base">На сайте опубликованы обновлённые условия регистрации на...</p>
                    <div class="mb-2 text-brand-gray-light text-xs md:text-base">2 апреля 2026</div>
                    <div class="">
                        <a href="#" class="text-[#2E325C] font-semibold hover:underline text-xs md:text-lg">Читать далее →</a>
                    </div>
                </div>
            </article>
        </div>
        <a href="{{ route('news') }}" wire:navigate class="text-[#2E325C] text-center block mt-8 text-sm font-semibold hover:underline md:hidden">Все новости →</a>
    </div>
</section>

{{-- ===== ПРОМО-БЛОКИ: КОМАНДЫ И ЯХТЫ ===== --}}
<section class="py-8">
    <div class="max-w-(--breakpoint-2xl) mx-auto px-4 sm:px-6 lg:px-8">
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
                    <a href="{{ route('teams') }}" wire:navigate class="md:text-lg text-sm font-semibold justify-start text-white">
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
                    <a href="{{ route('yachts') }}" wire:navigate class="md:text-lg text-sm  font-semibold justify-start text-[#2E325C]">
                        Смотреть яхты  →
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ===== ГАЛЕРЕЯ ===== --}}
{{-- @livewire('gallery.preview') --}}
<section x-data="{
    current: 0,
    images: [
        '{{ asset('images/news/news_1.png') }}',
        '{{ asset('images/news/news_2.png') }}',
        '{{ asset('images/news/news_3.png') }}',
        '{{ asset('images/news/news_1.png') }}',
        '{{ asset('images/news/news_2.png') }}',
    ]
}" class="py-12 bg-brand-light">
<style>
    .month-card { flex: 0 0 calc((100% - 4 * 1rem) / 5); }
    @media (max-width: 1023px) { .month-card { flex: 0 0 calc((100% - 2 * 1rem) / 3); } }
    @media (max-width: 639px)  { .month-card { flex: 0 0 85%; } }

    .slides   { transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
    .page-dot { transition: width 0.3s ease, background-color 0.3s ease; }
</style>

    <div class="max-w-(--breakpoint-2xl) mx-auto px-4 sm:px-6 lg:px-8" x-data="regattaCalendar()" data-current-month="{{ now()->format('n') - 1 }}">
        <div class="flex items-center justify-between mb-6">
            <h2 class="section-title a-font">Галерея</h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('gallery') }}" wire:navigate class="text-lg font-semibold hover:underline">Все галерея →</a>
                <div class="hidden md:flex items-center gap-2">
                    <button @click="prev()" :disabled="offset === 0"
                        class="bg-[#2D92CE] rounded-full w-8 h-8 flex items-center justify-center shadow-sm hover:shadow-md text-white hover:bg-[#0074CC] transition-all cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button @click="next()" :disabled="offset >= maxOffset"
                        class="bg-[#2D92CE] rounded-full w-8 h-8 flex items-center justify-center shadow-sm hover:shadow-md text-white hover:bg-[#0074CC] transition-all cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>

            </div>
        </div>
        <div class="grid grid-cols-3 gap-4 overflow-hidden">
            <template x-for="(img, idx) in images.slice(current, current + 3)" :key="idx">
                <div class="overflow-hidden h-52 cursor-pointer group">
                    <img :src="img" alt="" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                </div>
            </template>
        </div>
        <div class="flex justify-center gap-1.5 mt-5">
            <template x-for="(_, idx) in Array.from({ length: maxOffset + 1 })" :key="idx">
                <button
                    class="page-dot h-1.5 rounded-full border-0 cursor-pointer"
                    :class="idx === offset ? 'w-5 bg-[#2D92CE]' : 'w-1.5 bg-slate-300'"
                    @click="goTo(idx)"
                    :aria-label="`Месяц ${idx + 1}`">
                </button>
            </template>
        </div>
    </div>
</section>
{{-- Alpine-логика слайдера --}}
<script>
function regattaCalendar() {
    return {
        visible: 5,
        gap: 16,
        offset: 0,

        get maxOffset() {
            return Math.max(0, 12 - this.visible);
        },

        prev() { if (this.offset > 0) this.offset--; },
        next() { if (this.offset < this.maxOffset) this.offset++; },
        goTo(idx) {
            this.offset = Math.min(Math.max(idx, 0), this.maxOffset);
        },

        init() {
            this.updateVisible();
            const currentMonthIdx = parseInt(this.$el.dataset.currentMonth) || 0;
            this.offset = Math.min(currentMonthIdx, this.maxOffset);
            window.addEventListener('resize', () => this.updateVisible());
        },
        updateVisible() {
            const w = window.innerWidth;
            this.visible = w < 640 ? 1.25 : w < 1024 ? 3 : 5;
            if (this.offset > this.maxOffset) this.offset = Math.max(0, this.maxOffset);
        },
    }
}
</script>

{{-- ===== СПОНСОРЫ ===== --}}
<section class="py-10 bg-white">
    <div class="max-w-(--breakpoint-2xl) mx-auto px-4 sm:px-6 lg:px-8">
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