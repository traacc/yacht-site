{{-- resources/views/livewire/regattas-calendar.blade.php --}}
{{-- ВАЖНО: у Livewire-компонента должен быть ОДИН корневой элемент.
     <style> и <script> держим ВНУТРИ корневого <div>, иначе Livewire
     примет за корень первый тег (<style>) и не будет обновлять календарь. --}}
<div x-data="regattaCalendar()" data-current-month="{{ now()->format('n') - 1 }}" class="py-12 bg-brand-light">
    <style>
        .slides   { transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .page-dot { transition: width 0.3s ease, background-color 0.3s ease; }
        .slider-dragging { transition: none !important; cursor: grabbing !important; user-select: none; }
        .slider-grab    { cursor: grab; }
        .slider-grab:active { cursor: grabbing; }
    </style>
    <div class="">
        <div class="flex md:items-center justify-between mb-6 flex-col lg:flex-row gap-y-3">
            <h2 class="section-title a-font mb-4 md:mb-0">Календарь регат сезона</h2>
            <div class="flex items-center gap-4 flex-col md:flex-row">
                {{-- Кнопка скачивания PDF --}}
                <a
                    href="{{ route('regattas.calendar.pdf', ['year' => $year]) }}"
                    target="_blank"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-[#2D92CE] text-white text-sm font-semibold transition-colors duration-200 shrink-0"
                    title="Скачать календарь регат в PDF"
                >
                    <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 3a1 1 0 0 1 1 1v9.586l2.293-2.293a1 1 0 0 1 1.414 1.414l-4 4a1 1 0 0 1-1.414 0l-4-4a1 1 0 1 1 1.414-1.414L11 13.586V4a1 1 0 0 1 1-1Z" fill="currentColor"/>
                        <path d="M6 17a1 1 0 1 0-2 0v.6C4 19.482 5.518 21 7.4 21h9.2c1.882 0 3.4-1.518 3.4-3.4V17a1 1 0 1 0-2 0v.6c0 .778-.622 1.4-1.4 1.4H7.4c-.778 0-1.4-.622-1.4-1.4V17Z" fill="currentColor"/>
                    </svg>
                    Скачать календарь
                </a>
                {{-- Легенда (кликабельная — фильтрует регаты по статусу) --}}
                <div class="flex items-center gap-4 text-[#2E325C]">
                    <button type="button" @click="toggleStatus('finished')"
                        :class="isStatusActive('finished') ? '' : (activeStatuses.length ? 'opacity-40' : '')"
                        class="flex items-center gap-1.5 text-xs md:text-base cursor-pointer transition-opacity">
                        <span class="size-2 md:size-4 rounded-full bg-[#157949] inline-block"></span>Состоявшиеся
                    </button>
                    <button type="button" @click="toggleStatus('closest')"
                        :class="isStatusActive('closest') ? '' : (activeStatuses.length ? 'opacity-40' : '')"
                        class="flex items-center gap-1.5 text-xs md:text-base cursor-pointer transition-opacity">
                        <span class="size-2 md:size-4 rounded-full bg-[#C2A36B] inline-block"></span>Ближайшие
                    </button>
                    <button type="button" @click="toggleStatus('upcoming')"
                        :class="isStatusActive('upcoming') ? '' : (activeStatuses.length ? 'opacity-40' : '')"
                        class="flex items-center gap-1.5 text-xs md:text-base cursor-pointer transition-opacity">
                        <span class="size-2 md:size-4 rounded-full bg-[#C6C6C6] inline-block"></span>Планируемые
                    </button>
                </div>
                {{-- Выбор года --}}
                    @if ($showSelector)
                <div class="calendar-icon w-full md:w-auto">
                
                    <div class="calendar-icon">
                        <x-custom-select
                            name="season_year"
                            :options="$years"
                            wire:model.live="year"
                        />
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Слайдер месяцев --}}
        <div class="relative" x-ref="wrapper">
            <button @click="prev()" :disabled="offset === 0"
                class="absolute hidden md:flex -left-5 top-1/2 -translate-y-1/2 z-10 bg-[#2D92CE] hover:bg-[#0074CC] cursor-pointer rounded-full w-9 h-9 items-center justify-center text-white disabled:opacity-30 disabled:cursor-not-allowed transition-all">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>

            <div class="overflow-hidden">
                <div
                    class="slides flex will-change-transform slider-grab"
                    :class="{ 'slider-dragging': dragging }"
                    :style="`gap: ${gap}px; transform: translateX(${-offset * (cardWidth + gap)}px)`"
                    @mousedown="onDragStart"
                    @mousemove="onDragMove"
                    @mouseup="onDragEnd"
                    @mouseleave="onDragEnd"
                    @touchstart="onTouchStart"
                    @touchmove="onTouchMove"
                    @touchend="onTouchEnd">
                    @foreach ($months as $month)
                        <div class="p-4 shadow-xs transition-all duration-200 shrink-0 hover:bg-[#2D92CE26]
                            {{ $month['is_current'] ? 'bg-[#2D92CE26]' : 'bg-[#F8F8F8]' }}"
                            :style="`width: ${cardWidth}px`">
                            <h3 class="text-[#2E325C] font-medium text-2xl pb-4 mb-3 a-font border-b
                                {{ $month['is_current'] ? 'text-[#2D92CE] border-b-[#2D92CE]/30' : 'border-b-[#EAEAEA]' }}">
                                {{ $month['name'] }}
                            </h3>
                            <div class="space-y-3">
                                @foreach ($month['events'] as $event)
                                    <div x-show="isEventVisible('{{ $event['status'] }}')"
                                        class="flex gap-2 py-4 border-b border-b-[#EAEAEA] last:border-b-0">
                                        <div>
                                            <span class="size-4 rounded-full block mt-0.5
                                                {{ $event['status'] === 'cancelled' ? 'bg-[#a12f15]' : '' }}
                                                {{ $event['status'] === 'postponed' ? 'bg-[#a19315]' : '' }}
                                                {{ $event['status'] === 'finished' ? 'bg-[#157949]' : '' }}
                                                {{ $event['status'] === 'closest' ? 'bg-[#C2A36B]' : '' }}
                                                {{ $event['status'] === 'upcoming' ? 'bg-[#C6C6C6]' : '' }}">
                                            </span>
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            @if ($event['status'] != 'postponed')<p class="text-[#2E325C] text-sm">{{ $event['date'] }}</p>@endif
                                            @if ($event['status'] === 'postponed')
                                                <span class="self-start inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-white bg-[#a19315]">
                                                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                    Перенесена@if (!empty($event['postponed_to'])) на<br>{{ $event['postponed_to'] }}@elseif (!empty($event['postponed_note']))<br>{{ $event['postponed_note'] }}@endif
                                                </span>
                                            @elseif ($event['status'] === 'cancelled')
                                                <span class="self-start inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-white bg-[#a12f15]">
                                                    Отменена
                                                </span>
                                            @endif
                                            <p class="text-[#2E325C] font-bold mt-0.5 {{ in_array($event['status'], ['postponed', 'cancelled']) ? 'line-through decoration-1 opacity-70' : '' }}"><a class="hover:underline" href="{{ $event['url'] }}">{{ $event['title'] }}</a></p>
                                            <!--<p class="text-brand-gray-light text-sm">{{ $event['city'] }}</p>-->
                                            <x-regatta-external-id :value="$event['external_id']" class="text-xs block" />
                                            <div class="controls text-xs font-semibold flex gap-2">
                                                @if ($event['has_documents'])
                                                    <a href="{{ $event['documents_url'] }}"
                                                       class="downloadsDocuments p-2 bg-white text-[#2E325C] cursor-pointer">Документы</a>
                                                @else
                                                    <button type="button" disabled
                                                            class="downloadsDocuments p-2 bg-white text-[#2E325C] opacity-50 cursor-not-allowed">Документы</button>
                                                @endif
                                                @if ($event['can_join'])
                                                    <button type="button"
                                                            @click="$dispatch('open-join-regatta-modal', { regattaId: '{{ $event['id'] }}' })"
                                                            class="join p-2 bg-[#2D92CE] text-white cursor-pointer">Заявка</button>
                                                @else
                                                    <button type="button" disabled
                                                            class="join p-2 bg-[#2D92CE] text-white opacity-50 cursor-not-allowed">Заявка</button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach

                                @if (empty($month['events']))
                                    <div class="text-[#C6C6C6] text-sm py-4 text-center">Нет регат</div>
                                @else
                                    <div x-show="!monthHasVisible({{ json_encode(array_column($month['events'], 'status')) }})"
                                         class="text-[#C6C6C6] text-sm py-4 text-center">Нет регат по фильтру</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <button
                @click="next()"
                :disabled="offset >= maxOffset"
                aria-label="Вперёд"
                class="absolute hidden md:flex -right-5 top-1/2 -translate-y-1/2 z-10 w-9 h-9 items-center justify-center cursor-pointer bg-[#2D92CE] hover:bg-[#0074CC] text-white rounded-full shadow-md disabled:opacity-30 disabled:cursor-not-allowed transition-colors duration-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        </div>

        {{-- Точки-пагинация --}}
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

    {{-- Alpine-логика слайдера --}}
    <script>
