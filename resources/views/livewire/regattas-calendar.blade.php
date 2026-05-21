{{-- resources/views/livewire/regattas-calendar.blade.php --}}
<style>
    .month-card { flex: 0 0 calc((100% - 4 * 1rem) / 5); }
    @media (max-width: 1023px) { .month-card { flex: 0 0 calc((100% - 2 * 1rem) / 3); } }
    @media (max-width: 639px)  { .month-card { flex: 0 0 85%; } }

    .slides   { transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
    .page-dot { transition: width 0.3s ease, background-color 0.3s ease; }
</style>

<div x-data="regattaCalendar()" data-current-month="{{ now()->format('n') - 1 }}" class="py-12 bg-brand-light">
    <div class="max-w-(--breakpoint-2xl) mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex md:items-center justify-between mb-6 flex-col md:flex-row">
            <h2 class="section-title a-font mb-4 md:mb-0">Календарь регат сезона</h2>
            <div class="flex items-center gap-4 flex-col md:flex-row">
                {{-- Легенда --}}
                <div class="flex items-center gap-4 text-[#2E325C]">
                    <span class="flex items-center gap-1.5 text-xs md:text-base"><span class="size-2 md:size-4 rounded-full bg-[#157949] inline-block"></span>Состоявшиеся</span>
                    <span class="flex items-center gap-1.5 text-xs md:text-base"><span class="size-2 md:size-4 rounded-full bg-[#C2A36B] inline-block"></span>Ближайшие</span>
                    <span class="flex items-center gap-1.5 text-xs md:text-base"><span class="size-2 md:size-4 rounded-full bg-[#C6C6C6] inline-block"></span>Планируемые</span>
                </div>
                {{-- Выбор года --}}
                    @if ($showSelector)
                    <div class="calendar-icon w-full md:w-auto">
                
                    <select
                        wire:model.live="year"
                        class="border border-[#C6C6C6] bg-white w-full text-[#2E325C] px-4 py-2 min-w-[120px] focus:outline-hidden focus:ring-2 focus:ring-[#2D92CE] text-sm font-medium"
                    >
                        @foreach ($years as $y)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endforeach
                    </select>
                    @endif
                </div>
            </div>
        </div>

        {{-- Слайдер месяцев --}}
        <div class="relative px-4 sm:px-6 lg:px-8">
            <button @click="prev()" :disabled="offset === 0"
                class="absolute left-4 top-1/2 -translate-y-1/2 z-10 bg-[#2D92CE] hover:bg-[#0074CC] cursor-pointer rounded-full w-9 h-9 flex items-center justify-center text-white disabled:opacity-30 disabled:cursor-not-allowed transition-all">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>

            <div class="overflow-hidden">
                <div
                    class="slides flex gap-4 will-change-transform"
                    :style="`transform: translateX(calc(-${offset} * (100% + ${gap}px) / ${visible}))`">
                    @foreach ($months as $month)
                        <div class="p-4 shadow-xs transition-all duration-200 month-card hover:bg-[#2D92CE26]
                            {{ $month['is_current'] ? 'bg-[#2D92CE26]' : 'bg-[#F8F8F8]' }}">
                            <h3 class="text-[#2E325C] font-medium text-2xl pb-4 mb-3 a-font border-b
                                {{ $month['is_current'] ? 'text-[#2D92CE] border-b-[#2D92CE]/30' : 'border-b-[#EAEAEA]' }}">
                                {{ $month['name'] }}
                            </h3>
                            <div class="space-y-3">
                                @foreach ($month['events'] as $event)
                                    <div class="flex gap-2 py-4 border-b border-b-[#EAEAEA] last:border-b-0">
                                        <div>
                                            <span class="size-4 rounded-full block mt-0.5 
                                                {{ $event['status'] === 'completed' ? 'bg-[#157949]' : '' }}
                                                {{ $event['status'] === 'upcoming' ? 'bg-[#C2A36B]' : '' }}
                                                {{ $event['status'] === 'planned' ? 'bg-[#C6C6C6]' : '' }}">
                                            </span>
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <p class="text-[#2E325C] text-sm">{{ $event['date'] }}</p>
                                            <p class="text-[#2E325C] font-bold mt-0.5"><a class="hover:underline" href="{{ $event['url'] }}">{{ $event['title'] }}</a></p>
                                            <p class="text-brand-gray-light text-sm">{{ $event['city'] }}</p>
                                        </div>
                                    </div>
                                @endforeach

                                @if (empty($month['events']))
                                    <div class="text-[#C6C6C6] text-sm py-4 text-center">Нет регат</div>
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
                class="absolute right-4 top-1/2 -translate-y-1/2 z-10 w-9 h-9 flex items-center justify-center cursor-pointer bg-[#2D92CE] hover:bg-[#0074CC] text-white rounded-full shadow-md disabled:opacity-30 disabled:cursor-not-allowed transition-colors duration-200">
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
</div>

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
