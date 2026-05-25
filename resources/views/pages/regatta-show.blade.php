<x-public-layout>
    <x-breadcrumbs_page :title="$regatta->name" />

    {{-- ===== КАРТОЧКА РЕГАТЫ ===== --}}
    <main class="main" x-data="{ team_modal_open: false, activeTeamIndex: null, entriesJson: @js($entriesJson) }">
        <section class="py-10 bg-white">
            <div class="container mx-auto bg-brand-light-bg flex flex-col md:flex-row gap-10 justify-between items-center">
                <div class="info">
                    @if($regatta->isUpcoming())
                        <div class="bg-brand-pink-bg px-4 py-2 max-w-56 text-center">
                            <span class="text-brand-red font-bold uppercase">БЛИЖАЙШАЯ РЕГАТА</span>
                        </div>
                    @endif
                    <h2 class="section-title a-font text-brand-dark text-6xl py-6">{{ $regatta->name }}</h2>
                    <div class="space-y-1.5 text-brand-gray font-medium text-lg">
                        <div class="flex items-center gap-2 pb-3">
                            <x-icon-2 name="calendar" />
                            {{ $regatta->dateRange() }}
                        </div>
                        <div class="flex items-center gap-2 pb-3">
                            <x-icon-2 name="marker" />
                            {{ $regatta->location }}
                        </div>
                        @if($regatta->water_area)
                            <div class="flex items-center gap-2 pb-5">
                                <x-icon-2 name="waves" />
                                {{ $regatta->water_area }}
                            </div>
                        @endif
                    </div>
                    <p class="text-brand-gray text-lg">{{ $regatta->short_description }}</p>
                    @if($userIsEntered)
                        <div class="mt-6 bg-brand-light-bg border border-brand-blue text-brand-blue py-2 px-6 text-lg font-semibold inline-block">
                            Вы уже заявлены
                        </div>
                    @elseif($regatta->isFinished())
                        <button disabled
                                class="mt-6 bg-gray-300 text-gray-500 py-2 px-6 text-lg font-semibold cursor-not-allowed opacity-60">
                            Регата завершена
                        </button>
                    @else
                        <button @click="$dispatch('open-join-regatta-modal', { regattaId: '{{ $regatta->id }}' })"
                                class="mt-6 bg-brand-blue text-white py-2 px-6 hover:bg-brand-blue transition-colors text-lg font-semibold cursor-pointer">
                            Подать заявку →
                        </button>
                    @endif
                </div>
                <div class="pic max-w-[720px]">
                    <img class="w-full"
                         src="{{ asset('storage/' . $regatta->background_image) }}"
                         alt="{{ $regatta->name }}" />
                </div>
            </div>
        </section>
        {{-- ===== РАСПИСАНИЕ ===== --}}
        @if($scheduleDays->isNotEmpty())
            <section class="py-10">
                <div class="container mx-auto lg:p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="section-title a-font">Расписание</h2>
                    </div>
                    <div class="list grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($scheduleDays as $day)
                            <div class="card bg-brand-light-bg p-4">
                                <div class="card-header border-b flex gap-4 border-brand-border items-center pb-6">
                                    <span class="rounded-full bg-brand-blue-light text-brand-blue text-center flex justify-center items-center shrink-0 aspect-square size-11">
                                        <x-icon-2 name="calendar" />
                                    </span>
                                    <h3 class="a-font text-2xl">{{ $day['date'] }}</h3>
                                </div>
                                @foreach($day['events'] as $event)
                                    <div class="card-item flex gap-6 py-6 border-b border-brand-border last:border-b-0">
                                        <div class="time flex gap-2 font-medium">
                                            <span><x-icon-2 name="time" /></span>
                                            <span>{{ $event['time'] }}</span>
                                        </div>
                                        <div class="info">
                                            <h4 class="pb-3 font-medium">{{ $event['title'] }}</h4>
                                            <p class="text-sm">{{ $event['description'] }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- ===== ЗАЯВЛЕННЫЕ КОМАНДЫ ===== --}}
        @if(!$regatta->isFinished())
        <section class="py-10 teams">
            <div class="container mx-auto lg:p-6 bg-brand-light-bg">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="section-title a-font">Заявленные команды</h2>
                    <a href="#" class="text-brand-dark text-lg font-semibold hover:underline items-center gap-4 hidden md:flex">
                        <x-icon-2 name="download" /> Скачать список команд
                    </a>
                </div>
                <div class="overflow-x-auto p-3 md:p-6 bg-white">
                    <table class="w-full responsive-table">
                        <thead>
                            <tr class="text-2xl text-brand-dark border-b border-brand-border">
                                <th class="pb-2 text-center font-medium w-16 a-font">№</th>
                                <th class="pb-2 text-center font-medium a-font">Яхта</th>
                                <th class="pb-2 text-center font-medium a-font">Команда</th>
                                <th class="pb-2 text-center font-medium a-font">Капитан</th>
                                <th class="pb-2 text-center font-medium a-font">Состав команды</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-center font-medium">
                            @forelse($entries as $index => $entry)
                                <tr class="hover:bg-white transition-colors border-b border-brand-border pb-8! md:pb-0!">
                                    <td data-label="№" class="py-3">{{ $index + 1 }}</td>
                                    <td data-label="Яхта" class="py-3">{{ $entry->yacht?->name ?? '—' }}</td>
                                    <td data-label="Команда" class="py-3">{{ $entry->team?->name ?? '—' }}</td>
                                    <td data-label="Капитан" class="py-3">{{ $entry->team?->organizer?->full_name ?? '—' }}</td>
                                    <td data-label="Состав команды" class="py-3">
                                        <a @click="team_modal_open = true; activeTeamIndex = {{ $index }}"
                                           href="#"
                                           class="text-brand-blue font-medium underline hover:no-underline">
                                            {{ $entry->team?->activeMembers?->count() ?? 0 }} участников
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-6 text-brand-gray-light">Нет заявленных команд</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                    <a href="#" class="text-brand-dark text-lg justify-center font-semibold hover:underline items-center gap-4 md:hidden flex">
                        <x-icon-2 name="download" /> Скачать список команд
                    </a>
            </div>
        </section>
        @endif

        {{-- ===== РЕЗУЛЬТАТЫ ===== --}}
        <livewire:regatta-results mode="show" :regatta-id="$regatta->id" />
        {{-- ===== О РЕГАТЕ ===== --}}
        <section class="py-10">
            <div class="container mx-auto bg-brand-light-bg flex flex-col md:flex-row gap-10 items-center">
                <div class="pic max-w-[720px] shrink-0">
                    <img class="w-full h-full" src="{{ asset('storage/' . $regatta->background_image) }}" alt="{{ $regatta->name }}" />
                </div>
                <div class="info py-4">
                    <h2 class="section-title a-font text-brand-dark text-5xl mb-8">О регате</h2>
                    <p class="text-brand-gray font-medium text-lg mb-4">{!! $regatta->description !!}</p>
                    <div class="bg-white p-4 text-brand-gray">
                        <div class="flex pb-6">
                            <div>
                                <strong class="text-brand-dark">Уровень регаты:</strong>
                                <span>Кубок Ассоциации</span>
                            </div>
                        </div>
                        <div class="flex gap-16 pb-6">
                            <div>
                                <strong class="text-brand-dark">Гоночных дней:</strong>
                                <span>{{ $regatta->race_days_count }}</span>
                            </div>
                            <div>
                                <strong class="text-brand-dark">Количество гонок:</strong>
                                <span>{{ $regatta->races_count }}</span>
                            </div>
                        </div>
                        <div class="flex">
                            <div>
                                <strong class="text-brand-dark">Призы:</strong>
                                <span>{{ $regatta->prizes }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>



        {{-- ===== ДОКУМЕНТЫ РЕГАТЫ ===== --}}
        @if($documents->isNotEmpty())
            <section class="py-10">
                <div class="container mx-auto pdf-list">
                    <h2 class="section-title a-font mb-8">Документы регаты</h2>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-6">
                        @foreach($documents as $doc)
                            <div class="bg-brand-light-bg flex gap-4 hover:shadow-md transition-shadow cursor-pointer p-4">
                                <div class="max-w-16">
                                    <img class="w-full" src="{{ asset('images/icons/pdf.png') }}" alt="PDF" />
                                </div>
                                <div>
                                    <div class="text-brand-dark text-lg font-semibold mb-4">{{ $doc->title }}</div>
                                    <div class="text-brand-gray-light font-medium mb-4">{{ $doc->description }}</div>
                                    <a href="{{ $doc->file_url }}"
                                       class="text-brand-dark text-lg font-semibold flex gap-4 items-center">
                                        <x-icon-2 name="download" /> <span>Скачать PDF</span>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- ===== ЛОКАЦИЯ ===== --}}
        <section class="py-10">
            <div class="container mx-auto">
                    <h2 class="section-title a-font pb-8">Локация</h2>
                    
                    {{-- Проверяем, заполнено ли поле в базе данных --}}
                    @if(!empty($regatta->coordinates))
                        <div class="map" style="position: relative; overflow: hidden; border-radius: 8px;">
                            {{-- Контейнер, куда Яндекс.Карты встроят интерактивную карту --}}
                            <div id="public-yandex-map" style="width: 100%; height: 450px; background-color: #f3f4f6;"></div>
                        </div>

                        {{-- Подключаем API Яндекса и инициализируем карту --}}
                        <script src="https://api-maps.yandex.ru/2.1/?apikey=ffd9d711-109d-415d-bf73-e1a935512160&lang=ru_RU" type="text/javascript"></script>
                        <script type="text/javascript">
                            ymaps.ready(function () {
                                var coordinates = @json($regatta->coordinates);

                                var myMap = new ymaps.Map('public-yandex-map', {
                                    center: coordinates,
                                    zoom: 15,
                                    controls: ['zoomControl', 'fullscreenControl']
                                });

                                var myPlacemark = new ymaps.Placemark(coordinates, {
                                    hintContent: '{{ e($regatta->location) }}',
                                    balloonContent: '{{ e($regatta->location) }}'
                                }, {
                                    preset: 'islands#redDotIcon'
                                });

                                myMap.geoObjects.add(myPlacemark);
                                myMap.behaviors.disable('scrollZoom');
                            });
                        </script>
                    @else
                        {{-- Резервный вариант, если в БД нет локации --}}
                        <div class="map">
                            <img src="{{ asset('images/map.png') }}" alt="Локация не указана" />
                        </div>
                    @endif
                </div>
        </section>

        {{-- ===== ДРУГИЕ РЕГАТЫ СЕЗОНА ===== --}}
        @if($otherRegattas->isNotEmpty())
            <section class="py-10">
                <div class="container mx-auto" x-data="regattaCalendar(@js($otherRegattasData))">
                    <div class="flex justify-between">
                        <h2 class="section-title a-font pb-8">Другие регаты сезона</h2>
                        <div class="control-btns md:flex gap-4 hidden">
                            <button @click="prev()" :disabled="offset === 0"
                                class="z-10 bg-brand-blue rounded-full w-9 h-9 flex items-center justify-center text-white disabled:opacity-30 disabled:cursor-not-allowed transition-all"
                                aria-label="Назад">
                                <x-icon-chevron direction="left" class="w-5 h-5" />
                            </button>
                            <button @click="next()"
                                :disabled="offset >= maxOffset"
                                aria-label="Вперёд"
                                class="z-10 w-9 h-9 flex items-center justify-center
                                       bg-brand-blue text-white rounded-full shadow-md
                                       disabled:opacity-30 disabled:cursor-not-allowed
                                       transition-colors duration-200">
                                <x-icon-chevron direction="right" class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                    <div class="relative">
                        <div class="overflow-hidden">
                            <div class="slides flex gap-6"
                                 :style="`transform: translateX(calc(-${offset} * (100% + ${gap}px) / ${visible}))`">
                                <template x-for="(regatta, i) in regattas" :key="i">
                                    <div class="bg-brand-light-bg overflow-hidden font-sans regatta-card">
                                        <div class="relative">
                                            <img :src="regatta.img"
                                                 :alt="regatta.title"
                                                 class="w-full h-64 object-cover" />
                                            <div class="absolute top-0 right-0 bg-brand-bg-secondary px-4 py-2">
                                                <span class="text-brand-gray-light font-bold text-sm uppercase"
                                                      x-text="regatta.statusLabel"></span>
                                            </div>
                                            <div class="absolute bottom-0 left-0 bg-brand-light-bg text-brand-dark px-4 py-2">
                                                <span class="font-bold text-sm tracking-wide" x-text="regatta.date"></span>
                                            </div>
                                        </div>
                                        <div class="px-6 pt-6 pb-7 space-y-4">
                                            <h2 class="text-brand-navy font-semibold text-lg leading-tight"
                                                x-text="regatta.title"></h2>
                                            <div class="flex items-center gap-3 text-gray-600">
                                                <x-icon-2 name="marker" /> <span x-text="regatta.city"></span>
                                            </div>
                                            <div class="flex items-center gap-3 text-gray-600">
                                                <x-icon-2 name="waves" /> <span x-text="regatta.location"></span>
                                            </div>
                                            <a :href="regatta.url"
                                               class="flex items-center gap-2 text-brand-navy font-bold text-lg hover:gap-3 transition-all duration-200 group">
                                                Подробнее  →
                                                <span class="text-brand-navy group-hover:translate-x-1 transition-transform duration-200">&rarr;</span>
                                            </a>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <div class="flex justify-center gap-1.5 mt-5">
                                <template x-for="(_, idx) in Array.from({ length: maxOffset + 1 })" :key="idx">
                                    <button class="page-dot h-1.5 rounded-full border-0 cursor-pointer"
                                        :class="idx === offset ? 'w-5 bg-brand-blue' : 'w-1.5 bg-slate-300'"
                                        @click="goTo(idx)"
                                        :aria-label="`Страница ${idx + 1}`">
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        {{-- ===== МОДАЛЬНОЕ ОКНО: СОСТАВ КОМАНДЫ ===== --}}
        <div x-show="team_modal_open"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
            <div class="relative p-6 w-full max-w-[1000px] bg-white gap-6"
                 @click.away="team_modal_open = false"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
                <div class="info__header flex justify-between items-start mb-4">
                    <h4 class="a-font text-lg md:text-3xl text-brand-dark">
                        Состав команды
                        <span x-text="activeTeamIndex !== null ? entriesJson[activeTeamIndex]?.team_name : ''"></span>
                    </h4>
                    <div class="close">
                        <button @click="team_modal_open = false" class="text-2xl font-bold">&times;</button>
                    </div>
                </div>
                <table class="w-full bg-brand-light-bg responsive-table">
                    <thead>
                        <tr class="text-2xl text-brand-dark border-b border-brand-border">
                            <th class="pb-2 text-center font-medium a-font">Участник</th>
                            <th class="pb-2 text-center font-medium a-font">Дата рождения</th>
                            <th class="pb-2 text-center font-medium a-font">Разряд</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y text-center font-medium">
                        <template x-for="(member, i) in (activeTeamIndex !== null ? (entriesJson[activeTeamIndex]?.crew || []) : [])" :key="i">
                            <tr class="hover:bg-white transition-colors border-b border-brand-border pb-8! md:pb-0!">
                                <td data-label="Участник" class="py-3" x-text="member.name"></td>
                                <td data-label="Дата рождения" class="py-3" x-text="member.birthday"></td>
                                <td data-label="Разряд" class="py-3" x-text="member.rank"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        function regattaCalendar(regattas) {
            return {
                visible: 3,
                gap: 16,
                offset: 0,

                get maxOffset() {
                    return Math.max(0, regattas.length - this.visible);
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

                regattas: regattas,
            }
        }
    </script>

    <style>
        .regatta-card { flex: 0 0 calc((100% - 4 * 1rem) / 3); }
        @media (max-width: 1023px) { .regatta-card { flex: 0 0 calc((100% - 2 * 1rem) / 2); } }
        @media (max-width: 639px)  { .regatta-card { flex: 0 0 calc((100% - 1 * 1rem) / 1); } }
        .slides   { transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .page-dot { transition: width 0.3s ease, background-color 0.3s ease; }
    </style>

    <x-feedback-section />
</x-public-layout>