function regattaCalendar() {
    return {
        visible: 5,
        gap: 16,
        offset: 0,
        cardWidth: 0,

        dragging: false,
        dragStartX: 0,
        dragStartOffset: 0,

        // --- Фильтр по статусу (легенда) ---
        activeStatuses: [],

        toggleStatus(status) {
            const idx = this.activeStatuses.indexOf(status);
            if (idx === -1) {
                this.activeStatuses.push(status);
            } else {
                this.activeStatuses.splice(idx, 1);
            }
        },

        isStatusActive(status) {
            return this.activeStatuses.includes(status);
        },

        isEventVisible(status) {
            return this.activeStatuses.length === 0 || this.activeStatuses.includes(status);
        },

        monthHasVisible(statuses) {
            if (this.activeStatuses.length === 0) return true;
            return statuses.some(s => this.activeStatuses.includes(s));
        },

        get maxOffset() {
            return Math.max(0, 12 - this.visible);
        },

        prev() { if (this.offset > 0) this.offset--; },
        next() { if (this.offset < this.maxOffset) this.offset++; },
        goTo(idx) {
            this.offset = Math.min(Math.max(idx, 0), this.maxOffset);
        },

        calcCardWidth() {
            const wrapperWidth = this.$refs.wrapper.clientWidth;
            this.cardWidth = Math.floor((wrapperWidth - (this.visible - 1) * this.gap) / this.visible);
        },

        init() {
            this.updateVisible();
            this.calcCardWidth();
            const currentMonthIdx = parseInt(this.$el.dataset.currentMonth) || 0;
            this.offset = Math.min(currentMonthIdx, this.maxOffset);
            window.addEventListener('resize', () => {
                this.updateVisible();
                this.calcCardWidth();
            });
        },

        updateVisible() {
            const w = window.innerWidth;
            if (w < 640) {
                this.visible = 1;
            } else if (w < 1024) {
                this.visible = 3;
            } else {
                this.visible = 5;
            }
            if (this.offset > this.maxOffset) this.offset = Math.max(0, this.maxOffset);
        },

        // --- Mouse drag ---
        onDragStart(event) {
            this.dragging = true;
            this.dragStartX = event.clientX;
            this.dragStartOffset = this.offset;
        },

        onDragMove(event) {
            if (!this.dragging) return;
            event.preventDefault();
            const diff = event.clientX - this.dragStartX;
            if (Math.abs(diff) > this.cardWidth * 0.25) {
                const direction = diff > 0 ? -1 : 1;
                const newOffset = this.dragStartOffset + direction;
                if (newOffset >= 0 && newOffset <= this.maxOffset) {
                    this.offset = newOffset;
                }
                this.dragging = false;
            }
        },

        onDragEnd() {
            this.dragging = false;
        },

        // --- Touch drag ---
        onTouchStart(event) {
            this.dragging = true;
            this.dragStartX = event.touches[0].clientX;
            this.dragStartOffset = this.offset;
        },

        onTouchMove(event) {
            if (!this.dragging) return;
            const diff = event.touches[0].clientX - this.dragStartX;
            if (Math.abs(diff) > this.cardWidth * 0.25) {
                const direction = diff > 0 ? -1 : 1;
                const newOffset = this.dragStartOffset + direction;
                if (newOffset >= 0 && newOffset <= this.maxOffset) {
                    this.offset = newOffset;
                }
                this.dragging = false;
            }
        },

        onTouchEnd() {
            this.dragging = false;
        },
    }
}
    </script>
</div>
