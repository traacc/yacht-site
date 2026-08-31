{{--
    Карточка бронирования яхты (ТЗ 3-го этапа, п. 7).

    Календарь и расчёт стоимости живут в одном x-data с формой заявки: даты
    выбираются кликом по свободным дням и сразу уходят в заявку. Занятость и
    цены приходят с сервера (@see App\Services\YachtBooking) — в браузере
    только отрисовка выбранного периода.

    Ожидает: $type, $yacht, $calendar, $quote, $from, $to, $available, $terms, $others.
--}}
@php
    $gallery = $yacht->getMedia('gallery');
    $interior = $yacht->getMedia('interior_gallery');
    $cover = $gallery->first();

    $params = collect([
        ['label' => 'Класс', 'value' => $yacht->class ?? 'Carter 30'],
        ['label' => 'Парусный номер', 'value' => $yacht->vfps_number],
        ['label' => 'Проект', 'value' => $yacht->project],
        ['label' => 'Год выпуска', 'value' => $yacht->year],
        ['label' => 'Регион базирования', 'value' => $yacht->home_region],
        ['label' => 'Место стоянки', 'value' => $yacht->mooring_place],
        ['label' => 'Масса, кг', 'value' => $yacht->current_mass_kg],
    ])->filter(fn (array $param): bool => trim((string) $param['value']) !== '');

    $options = $yacht->optionValues
        ->sortBy([
            fn ($value) => $value->option->sort_order,
            fn ($value) => $value->option->label,
        ]);
@endphp

