@php
    // Контрол управляет соседними скрытыми полями формы (crop-прямоугольник + высота).
    $prefix     = \Illuminate\Support\Str::beforeLast($getStatePath(), '.');
    $cropXPath  = $prefix . '.hero_crop_x';
    $cropYPath  = $prefix . '.hero_crop_y';
    $cropWPath  = $prefix . '.hero_crop_w';
    $cropHPath  = $prefix . '.hero_crop_h';
    $heightPath = $prefix . '.hero_height';

    // URL превью вычисляем на этапе рендера (состояние формы уже заполнено),
    // поэтому и уже сохранённое, и только что загруженное изображение показываются корректно.
    $imageUrl = $getLivewire()->heroPreviewUrl();

    // Стили заданы инлайном намеренно: этот blade не входит в @source темы Filament,
    // поэтому произвольные Tailwind-классы здесь не собираются.
    // Маркеры слегка заведены внутрь рамки, чтобы не обрезались overflow:hidden сцены,
    // даже когда прямоугольник занимает весь кадр.
    $handles = [
        'nw' => 'top:-4px;left:-4px;cursor:nwse-resize;',
        'n'  => 'top:-4px;left:50%;margin-left:-6px;cursor:ns-resize;',
        'ne' => 'top:-4px;right:-4px;cursor:nesw-resize;',
        'e'  => 'top:50%;right:-4px;margin-top:-6px;cursor:ew-resize;',
        'se' => 'bottom:-4px;right:-4px;cursor:nwse-resize;',
        's'  => 'bottom:-4px;left:50%;margin-left:-6px;cursor:ns-resize;',
        'sw' => 'bottom:-4px;left:-4px;cursor:nesw-resize;',
        'w'  => 'top:50%;left:-4px;margin-top:-6px;cursor:ew-resize;',
    ];
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            cropX: $wire.$entangle('{{ $cropXPath }}'),
            cropY: $wire.$entangle('{{ $cropYPath }}'),
            cropW: $wire.$entangle('{{ $cropWPath }}'),
            cropH: $wire.$entangle('{{ $cropHPath }}'),
            height: $wire.$entangle('{{ $heightPath }}'),
            imgAspect: null,
            mode: null,
            origin: { x: 0, y: 0 },
            startRect: { x: 0, y: 0, w: 1, h: 1 },
            minSize: 0.05,

            clamp(v, a, b) { return Math.min(b, Math.max(a, v)); },

            onImgLoad() {
                const img = this.$refs.img;
                if (! img || ! img.naturalWidth) return;
                this.imgAspect = img.naturalWidth / img.naturalHeight;
                if (Number(this.cropW) >= 0.999 && Number(this.cropH) >= 0.999) {
                    const ch = Math.min(1, 768 * this.imgAspect / 1920);
                    this.cropH = ch;
                    this.cropY = (1 - ch) / 2;
                    this.applyHeight();
                }
            },

            frac(e) {
                const r = this.$refs.stage.getBoundingClientRect();
                const t = e.touches ? e.touches[0] : e;
                return {
                    x: this.clamp((t.clientX - r.left) / r.width, 0, 1),
                    y: this.clamp((t.clientY - r.top) / r.height, 0, 1),
                };
            },

            startDrag(e) {
                this.mode = 'move';
                this.origin = this.frac(e);
                this.startRect = { x: +this.cropX, y: +this.cropY, w: +this.cropW, h: +this.cropH };
            },
            startResize(e, handle) { this.mode = handle; },

            onMove(e) {
                if (! this.mode) return;
                const f = this.frac(e);

                if (this.mode === 'move') {
                    const dx = f.x - this.origin.x;
                    const dy = f.y - this.origin.y;
                    this.cropX = this.clamp(this.startRect.x + dx, 0, 1 - this.cropW);
                    this.cropY = this.clamp(this.startRect.y + dy, 0, 1 - this.cropH);
                } else {
                    let left = +this.cropX, top = +this.cropY;
                    let right = +this.cropX + +this.cropW, bottom = +this.cropY + +this.cropH;
                    if (this.mode.includes('w')) left = Math.min(f.x, right - this.minSize);
                    if (this.mode.includes('e')) right = Math.max(f.x, left + this.minSize);
                    if (this.mode.includes('n')) top = Math.min(f.y, bottom - this.minSize);
                    if (this.mode.includes('s')) bottom = Math.max(f.y, top + this.minSize);
                    this.cropX = this.clamp(left, 0, 1);
                    this.cropW = this.clamp(right, 0, 1) - this.cropX;
                    this.cropY = this.clamp(top, 0, 1);
                    this.cropH = this.clamp(bottom, 0, 1) - this.cropY;
                    this.constrainHeight(this.mode);
                }
                this.applyHeight();
            },
            endDrag() { this.mode = null; },

            // Пропорция прямоугольника ограничена так, чтобы высота блока была в диапазоне 120..768px.
            constrainHeight(mode) {
                if (! this.imgAspect) return;
                // Держим высоту блока в диапазоне 120..768px, ограничивая пропорцию рамки.
                const maxCH = Math.min(1, 768 * this.cropW * this.imgAspect / 1920);
                const minCH = Math.min(maxCH, 120 * this.cropW * this.imgAspect / 1920);
                const ch = this.clamp(+this.cropH, minCH, maxCH);
                if (ch !== +this.cropH) {
                    if (mode && mode.includes('n')) {
                        const bottom = +this.cropY + +this.cropH;
                        this.cropY = this.clamp(bottom - ch, 0, 1 - ch);
                    }
                    this.cropH = ch;
                }
            },

            applyHeight() {
                if (! this.imgAspect || this.cropW <= 0) return;
                const h = Math.round(1920 * this.cropH / (this.cropW * this.imgAspect));
                this.height = this.clamp(h, 120, 768);
            },

            reset() {
                this.cropX = 0; this.cropW = 1;
                const ch = this.imgAspect ? Math.min(1, 768 * this.imgAspect / 1920) : 1;
                this.cropH = ch; this.cropY = (1 - ch) / 2;
                this.applyHeight();
            },
        }"
        x-init="$nextTick(() => { const i = $refs.img; if (i && i.complete && i.naturalWidth) onImgLoad(); })"
        x-on:mousemove.window="onMove($event)"
        x-on:mouseup.window="endDrag()"
        x-on:touchmove.window.passive="onMove($event)"
        x-on:touchend.window="endDrag()"
        style="display:flex; flex-direction:column; gap:8px;"
    >
        {{-- Всё изображение целиком; поверх — рамка видимой области. --}}
        <div
            x-ref="stage"
            style="position:relative; width:100%; overflow:hidden; border-radius:8px; border:1px solid rgba(120,120,120,0.35); background:rgba(120,120,120,0.08); user-select:none; touch-action:none;"
        >
            @if($imageUrl)
                <img
                    x-ref="img"
                    x-on:load="onImgLoad()"
                    src="{{ $imageUrl }}"
                    alt=""
                    style="display:block; width:100%; height:auto; pointer-events:none; user-select:none;"
                >

                {{-- Рамка viewport: тело тянем (move), маркеры по краям — изменяют размер. --}}
                <div
                    :style="`position:absolute; box-sizing:border-box; border:2px solid #fff; cursor:move; left:${cropX * 100}%; top:${cropY * 100}%; width:${cropW * 100}%; height:${cropH * 100}%; box-shadow:0 0 0 9999px rgba(0,0,0,0.5);`"
                    x-on:mousedown.prevent.stop="startDrag($event)"
                    x-on:touchstart.prevent.stop="startDrag($event)"
                >
                    @foreach($handles as $h => $pos)
                        <div
                            style="position:absolute; width:12px; height:12px; background:#fff; border:1px solid #9ca3af; border-radius:2px; box-shadow:0 1px 2px rgba(0,0,0,0.3); {{ $pos }}"
                            x-on:mousedown.prevent.stop="startResize($event, '{{ $h }}')"
                            x-on:touchstart.prevent.stop="startResize($event, '{{ $h }}')"
                        ></div>
                    @endforeach
                </div>
            @else
                <div style="display:flex; align-items:center; justify-content:center; height:160px; font-size:13px; color:#9ca3af;">
                    Загрузите изображение выше
                </div>
            @endif
        </div>

        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <p style="font-size:12px; line-height:1.4; color:#6b7280; margin:0;">
                Рамка — то, что видно на сайте. Тяните её по изображению, за маркеры меняйте размер и пропорцию (пропорция задаёт высоту блока, ≤768px).
            </p>
            <button type="button" x-on:click="reset()"
                    style="flex-shrink:0; font-size:12px; color:#2563eb; background:none; border:0; padding:0; cursor:pointer; text-decoration:underline;">
                Сброс
            </button>
        </div>
    </div>
</x-dynamic-component>
