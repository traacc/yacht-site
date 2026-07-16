@php
    // Контрол управляет тремя соседними скрытыми полями формы (hero_zoom / hero_pos_x / hero_pos_y).
    $prefix   = \Illuminate\Support\Str::beforeLast($getStatePath(), '.');
    $zoomPath = $prefix . '.hero_zoom';
    $posXPath = $prefix . '.hero_pos_x';
    $posYPath = $prefix . '.hero_pos_y';

    // Вычисляем URL превью на этапе рендера: к этому моменту состояние формы
    // уже заполнено, поэтому уже загруженное изображение показывается корректно.
    // $getLivewire() отдаёт саму страницу (Filament прокидывает публичные методы компонента во вью).
    $imageUrl = $getLivewire()->heroPreviewUrl();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            zoom: $wire.$entangle('{{ $zoomPath }}'),
            posX: $wire.$entangle('{{ $posXPath }}'),
            posY: $wire.$entangle('{{ $posYPath }}'),
            dragging: false,
            last: { x: 0, y: 0 },
            point(e) {
                const t = e.touches ? e.touches[0] : e;
                return { x: t.clientX, y: t.clientY };
            },
            start(e) {
                this.dragging = true;
                this.last = this.point(e);
            },
            move(e) {
                if (! this.dragging) return;
                const p = this.point(e);
                const rect = this.$refs.stage.getBoundingClientRect();
                const dx = (p.x - this.last.x) / rect.width * 100;
                const dy = (p.y - this.last.y) / rect.height * 100;
                // «Схватить и тащить»: движение вправо открывает левую часть кадра.
                this.posX = Math.min(100, Math.max(0, this.posX - dx));
                this.posY = Math.min(100, Math.max(0, this.posY - dy));
                this.last = p;
            },
            end() { this.dragging = false; },
            wheel(e) {
                const next = this.zoom + (e.deltaY < 0 ? 0.1 : -0.1);
                this.zoom = Math.min(5, Math.max(1, Math.round(next * 10) / 10));
            },
            reset() { this.zoom = 1; this.posX = 50; this.posY = 50; },
        }"
        class="space-y-3"
    >
        {{-- Рамка = видимая область блока при Full HD (соотношение 5:2). --}}
        <div
            x-ref="stage"
            class="relative w-full overflow-hidden rounded-lg border border-gray-300 bg-gray-100 dark:border-white/10 dark:bg-gray-900 select-none"
            style="aspect-ratio: 5 / 2;"
            :class="dragging ? 'cursor-grabbing' : 'cursor-grab'"
            x-on:mousedown.prevent="start"
            x-on:mousemove.window="move"
            x-on:mouseup.window="end"
            x-on:touchstart.prevent="start"
            x-on:touchmove.prevent="move"
            x-on:touchend="end"
            x-on:wheel.prevent="wheel"
        >
            @if($imageUrl)
                <img
                    src="{{ $imageUrl }}"
                    alt=""
                    class="absolute inset-0 w-full h-full object-cover pointer-events-none"
                    :style="`object-position:${posX}% ${posY}%; transform:scale(${zoom}); transform-origin:${posX}% ${posY}%;`"
                >
            @else
                <div class="absolute inset-0 flex items-center justify-center text-sm text-gray-400">
                    Загрузите изображение выше
                </div>
            @endif
        </div>

        <div class="flex items-center gap-3">
            <span class="text-xs text-gray-500 dark:text-gray-400 w-16 shrink-0">Масштаб</span>
            <input type="range" min="1" max="5" step="0.1" x-model.number="zoom" class="flex-1">
            <span class="text-xs tabular-nums w-10 text-right text-gray-600 dark:text-gray-300" x-text="Number(zoom).toFixed(1) + '×'"></span>
            <button type="button" x-on:click="reset" class="text-xs text-primary-600 hover:underline dark:text-primary-400">Сброс</button>
        </div>

        <p class="text-xs text-gray-500 dark:text-gray-400">
            Перетаскивайте изображение мышью, чтобы выбрать видимую область. Ползунок или колесо мыши — приближение.
            Рамка показывает, как фон выглядит на Full HD-мониторе при 100% масштабе.
        </p>
    </div>
</x-dynamic-component>
