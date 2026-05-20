{{--
|=============================================================================
| TALL-Stack Card Slider Component
|=============================================================================
| Использование:
|   <x-card-slider
|       :items="$products"
|       :visible-items="['mobile' => 1.2, 'tablet' => 2.3, 'desktop' => 3.5]"
|       gap="gap-5"
|       :autoplay="true"
|       :interval="4000"
|       :loop="true"
|   >
|       {{-- Слот для рендера одной карточки. Переменная $item доступна внутри --}}
|       <x-slot:card>
|           <div class="bg-white rounded-2xl shadow-lg p-6">
|               <h3 class="font-bold text-lg">{{ $item['title'] }}</h3>
|               <p class="text-gray-500 mt-2">{{ $item['description'] }}</p>
|           </div>
|       </x-slot:card>
|   </x-card-slider>
--}}

@props([
    'items'        => [],          // Массив данных карточек
    'visibleItems' => [            // Количество видимых карточек на брейкпоинт
        'mobile'  => 1.2,         // Дробь = следующая карточка "выглядывает"
        'tablet'  => 2.3,
        'desktop' => 3.5,
    ],
    'gap'          => 'gap-5',    // Tailwind-класс gap между карточками
    'autoplay'     => false,      // Автопрокрутка
    'interval'     => 5000,       // Интервал автопрокрутки (мс)
    'loop'         => true,       // Зацикленность
])

{{-- Извлекаем числовое значение gap из Tailwind-класса для использования в Alpine.js --}}
{{-- Tailwind gap: gap-1=4px, gap-2=8px, gap-3=12px, gap-4=16px, gap-5=20px, gap-6=24px, gap-8=32px --}}
@php
    $gapMap = [
        'gap-0' => 0,  'gap-1' => 4,  'gap-2' => 8,  'gap-3' => 12,
        'gap-4' => 16, 'gap-5' => 20, 'gap-6' => 24,  'gap-7' => 28,
        'gap-8' => 32, 'gap-10' => 40, 'gap-12' => 48,
    ];
    $gapPx = $gapMap[$gap] ?? 20;

    // Передаём настройки в Alpine.js как JSON
    $alpineConfig = json_encode([
        'visibleItems' => $visibleItems,
        'gapPx'        => $gapPx,
        'totalItems'   => count($items),
        'autoplay'     => $autoplay,
        'interval'     => $interval,
        'loop'         => $loop,
    ]);
@endphp

<div
    x-data="cardSlider({{ $alpineConfig }})"
    x-init="init()"
    @resize.window.debounce.100ms="onResize()"
    class="relative w-full select-none"
    role="region"
    aria-label="Card Slider"