<x-public-layout
    :title="'Аренда яхты «' . $yacht->name . '» — Yacht Association'"
    :description="'Аренда яхты «' . $yacht->name . '»: свободные даты, стоимость суток и бронирование онлайн.'">

    <x-breadcrumbs_page :title="$yacht->name"></x-breadcrumbs_page>

    <main>
        <section class="py-10 px-4 sm:px-6 lg:px-8">
            <div class="container mx-auto">
                <a href="{{ route('services.yacht-rental') }}" class="text-[#2D92CE] font-semibold hover:underline text-sm">
                    ← Все яхты в аренду
                </a>

                <h1 class="a-font text-3xl md:text-5xl text-[#2E325C] mt-4 mb-2">{{ $yacht->name }}</h1>

                <div class="text-brand-gray-light md:text-lg mb-8">
                    {{ $yacht->class ?? 'Carter 30' }}@if ($yacht->year), {{ $yacht->year }} г.@endif
                    @if ($yacht->home_region) · {{ $yacht->home_region }}@endif
                </div>

                @if ($available === false)
                    <div class="bg-[#F4C9C6] text-[#2E325C] px-4 py-3 mb-8">
                        Выбранные даты уже заняты — отметьте свободный период в календаре ниже.
                    </div>
                @endif

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8"
                     x-data="yachtBooking({
                        windows: @js($calendar['windows']),
                        busy: @js($calendar['busy']),
                        start: @js($available === false ? null : $from?->toDateString()),
                        end: @js($available === false ? null : $to?->toDateString()),
                        url: @js(route('yacht-rental.request', $yacht)),
                        csrf: @js(csrf_token()),
                        user: @js(['name' => auth()->user()?->name ?? '', 'phone' => auth()->user()?->phone ?? '', 'email' => auth()->user()?->email ?? '']),
                     })">

                    {{-- ===== Левая колонка: галерея и характеристики ===== --}}
                    <div class="lg:col-span-2">
                        @if ($gallery->isNotEmpty() || $interior->isNotEmpty())
                            <div class="mb-10"
                                 x-data="{
                                    tab: {{ $gallery->isNotEmpty() ? "'exterior'" : "'interior'" }},
                                    index: 0,
                                    lightbox: false,
                                    photos: {
                                        exterior: @js($gallery->map(fn ($media) => ['url' => $media->getUrl(), 'thumb' => $media->getUrl('thumb'), 'name' => $media->name])->values()),
                                        interior: @js($interior->map(fn ($media) => ['url' => $media->getUrl(), 'thumb' => $media->getUrl('thumb'), 'name' => $media->name])->values()),
                                    },
                                    get current() { return this.photos[this.tab] ?? []; },
                                 }">
                                @if ($gallery->isNotEmpty() && $interior->isNotEmpty())
                                    <div class="flex gap-4 mb-4">
                                        <button type="button" @click="tab = 'exterior'; index = 0"
                                                :class="tab === 'exterior' ? 'text-white bg-[#2D92CE]' : 'bg-[#F8F8F8]'"
                                                class="p-3 font-medium transition-colors">Экстерьер</button>
                                        <button type="button" @click="tab = 'interior'; index = 0"
                                                :class="tab === 'interior' ? 'text-white bg-[#2D92CE]' : 'bg-[#F8F8F8]'"
                                                class="p-3 font-medium transition-colors">Интерьер</button>
                                    </div>
                                @endif

                                <div class="relative mb-3">
                                    <img :src="current[index]?.url" :alt="current[index]?.name || '{{ $yacht->name }}'"
                                         class="w-full aspect-video object-cover cursor-pointer bg-[#F8F8F8]"
                                         @click="lightbox = true">

                                    <template x-if="current.length > 1">
                                        <div>
                                            <button type="button" @click="index = index > 0 ? index - 1 : current.length - 1"
                                                    class="absolute rounded-full left-2 top-1/2 -translate-y-1/2 bg-brand-blue text-white w-10 h-10 flex items-center justify-center text-3xl pb-1.5">‹</button>
                                            <button type="button" @click="index = index < current.length - 1 ? index + 1 : 0"
                                                    class="absolute rounded-full right-2 top-1/2 -translate-y-1/2 bg-brand-blue text-white w-10 h-10 flex items-center justify-center text-3xl pb-1.5">›</button>
                                        </div>
                                    </template>
                                </div>

                                <div class="flex gap-2 overflow-x-auto pb-1" x-show="current.length > 1">
                                    <template x-for="(photo, idx) in current" :key="idx">
                                        <img :src="photo.thumb || photo.url" :alt="photo.name" @click="index = idx"
                                             :class="idx === index ? 'ring-2 ring-[#2D92CE] opacity-100' : 'opacity-60 hover:opacity-100'"
                                             class="w-20 h-20 object-cover cursor-pointer shrink-0 transition-opacity">
                                    </template>
                                </div>

                                <template x-teleport="body">
                                    <div x-show="lightbox" x-cloak
                                         @keydown.window.escape="lightbox = false"
                                         @click.self="lightbox = false"
                                         style="position: fixed; inset: 0; z-index: 100000; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.9); padding: 1rem;">
                                        <button type="button" @click="lightbox = false"
                                                class="absolute top-4 right-4 text-white text-4xl font-bold">&times;</button>
                                        <button type="button" @click="index = index > 0 ? index - 1 : current.length - 1"
                                                class="absolute left-4 top-1/2 -translate-y-1/2 text-white text-5xl">‹</button>
                                        <img :src="current[index]?.url" :alt="current[index]?.name"
                                             class="max-w-full max-h-[85vh] object-contain mx-auto">
                                        <button type="button" @click="index = index < current.length - 1 ? index + 1 : 0"
                                                class="absolute right-4 top-1/2 -translate-y-1/2 text-white text-5xl">›</button>
                                    </div>
                                </template>
                            </div>
                        @elseif ($cover === null)
                            <img class="w-full aspect-video object-cover mb-10" src="{{ asset('images/gallery.webp') }}" alt="{{ $yacht->name }}">
                        @endif

                        {{-- ===== Календарь ===== --}}
                        <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Свободные даты</h2>

                        <div class="bg-[#F8F8F8] p-4 md:p-6 mb-10">
                            <p class="mb-4 text-brand-gray" x-show="hasWindows">
                                Отметьте день заезда и день выезда — стоимость посчитается автоматически.
                            </p>
                            <p class="mb-4 text-brand-gray" x-show="!hasWindows" x-cloak>
                                Владелец пока не открыл даты для аренды. Оставьте заявку — согласуем период вручную.
                            </p>

                            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                                <h3 class="a-font text-xl md:text-2xl text-[#2E325C]" x-text="monthLabel"></h3>
                                <div class="flex items-center gap-4 flex-wrap text-sm">
                                    <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-[#BAD5C6] inline-block"></span> Свободно</span>
                                    <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-[#F4C9C6] inline-block"></span> Занято</span>
                                    <div class="flex items-center gap-1">
                                        <button type="button" @click="prevMonth()" class="w-8 h-8 flex items-center justify-center text-[#2D92CE] text-xl hover:bg-white transition-colors">‹</button>
                                        <button type="button" @click="nextMonth()" class="w-8 h-8 flex items-center justify-center text-[#2D92CE] text-xl hover:bg-white transition-colors">›</button>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-7 mb-1">
                                <template x-for="wd in weekdays" :key="wd">
                                    <div class="text-center font-semibold text-[#2E325C] py-2" x-text="wd"></div>
                                </template>
                            </div>

                            <div class="grid grid-cols-7 gap-px bg-[#EAEAEA] border border-[#EAEAEA]" @mouseleave="hoverDate = null">
                                <template x-for="(cell, i) in cells" :key="i">
                                    <div class="h-12 md:h-16 flex items-center justify-center transition-all"
                                         :class="cellClass(cell)"
                                         @click="selectDay(cell)"
                                         @mouseenter="hoverDay(cell)"
                                         x-text="cell.day"></div>
                                </template>
                            </div>

                            <p class="mt-3 text-sm text-brand-gray" x-show="start && !end" x-cloak>
                                Заезд: <span class="font-semibold" x-text="formatDate(start)"></span>. Выберите день выезда.
                            </p>
                        </div>

                        {{-- ===== Характеристики ===== --}}
                        @if ($params->isNotEmpty())
                            <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Характеристики</h2>
                            <div class="overflow-x-auto mb-10">
                                <table class="w-full border-collapse bg-[#F8F8F8]">
                                    <tbody class="divide-y">
                                        @foreach ($params as $param)
                                            <tr class="border-b border-[#EAEAEA]">
                                                <td class="py-3 px-4 font-semibold text-[#2E325C] w-1/2">{{ $param['label'] }}</td>
                                                <td class="py-3 px-4">{{ $param['value'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        {{-- ===== Оснащение ===== --}}
                        @if ($options->isNotEmpty())
                            <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Оснащение</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-2 bg-[#F8F8F8] p-6 mb-10">
                                @foreach ($options as $value)
                                    <div class="flex gap-2">
                                        <span class="font-semibold py-1">{{ $value->option->label }}:</span>
                                        <span class="py-1">{{ $value->label }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- ===== Для чего подходит ===== --}}
                        @if (is_array($yacht->suitable_for) && $yacht->suitable_for !== [])
                            <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Для чего подходит яхта</h2>
                            <div class="flex flex-col gap-3 mb-10">
                                @foreach ($yacht->suitable_for as $purpose)
                                    <div class="flex items-center gap-3">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" class="shrink-0" xmlns="http://www.w3.org/2000/svg">
                                            <rect x="3" y="3" width="18" height="18" rx="0" stroke="#2E325C" stroke-width="1"/>
                                            <path d="M7 12l3 3 7-7" stroke="#2D92CE" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <span>{{ $purpose }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- ===== Условия аренды ===== --}}
                        @if (trim(strip_tags((string) $terms, '<img>')) !== '')
                            <div id="rental-terms" class="mb-10">
                                <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Условия аренды</h2>
                                <div class="prose max-w-none text-brand-gray">{!! $terms !!}</div>
                            </div>
                        @endif
                    </div>

                    {{-- ===== Правая колонка: бронирование ===== --}}
                    <div class="lg:col-span-1">
                        <div class="border border-[#C6C6C6] p-6 lg:sticky lg:top-6">
                            <div class="mb-4">
                                @if ($quote['event'] !== null)
                                    <div class="text-3xl a-font text-[#2E325C]">
                                        от {{ number_format($quote['event'], 0, '.', ' ') }} ₽
                                        <span class="text-base text-brand-gray-light">/ сутки</span>
                                    </div>
                                    @if ($quote['pro'] !== null && $quote['pro'] !== $quote['event'])
                                        <div class="text-sm text-brand-gray-light mt-1">
                                            Для профессиональных команд — {{ number_format($quote['pro'], 0, '.', ' ') }} ₽/сутки
                                        </div>
                                    @endif
                                @else
                                    <div class="text-2xl a-font text-[#2E325C]">Стоимость по запросу</div>
                                @endif
                            </div>

                            <div class="grid grid-cols-2 gap-3 mb-4">
                                <label class="block">
                                    <span class="block text-sm text-brand-gray-light mb-1">Заезд</span>
                                    <input type="date" x-model="start" @change="normalize()"
                                           class="block appearance-none border border-[#C6C6C6] w-full text-sm p-2">
                                </label>
                                <label class="block">
                                    <span class="block text-sm text-brand-gray-light mb-1">Выезд</span>
                                    <input type="date" x-model="end" @change="normalize()"
                                           class="block appearance-none border border-[#C6C6C6] w-full text-sm p-2">
                                </label>
                            </div>

                            <div class="bg-[#F8F8F8] p-4 mb-4" x-show="days > 0" x-cloak>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-brand-gray-light">Срок аренды</span>
                                    <span class="font-semibold text-[#2E325C]" x-text="daysLabel"></span>
                                </div>
                                <template x-if="priceEvent !== null">
                                    <div class="flex justify-between items-baseline">
                                        <span class="text-brand-gray-light text-sm">Итого</span>
                                        <span class="text-xl font-bold text-[#2E325C]" x-text="money(priceEvent * days)"></span>
                                    </div>
                                </template>
                                <template x-if="priceEvent === null">
                                    <div class="text-sm text-brand-gray-light">Стоимость подтвердит менеджер.</div>
                                </template>
                                <div class="text-xs text-brand-gray-light mt-2" x-show="!rangeFree" x-cloak>
                                    В выбранном периоде есть занятые дни — заявку примем, но даты придётся согласовать.
                                </div>
                            </div>

                            <div x-show="submitted" x-cloak class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 mb-4">
                                Спасибо! Заявка на бронирование отправлена — менеджер свяжется с вами и подтвердит даты.
                            </div>

                            <div x-show="error" x-cloak class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 mb-4">
                                <span x-text="error"></span>
                            </div>

                            <form @submit.prevent="submit()" x-show="!submitted">
                                @csrf
                                <input type="text" x-model="form.name" required maxlength="255" placeholder="Ваше имя"
                                       class="block appearance-none border border-[#C6C6C6] w-full mb-3 text-sm p-3">

                                <input type="tel" x-model="form.phone" required maxlength="20" placeholder="Телефон"
                                       class="block appearance-none border border-[#C6C6C6] w-full mb-3 text-sm p-3">

                                <input type="email" x-model="form.email" maxlength="255" placeholder="Email (необязательно)"
                                       class="block appearance-none border border-[#C6C6C6] w-full mb-3 text-sm p-3">

                                <textarea x-model="form.comment" maxlength="2000" rows="3" placeholder="Комментарий: цель аренды, нужен ли шкипер"
                                          class="block appearance-none border border-[#C6C6C6] w-full mb-3 text-sm p-3"></textarea>

                                <div class="privacy flex gap-4 mb-4">
                                    <label class="custom-checkbox">
                                        <input type="checkbox" x-model="form.agreement" required/>
                                        <span class="checkbox-box shrink-0"></span>
                                        <div class="text-sm text-brand-gray-light">
                                            Я принимаю
                                            @if (trim(strip_tags((string) $terms, '<img>')) !== '')
                                                <a class="underline" href="#rental-terms">условия аренды</a> и
                                            @else
                                                условия аренды и
                                            @endif
                                            <a class="underline" href="/files/Политика_обработки_персональных_данных_1.pdf">политику обработки персональных данных</a>
                                        </div>
                                    </label>
                                </div>

                                <button type="submit" :disabled="loading"
                                        class="bg-[#2D92CE] text-white text-center w-full py-4 font-semibold hover:bg-[#0074CC] transition-colors"
                                        x-text="loading ? 'Отправка...' : 'Забронировать'"></button>

                                <p class="text-xs text-brand-gray-light mt-3">
                                    Бронирование подтверждает менеджер: оплата — после подтверждения дат.
                                </p>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- ===== Другие яхты ===== --}}
                @if ($others->isNotEmpty())
                    <div class="mt-12">
                        <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Другие яхты в аренду</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                            @foreach ($others as $other)
                                @include('partials.yacht-booking-card', [
                                    'yacht' => $other,
                                    'days' => 0,
                                    'from' => null,
                                    'to' => null,
                                ])
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- ===== Другие услуги ===== --}}
                <div class="mt-12">
                    <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Другие услуги</h2>
                    <x-service-cards :current="$type" />
                </div>
            </div>
        </section>
    </main>

    <script>
        /**
         * Календарь и форма бронирования одной яхты.
         *
         * Занятые дни и окна аренды считает сервер; здесь — выбор периода,
         * подсказка стоимости и отправка заявки.
         */
        document.addEventListener('alpine:init', () => {
            Alpine.data('yachtBooking', (config) => ({
                    windows: config.windows,
                    busy: config.busy,
                    start: config.start,
                    end: config.end,
                    hoverDate: null,
                    calMonth: 0,
                    calYear: 0,
                    loading: false,
                    submitted: false,
                    error: '',
                    form: {
                        name: config.user.name,
                        phone: config.user.phone,
                        email: config.user.email,
                        comment: '',
                        agreement: false,
                    },
                    weekdays: ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'],
                    monthNames: ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'],

                    init() {
                        // Открываем месяц выбранного заезда, иначе — ближайшего
                        // окна аренды, иначе текущий.
                        const anchor = this.start
                            || this.windows.map(w => w.start).sort()[0]
                            || this.today();
                        const date = new Date(anchor + 'T00:00:00');
                        this.calMonth = date.getMonth();
                        this.calYear = date.getFullYear();
                    },

                    today() {
                        return this.toKey(new Date());
                    },

                    toKey(date) {
                        return date.getFullYear() + '-'
                            + String(date.getMonth() + 1).padStart(2, '0') + '-'
                            + String(date.getDate()).padStart(2, '0');
                    },

                    get hasWindows() {
                        return this.windows.length > 0;
                    },

                    get monthLabel() {
                        return this.monthNames[this.calMonth] + ' ' + this.calYear;
                    },

                    prevMonth() {
                        if (this.calMonth === 0) { this.calMonth = 11; this.calYear--; } else { this.calMonth--; }
                    },

                    nextMonth() {
                        if (this.calMonth === 11) { this.calMonth = 0; this.calYear++; } else { this.calMonth++; }
                    },

                    /** free — день внутри окна аренды и не занят; busy — бронь или регата. */
                    statusFor(dateStr) {
                        if (this.busy.includes(dateStr)) return 'busy';
                        if (dateStr < this.today()) return 'none';
                        return this.windows.some(w => dateStr >= w.start && dateStr <= w.end) ? 'free' : 'none';
                    },

                    get days() {
                        const from = this.start;
                        const to = this.end || this.start;
                        if (!from || !to || to < from) return 0;
                        const diff = Math.round((new Date(to + 'T00:00:00') - new Date(from + 'T00:00:00')) / 86400000) + 1;
                        return diff > 0 ? diff : 0;
                    },

                    get daysLabel() {
                        const n = this.days;
                        const mod100 = n % 100;
                        const mod10 = n % 10;
                        let word = 'дней';
                        if (mod100 < 11 || mod100 > 14) {
                            if (mod10 === 1) word = 'день';
                            else if (mod10 >= 2 && mod10 <= 4) word = 'дня';
                        }
                        return n + ' ' + word;
                    },

                    /** Все ли дни периода свободны. */
                    isRangeFree(from, to) {
                        let cursor = new Date(from + 'T00:00:00');
                        const last = new Date(to + 'T00:00:00');
                        while (cursor <= last) {
                            if (this.statusFor(this.toKey(cursor)) !== 'free') return false;
                            cursor.setDate(cursor.getDate() + 1);
                        }
                        return true;
                    },

                    /**
                     * Весь ли выбранный период свободен.
                     *
                     * Кликом занятый период не выбрать, но даты приходят ещё из
                     * ссылки и из полей ввода — там проверять некому.
                     */
                    get rangeFree() {
                        return this.days === 0 || this.isRangeFree(this.start, this.end || this.start);
                    },

                    /** Цена суток по окну, покрывающему весь период; иначе — минимум по всем окнам. */
                    get priceEvent() {
                        const covering = this.days > 0
                            ? this.windows.filter(w => w.start <= this.start && w.end >= (this.end || this.start))
                            : this.windows;
                        const prices = covering.map(w => w.price_event).filter(p => p !== null && p > 0);
                        return prices.length ? Math.min(...prices) : null;
                    },

                    money(value) {
                        return new Intl.NumberFormat('ru-RU').format(Math.round(value)) + ' ₽';
                    },

                    formatDate(value) {
                        if (!value) return '';
                        const [y, m, d] = value.split('-');
                        return d + '.' + m + '.' + y;
                    },

                    /** Ручной ввод дат в полях: конец раньше начала подтягиваем к началу. */
                    normalize() {
                        if (this.start && this.end && this.end < this.start) {
                            this.end = this.start;
                        }
                    },

                    /** Сетка месяца: хвосты соседних месяцев добиваются до полных недель. */
                    get cells() {
                        const first = new Date(this.calYear, this.calMonth, 1);
                        const lead = (first.getDay() + 6) % 7;
                        const inMonth = new Date(this.calYear, this.calMonth + 1, 0).getDate();
                        const prevDays = new Date(this.calYear, this.calMonth, 0).getDate();
                        const cells = [];

                        for (let i = lead - 1; i >= 0; i--) cells.push({ day: prevDays - i, current: false });
                        for (let d = 1; d <= inMonth; d++) {
                            const dateStr = this.calYear + '-'
                                + String(this.calMonth + 1).padStart(2, '0') + '-'
                                + String(d).padStart(2, '0');
                            cells.push({ day: d, current: true, date: dateStr, status: this.statusFor(dateStr) });
                        }
                        let next = 1;
                        while (cells.length % 7 !== 0) cells.push({ day: next++, current: false });

                        return cells;
                    },

                    get effectiveEnd() {
                        if (this.end) return this.end;
                        if (this.start && this.hoverDate && this.hoverDate > this.start
                            && this.isRangeFree(this.start, this.hoverDate)) {
                            return this.hoverDate;
                        }
                        return null;
                    },

                    isSelected(cell) {
                        if (!cell.current || !this.start) return false;
                        const end = this.effectiveEnd;
                        if (!end) return cell.date === this.start;
                        return cell.date >= this.start && cell.date <= end;
                    },

                    cellClass(cell) {
                        if (!cell.current) return 'text-[#C6C6C6]';
                        let base;
                        if (cell.status === 'free') base = 'bg-[#BAD5C6] text-[#2E325C] cursor-pointer hover:brightness-95';
                        else if (cell.status === 'busy') base = 'bg-[#F4C9C6] text-[#2E325C] cursor-default';
                        else base = 'bg-white text-[#2E325C] cursor-default';
                        if (this.isSelected(cell)) base += ' ring-2 ring-inset ring-[#2D92CE] font-semibold';
                        return base;
                    },

                    hoverDay(cell) {
                        if (cell.current && this.start && !this.end && cell.status === 'free') {
                            this.hoverDate = cell.date;
                        }
                    },

                    selectDay(cell) {
                        if (!cell.current || cell.status !== 'free') return;

                        // Первый клик или новый выбор после готового периода — заезд.
                        if (!this.start || this.end) {
                            this.start = cell.date;
                            this.end = null;
                            this.hoverDate = null;
                            return;
                        }

                        // Клик раньше заезда — начинаем выбор с этой даты.
                        // Так же поступаем, если между датами есть занятые дни:
                        // такой период всё равно не подтвердить.
                        if (cell.date < this.start || ! this.isRangeFree(this.start, cell.date)) {
                            this.start = cell.date;
                            this.end = null;
                            this.hoverDate = null;
                            return;
                        }

                        this.end = cell.date;
                        this.hoverDate = null;
                    },

                    async submit() {
                        this.error = '';
                        this.loading = true;

                        try {
                            const response = await fetch(config.url, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': config.csrf,
                                },
                                body: JSON.stringify({
                                    name: this.form.name,
                                    phone: this.form.phone,
                                    email: this.form.email,
                                    comment: this.form.comment,
                                    agreement: this.form.agreement,
                                    desired_date: this.start,
                                    desired_date_end: this.end || this.start,
                                }),
                            });

                            if (!response.ok) {
                                const data = await response.json().catch(() => ({}));
                                const firstError = Object.values(data.errors ?? {})[0]?.[0];
                                throw new Error(firstError || data.message || 'Произошла ошибка при отправке.');
                            }

                            this.submitted = true;
                        } catch (err) {
                            this.error = err.message || 'Произошла ошибка при отправке. Попробуйте позже.';
                        } finally {
                            this.loading = false;
                        }
                    },
            }));
        });
    </script>

    <x-feedback-section></x-feedback-section>
</x-public-layout>
