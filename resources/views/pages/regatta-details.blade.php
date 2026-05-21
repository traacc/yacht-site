<x-public-layout>
<x-breadcrumbs_page title="Кубок CarterPro">
</x-breadcrumbs_page>
{{-- ===== КАРТОЧКА РЕГАТЫ ===== --}}
<main class="main" x-data="{ team_modal_open: false }">
    <section class="py-10 bg-white">
    <div class="max-w-(--breakpoint-2xl) mx-auto bg-[#F8F8F8] flex flex-col md:flex-row gap-10 items-center">
        <div class="info px-4 sm:px-6 lg:px-8">
            <div class="bg-[#FDE4E3] px-4 py-2 max-w-56 text-center">
                <span class=" text-[#F24842] font-bold uppercase">БЛИЖАЙШАЯ РЕГАТА</span>
            </div>
            <h2 class="section-title a-font text-[#2E325C] text-6xl py-6">Кубок CarterPro</h2>
            <div class="space-y-1.5 text-brand-gray font-medium text-lg">
                <div class="flex items-center gap-2 pb-3">
                    {!! file_get_contents(public_path('images/icons/calendar.svg')) !!}
                    23–25 мая
                </div>
                <div class="flex items-center gap-2 pb-3">
                    {!! file_get_contents(public_path('images/icons/marker.svg')) !!}
                    Москва, Россия
                </div>
                <div class="flex items-center gap-2 pb-5">
                    {!! file_get_contents(public_path('images/icons/waves.svg')) !!}
                    Пироговское водохранилище
                </div>
            </div>
            <p class="text-brand-gray text-lg">Прибрежная гонка сезона с участием команд класса Carter 30.</p>
            <button @click="$dispatch('open-join-regatta-modal', { regattaId: 'demo-regatta-id' })"
                    class="mt-6 bg-[#2D92CE] text-white py-2 px-6 hover:bg-[#2D92CE] transition-colors text-lg font-semibold cursor-pointer">
                Подать заявку  →
            </button>
        </div>
        <div class="pic max-w-[720px]">
            <img class="w-full" src="{{ asset('images/rules/rules_pic_1.png') }}" alt="">
        </div>
    </div>
    </section>
    <section class="py-10">
        <div class="max-w-(--breakpoint-2xl) mx-auto lg:p-6 bg-[#F8F8F8]">
            <div class="flex items-center justify-between mb-6">
                <h2 class="section-title a-font">Заявленные команды</h2>
                <a href="#" class="text-[#2E325C] text-lg font-semibold hover:underline flex items-center gap-4"><img src="{{ asset('images/icons/download.svg') }}" alt=""> Скачать список команд</a>
            </div>
            <div class="overflow-x-auto p-6 bg-white">
                <table class="w-full">
                    <thead>
                        <tr class="text-2xl text-[#2E325C] border-b border-[#EAEAEA]">
                            <th class="pb-2 text-center font-medium w-16 a-font">№</th>
                            <th class="pb-2 text-center font-medium a-font">Яхта</th>
                            <th class="pb-2 text-center font-medium a-font">Команда</th>
                            <th class="pb-2 text-center font-medium a-font">Капитан</th>
                            <th class="pb-2 text-center font-medium a-font">Состав команды</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y text-center font-medium">
                    <template x-data="{ results: [
                    {num: 1, yacht: 'Барс', team: 'Барс', capitan: 'Игорь Скалин', participants: 8},
                    {num: 2, yacht: 'Freedom', team: 'Freedom', capitan: 'Игорь Скалин', participants: 8},
                    {num: 3, yacht: 'Energie', team: 'Energie', capitan: 'Игорь Скалин', participants: 8},
                    {num: 4, yacht: 'Energie', team: 'Energie', capitan: 'Игорь Скалин', participants: 8},
                    {num: 5, yacht: 'Energie', team: 'Energie', capitan: 'Игорь Скалин', participants: 8},
                    ] 
                    }" 
                    x-for="(place, i) in results" :key="i">
                        <tr class="hover:bg-white transition-colors border-b border-[#EAEAEA]">
                            <td class="py-3" x-text="place.num"></td>
                            <td class="py-3" x-text="place.yacht"></td>
                            <td class="py-3" x-text="place.team"></td>
                            <td class="py-3" x-text="place.capitan"></td>
                            <td class="py-3" ><a @click="team_modal_open = true" href="#" class="text-[#2D92CE] font-medium underline hover:no-underline" x-text="place.participants + ' участников '"></a></td>
                        </tr>
                    </template>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    
    <section class="py-10">
        <div class="max-w-(--breakpoint-2xl) mx-auto bg-[#F8F8F8] flex flex-col md:flex-row gap-10 items-center">
            <div class="pic max-w-[720px] shrink-0">
                <img class="w-full h-full" src="{{ asset('images/details/details_2.png') }}" alt="">
            </div>
            <div class="info py-4 px-4 sm:px-6 lg:px-8">
                <h2 class="section-title a-font text-[#2E325C] text-5xl mb-8">О регате</h2>
                <p class="text-brand-gray font-medium text-lg mb-4">Кубок Carter Pro — одна из ключевых регат сезона для яхт класса Carter 30.</p>
                <p class="text-brand-gray font-medium text-lg mb-4">Соревнования проходят в акватории Пироговского водохранилища в формате прибрежных гонок. Участников ждут короткие и средние дистанции, требующие точной навигации, слаженной работы экипажа и стратегических решений на воде.</p>
                <p class="text-brand-gray font-medium text-lg mb-4">Регата объединяет команды из разных регионов и является частью календаря соревнований сезона 2026.</p>
                <div class="bg-white p-4 text-brand-gray">
                    <div class="flex pb-6">
                        <div><strong class="text-[#2E325C]">Уровень регаты:</strong>   <span>Кубок Ассоциации</span></div>
                    </div>
                    <div class="flex gap-16 pb-6">
                        <div><strong class="text-[#2E325C]">Гоночных дней:</strong>   <span>3</span></div>
                        <div><strong class="text-[#2E325C]">Количество гонок:</strong>   <span>5</span></div>
                    </div>
                    <div class="flex">
                        <div><strong class="text-[#2E325C]">Призы:</strong>   <span>Кубки и памятные награды для победителей и призёров.</span></div>
                    </div>
                </div>
            </div>

        </div>
    </section>
    <section class="py-10">
        <div class="max-w-(--breakpoint-2xl) mx-auto lg:p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="section-title a-font">Расписание</h2>
            </div>
            <div class="list grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="card bg-[#F8F8F8] p-4">
                    <div class="card-header border-b flex gap-4 border-b-[#EAEAEA] items-center pb-6">
                        <span class="rounded-full bg-[#2D92CE26] text-[#2D92CE] text-center flex justify-center items-center shrink-0 aspect-square size-11">
                            {!! file_get_contents(public_path('images/icons/calendar.svg')) !!}
                        </span>
                        <h3 class="a-font text-2xl">23 мая</h3>
                    </div>
                    <div class="card-item flex gap-6 py-6 border-b border-b-[#EAEAEA]">
                        <div class="time flex gap-2 font-medium">
                            <span>
                                {!! file_get_contents(public_path('images/icons/time.svg')) !!}
                            </span>
                            <span>
                                10:00
                            </span>
                        </div>
                        <div class="info">
                            <h4 class="pb-3 font-medium">Регистрация участников</h4>
                            <p class="text-sm">Регистрация команд и выдача документов</p>
                        </div>
                    </div>
                    <div class="card-item flex gap-6 py-6 border-b border-b-[#EAEAEA] last:border-b-0">
                        <div class="time flex gap-2 font-medium">
                            <span>
                                {!! file_get_contents(public_path('images/icons/time.svg')) !!}
                            </span>
                            <span>
                                11:00
                            </span>
                        </div>
                        <div class="info">
                            <h4 class="pb-3 font-medium">Собрание капитанов</h4>
                            <p class="text-sm">Инструктаж и ответы на вопросы</p>
                        </div>
                    </div>
                    <div class="card-item flex gap-6 py-6 border-b border-b-[#EAEAEA] last:border-b-0">
                        <div class="time flex gap-2 font-medium">
                            <span>
                                {!! file_get_contents(public_path('images/icons/time.svg')) !!}
                            </span>
                            <span>
                                12:00
                            </span>
                        </div>
                        <div class="info">
                            <h4 class="pb-3 font-medium">Первая гонка</h4>
                            <p class="text-sm">Старт первой гонки дня</p>
                        </div>
                    </div>
                    <div class="card-item flex gap-6 py-6 border-b border-b-[#EAEAEA] last:border-b-0">
                        <div class="time flex gap-2 font-medium">
                            <span>
                                {!! file_get_contents(public_path('images/icons/time.svg')) !!}
                            </span>
                            <span>
                                18:00
                            </span>
                        </div>
                        <div class="info">
                            <h4 class="pb-3 font-medium">Подведение итогов дня</h4>
                            <p class="text-sm">Предварительные результаты</p>
                        </div>
                    </div>
                </div>
                <div class="card bg-[#F8F8F8] p-4">
                    <div class="card-header border-b flex gap-4 border-b-[#EAEAEA] items-center pb-6">
                        <span class="rounded-full bg-[#2D92CE26] text-[#2D92CE] text-center flex justify-center items-center shrink-0 aspect-square size-11">
                            {!! file_get_contents(public_path('images/icons/calendar.svg')) !!}
                        </span>
                        <h3 class="a-font text-2xl">24 мая</h3>
                    </div>
                    <div class="card-item flex gap-6 py-6 border-b border-b-[#EAEAEA] last:border-b-0">
                        <div class="time flex gap-2 font-medium">
                            <span>
                                {!! file_get_contents(public_path('images/icons/time.svg')) !!}
                            </span>
                            <span>
                                11:00
                            </span>
                        </div>
                        <div class="info">
                            <h4 class="pb-3 font-medium">Гоночный день</h4>
                            <p class="text-sm">Основные гонки регаты</p>
                        </div>
                    </div>
                    <div class="card-item flex gap-6 py-6 border-b border-b-[#EAEAEA] last:border-b-0">
                        <div class="time flex gap-2 font-medium">
                            <span>
                                {!! file_get_contents(public_path('images/icons/time.svg')) !!}
                            </span>
                            <span>
                                11:00
                            </span>
                        </div>
                        <div class="info">
                            <h4 class="pb-3 font-medium">Подведение итогов дня</h4>
                            <p class="text-sm">Предварительные результаты</p>
                        </div>
                    </div>
                </div>
                <div class="card bg-[#F8F8F8] p-4">
                    <div class="card-header border-b flex gap-4 border-b-[#EAEAEA] items-center pb-6">
                        <span class="rounded-full bg-[#2D92CE26] text-[#2D92CE] text-center flex justify-center items-center shrink-0 aspect-square size-11">
                            {!! file_get_contents(public_path('images/icons/calendar.svg')) !!}
                        </span>
                        <h3 class="a-font text-2xl">25 мая</h3>
                    </div>
                    <div class="card-item flex gap-6 py-6 border-b border-b-[#EAEAEA] last:border-b-0">
                        <div class="time flex gap-2 font-medium">
                            <span>
                                {!! file_get_contents(public_path('images/icons/time.svg')) !!}
                            </span>
                            <span>
                                10:00
                            </span>
                        </div>
                        <div class="info">
                            <h4 class="pb-3 font-medium">Финальная гонка</h4>
                            <p class="text-sm">Заключительная гонка регаты.</p>
                        </div>
                    </div>
                    <div class="card-item flex gap-6 py-6 border-b border-b-[#EAEAEA] last:border-b-0">
                        <div class="time flex gap-2 font-medium">
                            <span>
                                {!! file_get_contents(public_path('images/icons/time.svg')) !!}
                            </span>
                            <span>
                                11:00
                            </span>
                        </div>
                        <div class="info">
                            <h4 class="pb-3 font-medium">Церемония награждения</h4>
                            <p class="text-sm">Подведение итогов и награждение победителей.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section class="py-10">
        <div class="max-w-(--breakpoint-2xl) mx-auto px-4 sm:px-6 lg:px-8 pdf-list">
            <h2 class="section-title a-font mb-8">Документы регаты</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-6">
                <template x-data="
                {
                    documents: [
                        {'title': 'Положение о соревнованиях',
                        'desc': 'Актуальная редакция от 12 мая 2025',
                        'path': '#'
                        },
                        {'title': 'Регламент',
                        'desc': 'Актуальная редакция от 12 мая 2025',
                        'path': '#'
                        },
                        {'title': 'Маршрут',
                        'desc': 'Актуальная редакция от 12 мая 2025',
                        'path': '#'
                        },
                        {'title': 'Гоночная инструкция',
                        'desc': 'Актуальная редакция от 12 мая 2025',
                        'path': '#'
                        },
                    ]
                }
                " x-for="doc in documents">
                    <div class="bg-[#F8F8F8] flex gap-4 hover:shadow-md transition-shadow cursor-pointer p-4">
                        <div class="max-w-16">
                            <img class="w-full" src="{{ asset('images/icons/pdf.png') }}" alt="">
                        </div>
                        <div class="">
                            <div class="text-[#2E325C] text-lg font-semibold mb-4" x-text='doc.title'></div>
                            <div class="text-brand-gray-light font-medium mb-4" x-text='doc.desc'></div>
                            <a x-bind:href="doc.path" class="text-[#2E325C] text-lg font-semibold flex gap-4 items-center"><img src="{{ asset('images/icons/download.svg') }}" alt=""> <span>Скачать PDF</span></a>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </section>
    <section class="py-10">
        <div class="max-w-(--breakpoint-2xl) mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="section-title a-font pb-8">Локация</h2>
            <div class="map">
                <img src="{{ asset('images/map.png') }}" alt="Локация">
            </div>
        </div>
    </section>
    <style>
        /*
            Minimal custom CSS — only what Tailwind cannot express:
            1. Responsive calc()-based flex-basis for slider cards
            2. Slide transition cubic-bezier
            3. Pagination dot active-width animation
        */
        .regatta-card { flex: 0 0 calc((100% - 4 * 1rem) / 3); }
        @media (max-width: 1023px) { .regatta-card { flex: 0 0 calc((100% - 2 * 1rem) / 2); } }
        @media (max-width: 639px)  { .regatta-card { flex: 0 0 calc((100% - 1 * 1rem) / 1); } }

        .slides   { transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .page-dot { transition: width 0.3s ease, background-color 0.3s ease; }
    </style>
    <section class="py-10">
        <div class="max-w-(--breakpoint-2xl) mx-auto px-4 sm:px-6 lg:px-8"  x-data="regattaCalendar()">
            <div class="flex justify-between">
                <h2 class="section-title a-font pb-8">Другие регаты сезона</h2>
                <div class="control-btns flex gap-4">
                    <button @click="prev()" :disabled="offset === 0"
                        class="z-10 bg-[#2D92CE] rounded-full w-9 h-9 flex items-center justify-center text-white hover:text-brand-red disabled:opacity-30 disabled:cursor-not-allowed transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                <button
                    @click="next()"
                    :disabled="offset >= maxOffset"
                    aria-label="Вперёд"
                    class="z-10
                            w-9 h-9 flex items-center justify-center
                            bg-[#2D92CE] text-white rounded-full shadow-md
                            hover:bg-brand-red
                            disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:bg-brand-blue
                            transition-colors duration-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                    </button>
                </div>
            </div>
            <div class="relative">
                <div class="overflow-hidden">
                    <div class="slides flex gap-6" :style="`transform: translateX(calc(-${offset} * (100% + ${gap}px) / ${visible}))`">
                        <template x-for="(regatta, i) in regattas" :key="i">
                            <div class="bg-[#F8F8F8] overflow-hidden  font-sans regatta-card">
                                <div class="relative">
                                    <img
                                        src="{{ asset('images/news/news_1.png') }}"
                                        alt="Парусная регата"
                                        class="w-full h-64 object-cover"
                                    />
                            
                                    <div class="absolute top-0 right-0 bg-[#ECECEC] px-4 py-2">
                                        <span class=" text-brand-gray-light font-bold text-sm uppercase">Планируемые</span>
                                    </div>
                            
                                    <div class="absolute bottom-0 left-0 bg-[#F8F8F8] text-[#2E325C] px-4 py-2">
                                        <span class="font-bold text-sm tracking-wide" x-text="regatta.date"></span>
                                    </div>
                                </div>
                        
                                <div class="px-6 pt-6 pb-7 space-y-4">
                                    <h2 class="text-brand-navy font-semibold text-lg leading-tight" x-text="regatta.title"></h2>
                            
                                    <div class="flex items-center gap-3 text-gray-600">
                                        <img src="{{ asset('images/icons/marker.svg') }}" alt=""> <span  x-text="regatta.city"></span>
                                    </div>
                            
                                    <div class="flex items-center gap-3 text-gray-600">
                                        <img src="{{ asset('images/icons/waves.svg') }}" alt=""> <span  x-text="regatta.location"></span>
                                    </div>

                                    <button class="flex items-center gap-2 text-brand-navy font-bold text-lg hover:gap-3 transition-all duration-200 group">
                                        Подробнее  →
                                        <span class="text-brand-navy group-hover:translate-x-1 transition-transform duration-200">
                                        </span>
                                    </button>
                                </div>

                            </div>
                        </template>
                    </div>

                    <div class="flex justify-center gap-1.5 mt-5">
                        <template x-for="(_, idx) in Array.from({ length: maxOffset + 1 })" ...>
                            <button
                                class="page-dot h-1.5 rounded-full border-0 cursor-pointer"
                                :class="idx === offset ? 'w-5 bg-[#2D92CE]' : 'w-1.5 bg-slate-300'"
                                @click="goTo(idx)"
                                :aria-label="`Месяц ${idx + 1}`">
                            </button>
                        </template>
                    </div>
                </div>

            </div>
    
        </div>
    </section>
    <div x-show="team_modal_open" 
            x-cloak 
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
        <div class="relative p-6 w-full max-w-[1000px] bg-white gap-6"
            @click.away="team_modal_open = false" 
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
        >

            <div class="info__header flex justify-between items-start mb-4">
                <h4 class="a-font text-3xl text-[#2E325C]">Состав команды Барс</h4>
                <div class="close">
                    <button @click="team_modal_open = false" class="text-2xl font-bold">&times;</button>
                </div>
            </div>
            <table class="w-full bg-[#F8F8F8]">
                <thead>
                    <tr class="text-2xl text-[#2E325C] border-b border-[#EAEAEA]">
                        <th class="pb-2 text-center font-medium a-font">Участник</th>
                        <th class="pb-2 text-center font-medium a-font">Дата рождения</th>
                        <th class="pb-2 text-center font-medium a-font">Разряд</th>
                    </tr>
                </thead>
                <tbody class="divide-y text-center font-medium">
                <template x-data="{ results: [
                {num: 1, participant: 'Игорь Скалин', birthday: '1970-01-12', сategory: 'ЗМС'},
                {num: 2, participant: 'Владимир Капитонов', birthday: '1980-03-25', сategory: 'МС' },
                {num: 3, participant: 'Дмитрий Леонтьев', birthday: '1971-11-18', сategory: 'МС'},
                {num: 3, participant: 'Дмитрий Леонтьев', birthday: '1971-11-18', сategory: 'МС'},
                {num: 3, participant: 'Дмитрий Леонтьев', birthday: '1971-11-18', сategory: 'МС'},
                {num: 3, participant: 'Дмитрий Леонтьев', birthday: '1971-11-18', сategory: 'МС'},
                {num: 3, participant: 'Дмитрий Леонтьев', birthday: '1971-11-18', сategory: 'МС'},
                {num: 3, participant: 'Дмитрий Леонтьев', birthday: '1971-11-18', сategory: 'МС'},
                ] 
                }" 
                x-for="(place, i) in results" :key="i">
                    <tr class="hover:bg-white transition-colors border-b border-[#EAEAEA]">
                        <td class="py-3" x-text="place.participant"></td>
                        <td class="py-3" x-text="place.birthday"></td>
                        <td class="py-3" x-text="place.сategory"></td>
                    </tr>
                </template>
                </tbody>
            </table>
            

        </div>

    </div>
</main>

<script>
function regattaCalendar() {
  return {
    visible: 3,
    gap: 16,   // matches Tailwind gap-4 (1rem = 16px)
    offset: 0,
 
    get maxOffset() {
      return Math.max(0, this.regattas.length - this.visible);
    },
 
    prev() { if (this.offset > 0) this.offset--; },
    next() { if (this.offset < this.maxOffset) this.offset++; },
    goTo(idx) {
      this.offset = Math.min(Math.max(idx, 0), this.maxOffset);
    },
 
    init() {
      this.updateVisible();
      window.addEventListener('resize', () => this.updateVisible());
    },
    updateVisible() {
      const w = window.innerWidth;
      this.visible = w < 640 ? 1 : w < 1024 ? 2 : 3;
      if (this.offset > this.maxOffset) this.offset = Math.max(0, this.maxOffset);
    },
 
    regattas: [
        {title: 'Летний кубок', date: '12-16 июля', city: 'Москва, Россия', location:'Пироговское водохранилище', img:'{{ asset('images/rules/rules_pic_1.png') }}'},
        {title: 'Летний кубок', date: '16-18 июля', city: 'Москва, Россия', location:'Пироговское водохранилище', img:'{{ asset('images/rules/rules_pic_1.png') }}'},
        {title: 'Летний кубок', date: '18-20 июля', city: 'Москва, Россия', location:'Пироговское водохранилище', img:'{{ asset('images/rules/rules_pic_1.png') }}'},
        {title: 'Летний кубок', date: '20-22 июля', city: 'Москва, Россия', location:'Пироговское водохранилище', img:'{{ asset('images/rules/rules_pic_1.png') }}'},
        {title: 'Летний кубок', date: '22-25 июля', city: 'Москва, Россия', location:'Пироговское водохранилище', img:'{{ asset('images/rules/rules_pic_1.png') }}'},
        {title: 'Летний кубок', date: '25-28 июля', city: 'Москва, Россия', location:'Пироговское водохранилище', img:'{{ asset('images/rules/rules_pic_1.png') }}'},
    ]

  }
}
</script>

<x-feedback-section></x-feedback-section>
</x-public-layout>