>
    {{-- ═══════════════════════════════════════════════════════════════════
         ТРЕК СЛАЙДЕРА
         overflow-hidden обрезает "выглядывающую" часть следующей карточки.
         Padding справа НЕ нужен — дробное visibleItems само создаёт peek.
    ═══════════════════════════════════════════════════════════════════ --}}
    <div
        class="overflow-hidden"
        @touchstart.passive="touchStart($event)"
        @touchmove.passive="touchMove($event)"
        @touchend="touchEnd($event)"
    >
        {{--
        ┌─────────────────────────────────────────────────────────────────┐
        │  МАТЕМАТИКА СМЕЩЕНИЯ (translateX)                               │
        │                                                                 │
        │  Пусть:                                                         │
        │    V  = visibleItems (дробное, напр. 2.3)                       │
        │    G  = gapPx (пиксели, напр. 20)                               │
        │    W  = ширина трека (containerWidth, пиксели)                  │
        │    N  = totalItems                                              │
        │    i  = currentIndex (текущий шаг)                              │
        │                                                                 │
        │  Ширина одной карточки:                                         │
        │    cardWidth = (W - G * (V - 1)) / V                            │
        │                                                                 │
        │  Пояснение: при V=2.3 видимых карточках в контейнере шириной W  │
        │  есть (V-1)=1.3 "промежутка" внутри видимой области.            │
        │  Вычитаем их суммарный gap и делим оставшееся пространство на V.│
        │                                                                 │
        │  Смещение для шага i:                                           │
        │    offsetPx = i * (cardWidth + G)                               │
        │                                                                 │
        │  Каждый шаг = одна полная карточка + один gap.                  │
        │  Таким образом, следующая карточка точно "заходит" под края.    │
        │                                                                 │
        │  Максимальный индекс (maxIndex):                                │
        │    Последняя позиция, при которой слайдер НЕ уходит в пустоту:  │
        │    maxIndex = N - floor(V)                                      │
        │                                                                 │
        │  Пример: N=8, V=2.3 → maxIndex = 8 - 2 = 6.                    │
        │  На шаге 6 видим карточки 7 и частично 8 (если есть).           │
        │  Количество dots = maxIndex + 1 = 7.                            │
        └─────────────────────────────────────────────────────────────────┘
        --}}
        <div
            class="flex {{ $gap }} transition-transform duration-500 ease-[cubic-bezier(.25,.46,.45,.94)] will-change-transform"
            :style="`transform: translateX(-${offsetPx}px)`"
            x-ref="track"
        >
            @foreach ($items as $index => $item)
                <div
                    class="flex-shrink-0"
                    :style="`width: ${cardWidth}px`"
                    role="group"
                    :aria-label="`Slide {{ $index + 1 }} of {{ count($items) }}`"
                    aria-roledescription="slide"
                >
                    {{-- Слот для содержимого карточки --}}
                    @if (isset($card))
                        {{ $card->with(['item' => $item, 'index' => $index]) }}
                    @else
                        {{-- Дефолтная карточка (если слот не передан) --}}
                        <div class="
                            bg-white rounded-2xl shadow-md overflow-hidden h-full
                            border border-gray-100 hover:shadow-xl
                            transition-shadow duration-300
                        ">
                            <div class="bg-gradient-to-br from-indigo-400 to-purple-500 h-44 flex items-center justify-center">
                                <span class="text-white text-5xl font-black opacity-40">
                                    {{ $index + 1 }}
                                </span>
                            </div>
                            <div class="p-5">
                                <h3 class="font-bold text-gray-900 text-lg leading-tight">
                                    {{ $item['title'] ?? "Card " . ($index + 1) }}
                                </h3>
                                @if (!empty($item['description']))
                                    <p class="text-gray-500 text-sm mt-2 leading-relaxed">
                                        {{ $item['description'] }}
                                    </p>
                                @endif
                                @if (!empty($item['tag']))
                                    <span class="
                                        inline-block mt-3 px-3 py-1 text-xs font-semibold
                                        bg-indigo-50 text-indigo-600 rounded-full
                                    ">
                                        {{ $item['tag'] }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
         КНОПКИ НАВИГАЦИИ (Prev / Next)
    ═══════════════════════════════════════════════════════════════════ --}}
    <button
        @click="prev()"
        :disabled="!loop && currentIndex === 0"
        :class="(!loop && currentIndex === 0) ? 'opacity-30 cursor-not-allowed' : 'hover:bg-white hover:shadow-lg hover:-translate-x-0.5'"
        class="
            absolute left-0 top-1/2 -translate-y-1/2 -translate-x-1/2
            z-10 flex items-center justify-center
            w-11 h-11 rounded-full
            bg-white/80 backdrop-blur-sm shadow-md
            border border-gray-200/60
            transition-all duration-200
            focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2
        "
        aria-label="Previous slide"
    >
        <svg class="w-5 h-5 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
        </svg>
    </button>

    <button
        @click="next()"
        :disabled="!loop && currentIndex >= maxIndex"
        :class="(!loop && currentIndex >= maxIndex) ? 'opacity-30 cursor-not-allowed' : 'hover:bg-white hover:shadow-lg hover:translate-x-0.5'"
        class="
            absolute right-0 top-1/2 -translate-y-1/2 translate-x-1/2
            z-10 flex items-center justify-center
            w-11 h-11 rounded-full
            bg-white/80 backdrop-blur-sm shadow-md
            border border-gray-200/60
            transition-all duration-200
            focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2
        "
        aria-label="Next slide"
    >
        <svg class="w-5 h-5 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
        </svg>
    </button>

    {{-- ═══════════════════════════════════════════════════════════════════
         ИНДИКАТОРЫ (Dots)
         Количество dots = maxIndex + 1 (ровно столько реальных позиций)
    ═══════════════════════════════════════════════════════════════════ --}}
    <div
        class="flex items-center justify-center gap-2 mt-5"
        role="tablist"
        aria-label="Slider navigation"
    >
        <template x-for="(dot, i) in dotsCount" :key="i">
            <button
                @click="goTo(i)"
                role="tab"
                :aria-selected="currentIndex === i"
                :aria-label="`Go to slide ${i + 1}`"
                class="
                    rounded-full transition-all duration-300
                    focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-1
                "
                :class="currentIndex === i
                    ? 'w-6 h-2.5 bg-indigo-600'
                    : 'w-2.5 h-2.5 bg-gray-300 hover:bg-gray-400'
                "
            ></button>
        </template>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════
     Alpine.js логика
     Вынесена в отдельный <script> для читаемости.
     В продакшне можно переместить в app.js / alpine-components.js.
