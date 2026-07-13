<x-public-layout title="Каталог яхт - классы, характеристики и владельцы" description="Реестр парусных судов: технические паспорта, допуски, история выступлений и контакты владельцев для участия в гонках">
<x-breadcrumbs_page title="Яхты Ассоциации">
</x-breadcrumbs_page>
<x-hero-section title="Яхты Ассоциации"
desc="Список яхт класса Carter 30, зарегистрированных в Ассоциации и участвующих в регатах сезона."
bgImage="{{ asset('images/bg/yachts.webp') }}"
>
</x-hero-section>

<main x-data="{
    yacht_modal_open: false,
    rental_modal_open: false,
    selectedYacht: null,
    yachtsData: {{ $yachtsJson }},
    search: '',
    get filteredYachts() {
        const isCyrillic = (str) => /^[а-яё]/i.test((str || '').trim());
        return this.yachtsData.filter(yacht =>
            yacht.name.toLowerCase().includes(this.search.toLowerCase()) ||
            yacht.vfps_number.toLowerCase().includes(this.search.toLowerCase()) ||
            (yacht.owner && yacht.owner.name && yacht.owner.name.toLowerCase().includes(this.search.toLowerCase()))
        ).sort((a, b) => {
            const aCyr = isCyrillic(a.name);
            const bCyr = isCyrillic(b.name);
            if (aCyr !== bCyr) return aCyr ? -1 : 1;
            return a.name.localeCompare(b.name, 'ru');
        });
    },
    rentalForm: { name: '', phone: '', desired_date: '', desired_date_end: '', comment: '' },
    rentalLoading: false,
    rentalSubmitted: false,
    rentalError: '',
    openYachtModal(yacht) {
        this.selectedYacht = yacht;
        this.yacht_modal_open = true;
    },
    openRentalModal(date = null, dateEnd = null) {
        this.rentalForm = { name: '', phone: '', desired_date: date || '', desired_date_end: dateEnd || date || '', comment: '' };
        this.rentalSubmitted = false;
        this.rentalError = '';
        this.yacht_modal_open = false;
        this.rental_modal_open = true;
    },
    rentalDays() {
        const s = this.rentalForm.desired_date;
        const e = this.rentalForm.desired_date_end || s;
        if (!s || !e) return 0;
        const start = new Date(s + 'T00:00:00');
        const end = new Date(e + 'T00:00:00');
        const diff = Math.round((end - start) / 86400000) + 1;
        return diff > 0 ? diff : 0;
    },
    rentalTotal(price) {
        if (price === null || price === undefined) return null;
        return new Intl.NumberFormat('ru-RU').format(price * this.rentalDays()) + ' ₽';
    },
    async submitRental() {
        this.rentalError = '';
        this.rentalLoading = true;
        try {
            const url = '{{ url('yachts') }}/' + this.selectedYacht.id + '/rental-request';
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify(this.rentalForm),
            });
            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error(data.message || 'Произошла ошибка при отправке.');
            }
            this.rentalSubmitted = true;
            this.rentalForm = { name: '', phone: '', desired_date: '', desired_date_end: '', comment: '' };
        } catch (err) {
            this.rentalError = err.message || 'Произошла ошибка при отправке. Попробуйте позже.';
        } finally {
            this.rentalLoading = false;
        }
    }
}" class="main">
    <section class="md:py-12 py-4 reggata-list">
        <div class="container mx-auto">
            <div class="flex items-center flex-col md:flex-row justify-between mb-6">
                <h2 class="section-title a-font">Список яхт</h2>
                @guest
                <a href="#" @click.prevent="$dispatch('open-login-modal', { tab: 'register' })" class="bg-[#2D92CE] cursor-pointer text-white hover:bg-[#0074CC] py-2 px-4 transition-colors">Зарегистрировать яхту →</a>
                @else
                <a href="/user/yachts?action=create" class="bg-[#2D92CE] cursor-pointer text-white hover:bg-[#0074CC] py-2 px-4 transition-colors">Зарегистрировать яхту →</a>
                @endguest
            </div>
            <div class="searchbar bg-[#F8F8F8] mb-6">
                <input x-model="search" class="w-full pl-8 bg-[#F8F8F8] border-none" type="text" placeholder="Поиск">
            </div>
            <div class="reggata-list__items">
                <table class="w-full text-left border-collapse bg-[#F8F8F8]">
                    <thead class="text-sm lg:text-2xl">
                        <tr>
                            <th class="py-2 a-font text-center">Название</th>
                            <th class="py-2 a-font text-center">Парус №</th>
                            <th class="py-2 a-font text-center">Капитан</th>
                            <!--
                            <th class="py-2 a-font text-center text-2xl">Балл ORC</th>
                            <th class="py-2 a-font text-center text-2xl">Сертификат ORC</th>
                            -->
                            <th class="py-2 a-font text-center"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="yacht in filteredYachts" :key="yacht.id">
                        <tr class="border-t text-[10px]  text-sm lg:text-2xl">
                            <td data-label="Название" class="py-2 text-center" x-text="yacht.name"></td>
                            <td data-label="Парус №" class="py-2 text-center" x-text="yacht.vfps_number"></td>
                            <td data-label="Капитан" class="py-2 text-center">
                                <template x-if="yacht.owner?.id && yacht.owner?.name && yacht.owner.name !== '—'">
                                    <button type="button" class="text-[#2D92CE] hover:underline cursor-pointer" @click="Livewire.dispatch('open-user-card', { userId: yacht.owner.id })" x-text="yacht.owner.name"></button>
                                </template>
                                <template x-if="!(yacht.owner?.id && yacht.owner?.name && yacht.owner.name !== '—')">
                                    <span x-text="yacht.owner?.name || '—'"></span>
                                </template>
                            </td>
                            <!--<td data-label="Балл ORC" class="py-2 text-center">—</td>
                            <td data-label="Сертификат ORC" class="py-2 text-center">
                                <template x-if="yacht.has_orc_cert">
                                    <span class="">Есть</span>
                                </template>
                                <template x-if="!yacht.has_orc_cert">
                                    <span class="">Нет</span>
                                </template>
                            </td>
                            -->
                            <td class="py-2 text-center">
                                <a href="#" @click.prevent="openYachtModal(yacht)" class="text-[#2D92CE] font-semibold hover:underline [&>span]:hidden md:[&>span]:inline">Подробнее  <span>→</span></a>
                            </td>
                        </tr>
                        </template>
                        <template x-if="filteredYachts.length === 0">
                        <tr>
                            <td colspan="6" class="py-8 text-center text-gray-500">Яхты не найдены</td>
                        </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($yachts->hasPages())
                @php
                    $currentPage = $yachts->currentPage();
                    $lastPage = $yachts->lastPage();
                    $pages = [];

                    // Always show first page, last page, and pages around current
                    foreach (range(1, $lastPage) as $p) {
                        if ($p <= 1 || $p >= $lastPage || ($p >= $currentPage - 2 && $p <= $currentPage + 2)) {
                            $pages[] = $p;
                        }
                    }
                    $pages = array_unique($pages);
                    sort($pages);
                @endphp
                <div class="pagination flex justify-center mt-10 gap-2">
                    @if($yachts->onFirstPage())
                        <button class="back opacity-50 cursor-default"></button>
                    @else
                        <a href="{{ $yachts->previousPageUrl() }}" class="back"></a>
                    @endif

                    @php $prevPage = null; @endphp
                    @foreach($pages as $page)
                        @if($prevPage !== null && $page - $prevPage > 1)
                            <span class="px-4 py-2 text-lg text-[#2E325C]">...</span>
                        @endif
                        @if($page == $currentPage)
                            <button class="px-4 py-2 text-lg text-[#2D92CE] font-medium border-b border-[#2D92CE]">{{ $page }}</button>
                        @else
                            <a href="{{ $yachts->url($page) }}" class="px-4 py-2 text-lg text-[#2E325C]">{{ $page }}</a>
                        @endif
                        @php $prevPage = $page; @endphp
                    @endforeach

                    @if($yachts->hasMorePages())
                        <a href="{{ $yachts->nextPageUrl() }}" class="next"></a>
                    @else
                        <button class="next opacity-50 cursor-default"></button>
                    @endif
                </div>
            @endif
        </div>
    </section>

    {{-- Модальное окно для подробной информации о яхте --}}
    <div x-show="yacht_modal_open"
        x-cloak
        @keydown.escape.window="yacht_modal_open = false"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 team-modal">
        <div @click.away="yacht_modal_open = false" class="relative p-3 md:p-6 max-w-[1000px] max-h-[80vh] overflow-y-auto bg-white gap-6"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
        >
            <template x-if="selectedYacht">
            <div>
                <div class="bg-[#F8F8F8] p-6 mb-8">
                    <div class="flex mb-6 justify-between items-center">
                        <h3 class="text-3xl a-font" x-text="selectedYacht.name"></h3>
                        <div class="close">
                            <button @click="yacht_modal_open = false" class="text-2xl font-bold">{!! file_get_contents(public_path('images/icons/close.svg')) !!}</button>
                        </div>
                    </div>
                    <p class="mb-6" x-text="'Яхта «' + selectedYacht.name + '» зарегистрирована в Ассоциации Carter Pro и принимает участие в регатах сезона.'"></p>
                    <div class="flex md:gap-24 flex-col md:flex-row">
                        <table>
                            <tr>
                                <td class="font-semibold py-2 w-64">Парус №</td>
                                <td x-text="selectedYacht.vfps_number"></td>
                            </tr>
                            <tr>
                                <td class="font-semibold py-2 w-64">Класс:</td>
                                <td x-text="selectedYacht.class"></td>
                            </tr>
                            <tr>
                                <td class="font-semibold py-2 w-64">Участие в регатах:</td>
                                <td x-text="selectedYacht.participation_count + ' регат(ы)'"></td>
                            </tr>
                            <tr>
                                <td class="font-semibold py-2 w-64">Регион базирования:</td>
                                <td x-text="selectedYacht.home_region"></td>
                            </tr>
                        </table>
                        <table>
                            <tr>
                                <td class="font-semibold py-2 w-64">Номер ГИМС:</td>
                                <td x-text="selectedYacht.gims_number"></td>
                            </tr>
                            <tr>
                                <td class="font-semibold py-2 w-64">Сертификат ORC:</td>
                                <td>
                                    <template x-if="selectedYacht.has_orc_cert">
                                        <span class="">Есть</span>
                                    </template>
                                    <template x-if="!selectedYacht.has_orc_cert">
                                        <span class="">Нет</span>
                                    </template>
                                </td>
                            </tr>
                            <tr>
                                <td class="font-semibold py-2 w-64">Балл ORC:</td>
                                <td>—</td>
                            </tr>
                            <tr>
                                <td class="font-semibold py-2 w-64">Место стоянки:</td>
                                <td x-text="selectedYacht.mooring_place"></td>
                            </tr>
                        </table>
                    </div>
                </div>
                {{-- Календарь доступности яхты --}}
                <h3 class="text-3xl a-font">Доступность</h3>
                <div class="mt-8 bg-[#F8F8F8] p-4 md:p-6"
                    x-data="{
                        calMonth: 0,
                        calYear: 2026,
                        rangeStart: null,
                        rangeEnd: null,
                        hoverDate: null,
                        weekdays: ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'],
                        monthNames: ['Январь','Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'],
                        init() {
                            const rentals = (selectedYacht.rentals || []).filter(r => r.start);
                            let d = new Date();
                            if (rentals.length) {
                                d = rentals.map(r => new Date(r.start + 'T00:00:00')).sort((a, b) => a - b)[0];
                            }
                            this.calMonth = d.getMonth();
                            this.calYear = d.getFullYear();
                            this.$watch('selectedYacht', () => { this.rangeStart = null; this.rangeEnd = null; this.hoverDate = null; });
                        },
                        get monthLabel() { return this.monthNames[this.calMonth] + ' ' + this.calYear; },
                        get hasAvailability() {
                            return (selectedYacht.rentals || []).some(r => r.start && r.end);
                        },
                        prevMonth() {
                            if (this.calMonth === 0) { this.calMonth = 11; this.calYear--; }
                            else { this.calMonth--; }
                        },
                        nextMonth() {
                            if (this.calMonth === 11) { this.calMonth = 0; this.calYear++; }
                            else { this.calMonth++; }
                        },
                        statusFor(dateStr) {
                            if ((selectedYacht.booked_dates || []).includes(dateStr)) return 'busy';
                            for (const r of (selectedYacht.rentals || [])) {
                                if (r.start && r.end && dateStr >= r.start && dateStr <= r.end) {
                                    return 'free';
                                }
                            }
                            return 'none';
                        },
                        get days() {
                            const first = new Date(this.calYear, this.calMonth, 1);
                            let lead = (first.getDay() + 6) % 7;
                            const inMonth = new Date(this.calYear, this.calMonth + 1, 0).getDate();
                            const prevDays = new Date(this.calYear, this.calMonth, 0).getDate();
                            const cells = [];
                            for (let i = lead - 1; i >= 0; i--) cells.push({ day: prevDays - i, current: false });
                            for (let d = 1; d <= inMonth; d++) {
                                const dateStr = this.calYear + '-' + String(this.calMonth + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
                                cells.push({ day: d, current: true, date: dateStr, status: this.statusFor(dateStr) });
                            }
                            let next = 1;
                            while (cells.length % 7 !== 0) cells.push({ day: next++, current: false });
                            return cells;
                        },
                        rangeValid(startStr, endStr) {
                            let d = new Date(startStr + 'T00:00:00');
                            const end = new Date(endStr + 'T00:00:00');
                            while (d <= end) {
                                const s = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
                                const st = this.statusFor(s);
                                if (st !== 'free') return false;
                                d.setDate(d.getDate() + 1);
                            }
                            return true;
                        },
                        get effectiveEnd() {
                            if (this.rangeEnd) return this.rangeEnd;
                            if (this.rangeStart && this.hoverDate && this.hoverDate > this.rangeStart && this.rangeValid(this.rangeStart, this.hoverDate)) {
                                return this.hoverDate;
                            }
                            return null;
                        },
                        isSelected(cell) {
                            if (!cell.current || !this.rangeStart) return false;
                            const end = this.effectiveEnd;
                            if (!end) return cell.date === this.rangeStart;
                            return cell.date >= this.rangeStart && cell.date <= end;
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
                            if (cell.current && this.rangeStart && !this.rangeEnd && cell.status === 'free') {
                                this.hoverDate = cell.date;
                            }
                        },
                        selectDay(cell) {
                            if (!cell.current || cell.status !== 'free') return;
                            // Первый клик (или новый выбор после завершённого диапазона) — задаём начало
                            if (!this.rangeStart || this.rangeEnd) {
                                this.rangeStart = cell.date;
                                this.rangeEnd = null;
                                this.hoverDate = null;
                                return;
                            }
                            // Клик раньше начала — начинаем выбор заново с этой даты
                            if (cell.date < this.rangeStart) {
                                this.rangeStart = cell.date;
                                this.rangeEnd = null;
                                return;
                            }
                            // Внутри диапазона есть занятые/недоступные дни — начинаем заново
                            if (cell.date !== this.rangeStart && !this.rangeValid(this.rangeStart, cell.date)) {
                                this.rangeStart = cell.date;
                                this.rangeEnd = null;
                                return;
                            }
                            // Завершаем диапазон и открываем заявку
                            this.rangeEnd = cell.date;
                            openRentalModal(this.rangeStart, this.rangeEnd);
                        }
                    }">
                    <p class="mb-4" x-show="hasAvailability">Выберите даты начала и окончания аренды, кликнув по свободным дням.</p>
                    <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                        <h6 class="a-font text-xl md:text-2xl text-[#2E325C]" x-text="monthLabel"></h6>
                        <div class="flex items-center gap-4 flex-wrap text-sm">
                            <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-[#BAD5C6] inline-block"></span> Свободно</span>
                            <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-[#F4C9C6] inline-block"></span> Занято</span>
                            <div class="flex items-center gap-1">
                                <button type="button" @click="prevMonth()" class="w-8 h-8 flex items-center justify-center text-[#2D92CE] text-xl hover:bg-white transition-colors">‹</button>
                                <button type="button" @click="nextMonth()" class="w-8 h-8 flex items-center justify-center text-[#2D92CE] text-xl hover:bg-white transition-colors">›</button>
                            </div>
                        </div>
                    </div>
                    <div class="relative" :class="{ 'opacity-60': !hasAvailability }">
                        <div class="grid grid-cols-7 mb-1">
                            <template x-for="wd in weekdays" :key="wd">
                                <div class="text-center font-semibold text-[#2E325C] py-2" x-text="wd"></div>
                            </template>
                        </div>
                        <div class="grid grid-cols-7 gap-px bg-[#EAEAEA] border border-[#EAEAEA]" @mouseleave="hoverDate = null">
                            <template x-for="(cell, i) in days" :key="i">
                                <div class="h-12 md:h-16 flex items-center justify-center transition-all"
                                        :class="cellClass(cell)"
                                        @click="selectDay(cell)"
                                        @mouseenter="hoverDay(cell)"
                                        x-text="cell.day"></div>
                            </template>
                        </div>
                        {{-- Перечёркиваем календарь, если яхта не сдаётся / нет арендных дат --}}
                        <div x-show="!hasAvailability" class="absolute inset-0 z-10 pointer-events-none flex items-center justify-center">
                            <svg class="absolute inset-0 w-full h-full" preserveAspectRatio="none" viewBox="0 0 100 100">
                                <line x1="0" y1="0" x2="100" y2="100" stroke="#C6455E" stroke-width="1.5" vector-effect="non-scaling-stroke"></line>
                                <line x1="100" y1="0" x2="0" y2="100" stroke="#C6455E" stroke-width="1.5" vector-effect="non-scaling-stroke"></line>
                            </svg>
                            <span class="relative bg-white/90 text-[#2E325C] a-font text-lg md:text-xl px-4 py-2">Яхта пока не сдаётся в аренду</span>
                        </div>
                    </div>
                    <p class="mt-3 text-sm text-brand-gray" x-show="hasAvailability && rangeStart && !rangeEnd">
                        Начало: <span class="font-semibold" x-text="rangeStart"></span>. Выберите дату окончания аренды.
                    </p>
                </div>
                <h3 class="text-3xl a-font mb-6">Параметры яхты</h3>
                <div class="overflow-y-auto relative custom-scroll mb-8">
                    <table class="w-full border-collapse bg-[#F8F8F8]">
                        <thead>
                            <tr class="text-2xl text-[#2E325C] border-b border-[#EAEAEA] sticky top-0 bg-[#F8F8F8]">
                                <th class="pt-2 pb-2 text-center font-medium a-font">Параметр</th>
                                <th class="pt-2 pb-2 text-center font-medium a-font">Значение</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-center font-medium">
                            <template x-for="(p, i) in selectedYacht.params" :key="i">
                                <tr class="hover:bg-white transition-colors border-b border-[#EAEAEA]">
                                    <td class="py-3" x-text="p.label"></td>
                                    <td class="py-3" x-text="p.value"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="mb-8" x-show="selectedYacht.options && selectedYacht.options.length > 0">
                    <h3 class="text-3xl a-font mb-6">Опции яхты</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-x-8 gap-y-2 bg-[#F8F8F8] p-6">
                        <template x-for="(o, i) in selectedYacht.options" :key="i">
                            <div class="flex">
                                <span class="font-semibold py-2 w-64" x-text="o.label + ':'"></span>
                                <span class="py-2" x-text="o.value"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="mb-8" x-show="selectedYacht.suitable_for && selectedYacht.suitable_for.length > 0">
                    <h3 class="text-3xl a-font">Для чего подходит яхта</h3>
                    <div class="flex flex-col gap-3 p-6 pl-0">
                        <template x-for="(item, i) in selectedYacht.suitable_for" :key="i">
                            <div class="flex items-center gap-3">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" class="shrink-0" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="3" y="3" width="18" height="18" rx="0" stroke="#2E325C" stroke-width="1"/>
                                    <path d="M7 12l3 3 7-7" stroke="#2D92CE" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span x-text="item"></span>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Galleries --}}
                <div class="mb-8"
                     x-show="(selectedYacht.gallery && selectedYacht.gallery.length > 0) || (selectedYacht.interior_gallery && selectedYacht.interior_gallery.length > 0)"
                     x-data="{ galleryTab: selectedYacht.gallery && selectedYacht.gallery.length > 0 ? 'exterior' : 'interior' }">
                    <div class="flex gap-8 mb-6">
                        <button type="button" @click="galleryTab = 'exterior'"
                                x-show="selectedYacht.gallery && selectedYacht.gallery.length > 0"
                                :class="galleryTab === 'exterior' ? 'text-white bg-[#2D92CE]' : 'bg-[#F8F8F8]'"
                                class="text-lg p-3 font-medium -mb-px transition-colors">Экстерьер</button>
                        <button type="button" @click="galleryTab = 'interior'"
                                x-show="selectedYacht.interior_gallery && selectedYacht.interior_gallery.length > 0"
                                :class="galleryTab === 'interior' ? 'text-white bg-[#2D92CE]' : 'bg-[#F8F8F8]'"
                                class="text-lg p-3 font-medium -mb-px transition-colors">Интерьер</button>
                    </div>

                    {{-- Gallery --}}
                    <div x-show="galleryTab === 'exterior'" x-data="{ activeIndex: 0, lightboxOpen: false }">
                        <div class="relative mb-4">
                            <img :src="selectedYacht.gallery[activeIndex]?.url" :alt="selectedYacht.gallery[activeIndex]?.name"
                                 class="w-full aspect-video object-contain cursor-pointer" @click="lightboxOpen = true">
                            <template x-if="selectedYacht.gallery.length > 1">
                                <div>
                                    <button @click="activeIndex = activeIndex > 0 ? activeIndex - 1 : selectedYacht.gallery.length - 1"
                                            class="absolute rounded-full left-2 top-1/2 -translate-y-1/2 bg-brand-blue hover:bg-brand-blue text-white w-10 h-10 flex items-center justify-center text-3xl transition-colors pb-1.5">‹</button>
                                    <button @click="activeIndex = activeIndex < selectedYacht.gallery.length - 1 ? activeIndex + 1 : 0"
                                            class="absolute rounded-full right-2 top-1/2 -translate-y-1/2 bg-brand-blue hover:bg-brand-blue text-white w-10 h-10 flex items-center justify-center text-3xl transition-colors pb-1.5">›</button>
                                </div>
                            </template>
                        </div>

                        <div class="flex gap-2 overflow-x-auto pb-1" x-show="selectedYacht.gallery.length > 1">
                            <template x-for="(img, idx) in selectedYacht.gallery" :key="idx">
                                <img :src="img.thumbnail || img.url" :alt="img.name" @click="activeIndex = idx"
                                     :class="idx === activeIndex ? 'ring-2 ring-[#2D92CE] opacity-100' : 'opacity-60 hover:opacity-100'"
                                     class="w-20 h-20 object-cover cursor-pointer shrink-0 transition-opacity">
                            </template>
                        </div>

                        {{-- Lightbox --}}
                        <template x-teleport="body">
                            <div x-show="lightboxOpen"
                                 x-cloak
                                 @keydown.window.escape="lightboxOpen = false"
                                 style="position: fixed; inset: 0; z-index: 100000; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.9); padding: 1rem;"
                                 @click.self="lightboxOpen = false">
                                <button @click="lightboxOpen = false"
                                        class="absolute top-4 right-4 text-white text-4xl font-bold hover:opacity-70 transition-opacity">
                                    {!! file_get_contents(public_path('images/icons/close.svg')) !!}
                                </button>
                                <button @click="activeIndex = activeIndex > 0 ? activeIndex - 1 : selectedYacht.gallery.length - 1"
                                        class="absolute left-4 top-1/2 -translate-y-1/2 text-white text-5xl hover:opacity-70 transition-opacity">‹</button>
                                <img :src="selectedYacht.gallery[activeIndex]?.url"
                                     :alt="selectedYacht.gallery[activeIndex]?.name"
                                     class="max-w-full max-h-[85vh] object-contain mx-auto">
                                <button @click="activeIndex = activeIndex < selectedYacht.gallery.length - 1 ? activeIndex + 1 : 0"
                                        class="absolute right-4 top-1/2 -translate-y-1/2 text-white text-5xl hover:opacity-70 transition-opacity">›</button>
                                <div class="absolute bottom-4 text-white text-sm" x-text="`${activeIndex + 1} / ${selectedYacht.gallery.length}`"></div>
                            </div>
                        </template>
                    </div>

                    {{-- Interior Gallery --}}
                    <div x-show="galleryTab === 'interior'" x-data="{ activeIndex: 0, lightboxOpen: false }">
                        <div class="relative mb-4">
                            <img :src="selectedYacht.interior_gallery[activeIndex]?.url" :alt="selectedYacht.interior_gallery[activeIndex]?.name"
                                 class="w-full aspect-video object-contain cursor-pointer" @click="lightboxOpen = true">
                            <template x-if="selectedYacht.interior_gallery.length > 1">
                                <div>
                                    <button @click="activeIndex = activeIndex > 0 ? activeIndex - 1 : selectedYacht.interior_gallery.length - 1"
                                            class="absolute rounded-full left-2 top-1/2 -translate-y-1/2 bg-brand-blue hover:bg-brand-blue text-white w-10 h-10 flex items-center justify-center text-3xl transition-colors pb-1.5">‹</button>
                                    <button @click="activeIndex = activeIndex < selectedYacht.interior_gallery.length - 1 ? activeIndex + 1 : 0"
                                            class="absolute rounded-full right-2 top-1/2 -translate-y-1/2 bg-brand-blue hover:bg-brand-blue text-white w-10 h-10 flex items-center justify-center text-3xl transition-colors pb-1.5">›</button>
                                </div>
                            </template>
                        </div>

                        <div class="flex gap-2 overflow-x-auto pb-1" x-show="selectedYacht.interior_gallery.length > 1">
                            <template x-for="(img, idx) in selectedYacht.interior_gallery" :key="idx">
                                <img :src="img.thumbnail || img.url" :alt="img.name" @click="activeIndex = idx"
                                     :class="idx === activeIndex ? 'ring-2 ring-[#2D92CE] opacity-100' : 'opacity-60 hover:opacity-100'"
                                     class="w-20 h-20 object-cover cursor-pointer shrink-0 transition-opacity">
                            </template>
                        </div>

                        {{-- Lightbox --}}
                        <template x-teleport="body">
                            <div x-show="lightboxOpen"
                                 x-cloak
                                 @keydown.window.escape="lightboxOpen = false"
                                 style="position: fixed; inset: 0; z-index: 100000; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.9); padding: 1rem;"
                                 @click.self="lightboxOpen = false">
                                <button @click="lightboxOpen = false"
                                        class="absolute top-4 right-4 text-white text-4xl font-bold hover:opacity-70 transition-opacity">
                                    {!! file_get_contents(public_path('images/icons/close.svg')) !!}
                                </button>
                                <button @click="activeIndex = activeIndex > 0 ? activeIndex - 1 : selectedYacht.interior_gallery.length - 1"
                                        class="absolute left-4 top-1/2 -translate-y-1/2 text-white text-5xl hover:opacity-70 transition-opacity">‹</button>
                                <img :src="selectedYacht.interior_gallery[activeIndex]?.url"
                                     :alt="selectedYacht.interior_gallery[activeIndex]?.name"
                                     class="max-w-full max-h-[85vh] object-contain mx-auto">
                                <button @click="activeIndex = activeIndex < selectedYacht.interior_gallery.length - 1 ? activeIndex + 1 : 0"
                                        class="absolute right-4 top-1/2 -translate-y-1/2 text-white text-5xl hover:opacity-70 transition-opacity">›</button>
                                <div class="absolute bottom-4 text-white text-sm" x-text="`${activeIndex + 1} / ${selectedYacht.interior_gallery.length}`"></div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="mb-8" x-show="selectedYacht.documents.length > 0">
                    <h3 class="text-3xl a-font mb-6">Документы</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <template x-for="doc in selectedYacht.documents">
                            <!--<div class="bg-[#F8F8F8] flex gap-4 hover:shadow-md transition-shadow cursor-pointer p-4">
                                <div class="max-w-16">
                                    <img class="w-full" src="{{ asset('images/icons/pdf.png') }}" alt="">
                                </div>
                                <div>
                                    <div class="text-[#2E325C] text-lg font-semibold mb-4" x-text="doc.title"></div>
                                    <a :href="doc.url" class="text-[#2E325C] text-lg font-semibold flex gap-4 items-center">
                                        <img src="{{ asset('images/icons/download.svg') }}" alt="">
                                        <span>Скачать PDF</span>
                                    </a>
                                </div>
                            </div>-->
                            <div class="bg-[#F8F8F8] flex gap-4 hover:shadow-md transition-shadow cursor-pointer p-4">
                                <div class="max-w-10 md:max-w-16">
                                    <img class="w-full" src="{{ asset('images/icons/pdf.png') }}" alt="">
                                </div>
                                <div class="">
                                    <div class="text-[#2E325C] text-sm md:text-lg font-semibold mb-4" x-text='doc.title'></div>
                                    <div class="text-brand-gray-light font-medium mb-4 text-xs md:text-base" x-text='doc.desc'></div>
                                    <a x-bind:href="doc.url" class="text-[#2E325C] text-sm md:text-lg font-semibold flex gap-4 items-center"><img src="{{ asset('images/icons/download.svg') }}" alt=""> <span>Скачать PDF</span></a>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <script>
                    function participation_status(status, regattaFinished) {
                        if (status === 'submitted') {
                            return '<div class="bg-[#A88C5833] px-3 py-1 text-[#A88C58] inline-block font-semibold max-w-[150px] w-full">Заявка подана</div>';
                        } else if (status === 'approved') {
                            return regattaFinished
                                ? '<div class="bg-[#2D92CE33] px-3 py-1 text-[#2D92CE] inline-block font-semibold max-w-[150px] w-full">Участвовала</div>'
                                : '<div class="bg-[#2D92CE33] px-3 py-1 text-[#2D92CE] inline-block font-semibold max-w-[150px] w-full">Участвует</div>';
                        } else if (status === 'completed' || status === 'finished') {
                            return '<div class="bg-[#15794933] px-3 py-1 text-[#157949] inline-block font-semibold max-w-[150px] w-full">Завершено</div>';
                        } else if (status === 'rejected') {
                            return '<div class="bg-[#F2484233] px-3 py-1 text-[#F24842] inline-block font-semibold max-w-[150px] w-full">Отклонена</div>';
                        } else {
                            return '<div class="bg-[#F8F8F8] px-3 py-1 text-[#666] inline-block font-semibold max-w-[150px] w-full">' + status + '</div>';
                        }
                    }
                </script>

                <div class="participation mb-8" x-show="selectedYacht.participation.length > 0">
                    <div class="participation-header flex items-center justify-between mb-6">
                        <h5 class="a-font text-lg md:text-3xl">Участие в регатах</h5>
                        <a href="#" class="text-[#2E325C] text-lg font-semibold gap-2 items-center hidden md:flex">
                            <img src="{{ asset('images/icons/download.svg') }}" alt="">
                            <span>Скачать историю участия</span>
                        </a>
                    </div>
                    <div class="overflow-y-auto max-h-[180px] relative custom-scroll responsive-table">
                        <table class="w-full border-collapse bg-[#F8F8F8]">
                            <thead>
                                <tr class="text-2xl text-[#2E325C] border-b border-[#EAEAEA] sticky top-0 bg-[#F8F8F8]">
                                    <th class="pt-2 pb-2 text-center font-medium a-font">Регата</th>
                                    <th class="pt-2 pb-2 text-center font-medium a-font">Дата</th>
                                    <th class="pt-2 pb-2 text-center font-medium a-font">Команда</th>
                                    <th class="pt-2 pb-2 text-center font-medium a-font">Статус</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-center font-medium">
                                <template x-for="(p, i) in selectedYacht.participation" :key="i">
                                    <tr class="hover:bg-white transition-colors border-b border-[#EAEAEA]">
                                        <td data-label="Регата" class="py-3" x-text="p.regatta"></td>
                                        <td data-label="Дата" class="py-3" x-text="p.date_event"></td>
                                        <td data-label="Команда" class="py-3" x-text="p.team"></td>
                                        <td data-label="Статус" class="py-3" x-html="participation_status(p.status, p.regatta_finished)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        <a href="#" class="text-[#2E325C] text-lg font-semibold gap-2 block text-center items-center flex md:hidden">
                            <img src="{{ asset('images/icons/download.svg') }}" alt="">
                            <span>Скачать историю участия</span>
                        </a>
                    </div>

                </div>

                {{-- Аренда яхты: список регат со стоимостью --}}
                <div class="rent mb-8" x-show="selectedYacht.for_rent && selectedYacht.rentals.length > 0">
                    <div class="rent-header flex items-center justify-between mb-6">
                        <h5 class="a-font text-lg md:text-3xl">Аренда яхты</h5>
                        <div class="bg-[#2D92CE33] px-3 py-1 text-[#2D92CE] inline-block font-semibold">Доступна для аренды</div>
                    </div>
                    <div class="overflow-y-auto relative custom-scroll responsive-table">
                        <table class="w-full border-collapse bg-[#F8F8F8]">
                            <thead>
                                <tr class="text-2xl text-[#2E325C] border-b border-[#EAEAEA] sticky top-0 bg-[#F8F8F8]">
                                    <th class="pt-2 pb-2 text-center font-medium a-font">Период</th>
                                    <th class="pt-2 pb-2 text-center font-medium a-font">Мероприятия</th>
                                    <th class="pt-2 pb-2 text-center font-medium a-font">Профкоманды</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-center font-medium">
                                <template x-for="(r, i) in selectedYacht.rentals" :key="i">
                                    <tr class="hover:bg-white transition-colors border-b border-[#EAEAEA]">
                                        <td data-label="Период" class="py-3" x-text="r.date_range"></td>
                                        <td data-label="Мероприятия" class="py-3 font-semibold text-[#2D92CE]" x-text="r.price_event"></td>
                                        <td data-label="Профкоманды" class="py-3 font-semibold text-[#2D92CE]" x-text="r.price_pro"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <!--
                    <div class="flex justify-end mt-6">
                        <button type="button" @click="openRentalModal()"
                                class="bg-[#2D92CE] text-white hover:bg-[#0074CC] py-3 px-6 font-semibold transition-colors">
                            Запросить аренду →
                        </button>
                    </div>
                    -->
                </div>
            </div>
            </template>
        </div>
    </div>

    {{-- Модальное окно: запрос аренды яхты --}}
    <div x-show="rental_modal_open"
        x-cloak
        @keydown.escape.window="rental_modal_open = false"
        class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/50 team-modal">
        <template x-if="selectedYacht">
        <div @click.away="rental_modal_open = false"
            class="relative p-4 md:p-8 w-full max-w-[820px] max-h-[90vh] overflow-y-auto bg-white"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100">
            <div class="flex items-center justify-between mb-6">
                <h3 class="a-font text-2xl md:text-4xl text-[#2E325C]">Запрос аренды</h3>
                <button type="button" @click="rental_modal_open = false" class="text-2xl font-bold">
                    {!! file_get_contents(public_path('images/icons/close.svg')) !!}
                </button>
            </div>

            {{-- Успех --}}
            <div x-show="rentalSubmitted" x-cloak
                 class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 mb-4" role="alert">
                <span class="block">Спасибо! Ваш запрос на аренду отправлен. Мы свяжемся с вами в ближайшее время.</span>
            </div>

            {{-- Ошибка --}}
            <div x-show="rentalError" x-cloak
                 class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 mb-4" role="alert">
                <span class="block" x-text="rentalError"></span>
            </div>

            <form @submit.prevent="submitRental()" x-show="!rentalSubmitted">
                <div class="mb-5">
                    <label class="block text-brand-gray mb-2">Имя</label>
                    <input type="text" x-model="rentalForm.name" required maxlength="255"
                           placeholder="Введите имя"
                           class="w-full bg-[#F8F8F8] border-none px-4 py-4">
                </div>
                <div class="mb-5">
                    <label class="block text-brand-gray mb-2">Телефон</label>
                    <input type="tel" x-model="rentalForm.phone" required maxlength="20"
                           x-mask="+7 (999) 999-99-99" placeholder="+ 7 (000)-000-00-00"
                           class="w-full bg-[#F8F8F8] border-none px-4 py-4">
                </div>
                <div class="mb-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-brand-gray mb-2">Дата (с)</label>
                        <input type="date" x-model="rentalForm.desired_date" readonly
                               class="w-full bg-[#F8F8F8] border-none px-4 py-4">
                    </div>
                    <div>
                        <label class="block text-brand-gray mb-2">Дата (по)</label>
                        <input type="date" x-model="rentalForm.desired_date_end" readonly
                               class="w-full bg-[#F8F8F8] border-none px-4 py-4">
                    </div>
                </div>
                <div class="mb-6">
                    <label class="block text-brand-gray mb-2">Комментарий</label>
                    <textarea x-model="rentalForm.comment" maxlength="2000" rows="4"
                              placeholder="Введите комментарий"
                              class="w-full bg-[#F8F8F8] border-none px-4 py-4 resize-none"></textarea>
                </div>

                {{-- Стоимость аренды --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6"
                     x-show="selectedYacht.rentals && selectedYacht.rentals.length > 0">
                    <div class="bg-[#F8F8F8] p-5">
                        <div class="text-brand-gray mb-2">Для мероприятий</div>
                        <div class="text-2xl md:text-3xl font-bold text-[#2E325C]" x-text="selectedYacht.rentals[0]?.price_event"></div>
                    </div>
                    <div class="bg-[#F8F8F8] p-5">
                        <div class="text-brand-gray mb-2">Для профессиональных команд</div>
                        <div class="text-2xl md:text-3xl font-bold text-[#2E325C]" x-text="selectedYacht.rentals[0]?.price_pro"></div>
                    </div>
                </div>

                {{-- Итоговая сумма аренды --}}
                <div class="bg-[#2D92CE33] p-5 mb-6"
                     x-show="selectedYacht.rentals && selectedYacht.rentals.length > 0 && rentalDays() > 0">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-brand-gray">Количество дней</span>
                        <span class="font-semibold text-[#2E325C]" x-text="rentalDays()"></span>
                    </div>
                    <div class="flex items-center justify-between mb-2"
                         x-show="selectedYacht.rentals[0]?.price_event_raw !== null && selectedYacht.rentals[0]?.price_event_raw !== undefined">
                        <span class="text-brand-gray">Итого (мероприятия)</span>
                        <span class="text-xl md:text-2xl font-bold text-[#2E325C]" x-text="rentalTotal(selectedYacht.rentals[0]?.price_event_raw)"></span>
                    </div>
                    <div class="flex items-center justify-between"
                         x-show="selectedYacht.rentals[0]?.price_pro_raw !== null && selectedYacht.rentals[0]?.price_pro_raw !== undefined">
                        <span class="text-brand-gray">Итого (профкоманды)</span>
                        <span class="text-xl md:text-2xl font-bold text-[#2E325C]" x-text="rentalTotal(selectedYacht.rentals[0]?.price_pro_raw)"></span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <button type="submit" :disabled="rentalLoading"
                            class="bg-[#2D92CE] text-white hover:bg-[#0074CC] py-4 font-semibold transition-colors"
                            x-text="rentalLoading ? 'Отправка...' : 'Отправить запрос'"></button>
                    <button type="button" @click="rental_modal_open = false"
                            class="bg-[#F8F8F8] text-[#2E325C] hover:bg-[#EAEAEA] py-4 font-semibold transition-colors">
                        Отменить
                    </button>
                </div>
            </form>
        </div>
        </template>
    </div>

</main>

<x-feedback-section>

</x-feedback-section>
</x-public-layout>