═══════════════════════════════════════════════════════════════════════ --}}
<script>
function cardSlider(config) {
    return {
        // ── Конфигурация (из Blade-пропсов) ──────────────────────────
        visibleItems: config.visibleItems,  // { mobile: 1.2, tablet: 2.3, desktop: 3.5 }
        gapPx:        config.gapPx,         // числовое значение gap в пикселях
        totalItems:   config.totalItems,    // общее кол-во карточек
        autoplayOn:   config.autoplay,
        interval:     config.interval,
        loop:         config.loop,

        // ── Состояние ─────────────────────────────────────────────────
        currentIndex:    0,     // текущий шаг (0-based)
        cardWidth:       0,     // ширина карточки в px (пересчитывается при resize)
        containerWidth:  0,     // ширина трека в px
        currentVisible:  1,     // дробное кол-во видимых карточек для текущего breakpoint
        offsetPx:        0,     // текущее смещение translateX в px
        autoplayTimer:   null,

        // ── Touch-состояние ───────────────────────────────────────────
        touchStartX:  0,
        touchDeltaX:  0,
        isSwiping:    false,
        THRESHOLD:    50,   // px — минимальный свайп для срабатывания

        // ─────────────────────────────────────────────────────────────
        // Вычисляемые свойства
        // ─────────────────────────────────────────────────────────────

        /**
         * Максимальный индекс (последняя позиция без "пустого хвоста"):
         *   maxIndex = totalItems - floor(currentVisible)
         *
         * Пример: 8 карточек, visible=2.3 → maxIndex = 8 - 2 = 6
         * На позиции 6 видим карточки #7 и #8 (частично).
         */
        get maxIndex() {
            return Math.max(0, this.totalItems - Math.floor(this.currentVisible));
        },

        /**
         * Массив для v-for dots: [0, 1, 2, ... maxIndex]
         * Количество dots = maxIndex + 1
         */
        get dotsCount() {
            return Array.from({ length: this.maxIndex + 1 }, (_, i) => i);
        },

        // ─────────────────────────────────────────────────────────────
        // Инициализация
        // ─────────────────────────────────────────────────────────────
        init() {
            this.recalculate();
            if (this.autoplayOn) this.startAutoplay();

            // Пересчёт при изменении видимости вкладки
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    this.stopAutoplay();
                } else if (this.autoplayOn) {
                    this.startAutoplay();
                }
            });
        },

        // ─────────────────────────────────────────────────────────────
        // Расчёт геометрии
        // ─────────────────────────────────────────────────────────────

        /**
         * Определяет текущий breakpoint и возвращает нужное visibleItems.
         *
         * Breakpoints (соответствуют Tailwind по умолчанию):
         *   < 768px  → mobile
         *   768–1024 → tablet
         *   > 1024px → desktop
         */
        getVisibleForBreakpoint() {
            const w = window.innerWidth;
            if (w < 768)  return this.visibleItems.mobile  ?? 1.2;
            if (w < 1024) return this.visibleItems.tablet  ?? 2.3;
            return              this.visibleItems.desktop ?? 3.5;
        },

        /**
         * Пересчитывает ширину карточки и смещение.
         *
         * Формула ширины карточки:
         *   cardWidth = (containerWidth - gapPx * (V - 1)) / V
         *
         * Где V = currentVisible (дробное).
         *
         * Разбор: при V=2.3 внутри контейнера видны 2.3 карточки.
         * Между ними (V-1)=1.3 промежутка по gapPx каждый.
         * Вычитаем суммарный gap и делим на V — получаем ширину одной.
         */
        recalculate() {
            const track = this.$refs.track?.parentElement;
            if (!track) return;

            this.containerWidth  = track.clientWidth;
            this.currentVisible  = this.getVisibleForBreakpoint();

            // cardWidth = (W - G*(V-1)) / V
            this.cardWidth = (
                this.containerWidth - this.gapPx * (this.currentVisible - 1)
            ) / this.currentVisible;

            // Зажимаем currentIndex в новый maxIndex после resize
            this.currentIndex = Math.min(this.currentIndex, this.maxIndex);

            // Обновляем смещение для нового размера
            this.updateOffset();
        },

        /**
         * Вычисляет и применяет смещение трека.
         *
         * Смещение для шага i:
         *   offsetPx = i * (cardWidth + gapPx)
         *
         * Каждый шаг = одна полная карточка + один gap.
         * Это гарантирует, что каждая следующая карточка точно
         * выравнивается по левому краю контейнера.
         */
        updateOffset() {
            this.offsetPx = this.currentIndex * (this.cardWidth + this.gapPx);
        },

        // ─────────────────────────────────────────────────────────────
        // Навигация
        // ─────────────────────────────────────────────────────────────
        goTo(index) {
            this.currentIndex = Math.max(0, Math.min(index, this.maxIndex));
            this.updateOffset();
            this.resetAutoplay();
        },

        next() {
            if (this.currentIndex >= this.maxIndex) {
                if (this.loop) this.goTo(0);
            } else {
                this.goTo(this.currentIndex + 1);
            }
        },

        prev() {
            if (this.currentIndex <= 0) {
                if (this.loop) this.goTo(this.maxIndex);
            } else {
                this.goTo(this.currentIndex - 1);
            }
        },

        // ─────────────────────────────────────────────────────────────
        // Автоплей
        // ─────────────────────────────────────────────────────────────
        startAutoplay() {
            this.stopAutoplay();
            this.autoplayTimer = setInterval(() => this.next(), this.interval);
        },

        stopAutoplay() {
            if (this.autoplayTimer) {
                clearInterval(this.autoplayTimer);
                this.autoplayTimer = null;
            }
        },

        resetAutoplay() {
            if (this.autoplayOn) {
                this.stopAutoplay();
                this.startAutoplay();
            }
        },

        // ─────────────────────────────────────────────────────────────
        // Touch / Swipe
        // ─────────────────────────────────────────────────────────────
        touchStart(e) {
            this.touchStartX = e.touches[0].clientX;
            this.touchDeltaX = 0;
            this.isSwiping   = true;
            this.stopAutoplay();
        },

        touchMove(e) {
            if (!this.isSwiping) return;
            this.touchDeltaX = e.touches[0].clientX - this.touchStartX;
        },

        /**
         * Определяем направление свайпа только если он превысил THRESHOLD.
         * Это защищает от случайных срабатываний при вертикальном скролле.
         *
         * |touchDeltaX| > THRESHOLD (50px по умолчанию):
         *   отрицательный delta → свайп влево  → next()
         *   положительный delta → свайп вправо → prev()
         */
        touchEnd() {
            if (!this.isSwiping) return;
            this.isSwiping = false;

            if (Math.abs(this.touchDeltaX) > this.THRESHOLD) {
                this.touchDeltaX < 0 ? this.next() : this.prev();
            }

            if (this.autoplayOn) this.startAutoplay();
        },

        // ─────────────────────────────────────────────────────────────
        // Resize
        // ─────────────────────────────────────────────────────────────
        onResize() {
            this.recalculate();
        },
    };
}
</script>