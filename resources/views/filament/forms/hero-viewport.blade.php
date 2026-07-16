@php
    // Контрол управляет скрытыми полями формы: картой кропов hero_crops (ключ файла → {crop_x..})
    // и общей высотой блока hero_height.
    $prefix     = \Illuminate\Support\Str::beforeLast($getStatePath(), '.');
    $cropsPath  = $prefix . '.hero_crops';
    $heightPath = $prefix . '.hero_height';

    // Список изображений текущего состояния формы (ключ + превью-URL). Пересчитывается
    // на каждом рендере, поэтому только что загруженные фото появляются сразу (FileUpload ->live()).
    $images = $getLivewire()->heroImages();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            crops: $wire.$entangle('{{ $cropsPath }}'),
            height: $wire.$entangle('{{ $heightPath }}'),
            aspects: {},
            drag: null,

            clamp(v, a, b) { return Math.min(b, Math.max(a, v)); },

            ensure(key) {
                if (! this.crops) this.crops = {};
                if (! this.crops[key]) this.crops[key] = { crop_x: 0, crop_y: 0, crop_w: 1, crop_h: 1 };
            },

            blockH() { return this.clamp(Number(this.height) || 768, 120, 768); },

            onImgLoad(key, e) {
                this.aspects[key] = e.target.naturalWidth / e.target.naturalHeight;
                this.ensure(key);
                this.relock(key);
            },

            maxW(key) {
                const a = this.aspects[key];
                return a ? Math.min(1, 1920 / (a * this.blockH())) : 1;
            },

            relock(key) {
                const a = this.aspects[key];
                if (! a) return;
                this.ensure(key);
                const c = this.crops[key];
                let cw = this.clamp(Number(c.crop_w) || 1, 0.1, this.maxW(key));
                let ch = cw * a * this.blockH() / 1920;
                let cx = this.clamp(Number(c.crop_x) || 0, 0, 1 - cw);
                let cy = this.clamp(Number(c.crop_y) || 0, 0, 1 - ch);
                this.crops[key] = { crop_x: cx, crop_y: cy, crop_w: cw, crop_h: ch };
            },

            relockAll() { Object.keys(this.aspects).forEach((k) => this.relock(k)); },

            frac(e) {
                const r = this.drag.stage.getBoundingClientRect();
                const t = e.touches ? e.touches[0] : e;
                return {
                    x: this.clamp((t.clientX - r.left) / r.width, 0, 1),
                    y: this.clamp((t.clientY - r.top) / r.height, 0, 1),
                };
            },

            startDrag(key, e) {
                const stage = e.currentTarget.closest('[data-stage]');
                this.ensure(key);
                this.drag = { key, stage, origin: null, start: { x: Number(this.crops[key].crop_x), y: Number(this.crops[key].crop_y) } };
                this.drag.origin = this.frac(e);
            },

            onMove(e) {
                if (! this.drag) return;
                const key = this.drag.key;
                const f = this.frac(e);
                const c = this.crops[key];
                const cw = Number(c.crop_w), ch = Number(c.crop_h);
                const cx = this.clamp(this.drag.start.x + (f.x - this.drag.origin.x), 0, 1 - cw);
                const cy = this.clamp(this.drag.start.y + (f.y - this.drag.origin.y), 0, 1 - ch);
                this.crops[key] = { crop_x: cx, crop_y: cy, crop_w: cw, crop_h: ch };
            },
            endDrag() { this.drag = null; },

            zoom(key, e) {
                this.ensure(key);
                const c = this.crops[key];
                const step = e.deltaY < 0 ? -0.05 : 0.05;
                const cw = this.clamp((Number(c.crop_w) || 1) + step, 0.1, this.maxW(key));
                this.crops[key] = { ...c, crop_w: cw };
                this.relock(key);
            },
        }"
        x-init="if (Array.isArray(crops) || ! crops) crops = Object.assign({}, crops || {});"
        x-on:mousemove.window="onMove($event)"
        x-on:mouseup.window="endDrag()"
        x-on:touchmove.window.passive="onMove($event)"
        x-on:touchend.window="endDrag()"
        style="display:flex; flex-direction:column; gap:12px;"
    >
        <div style="display:flex; align-items:center; gap:10px;">
            <span style="font-size:12px; color:#6b7280; width:56px; flex-shrink:0;">Высота</span>
            <input type="range" min="120" max="768" step="4" x-model.number="height" x-on:input="relockAll()" style="flex:1;">
            <span style="font-size:12px; color:#6b7280; width:52px; text-align:right;" x-text="Math.round(Number(height) || 768) + 'px'"></span>
        </div>

        @forelse($images as $img)
            @php $key = $img['key']; @endphp
            <div>
                <div
                    data-stage
                    style="position:relative; width:100%; overflow:hidden; border-radius:8px; border:1px solid rgba(120,120,120,0.35); background:rgba(120,120,120,0.08); user-select:none; touch-action:none;"
                    x-on:wheel.prevent="zoom('{{ $key }}', $event)"
                >
                    <img
                        x-on:load="onImgLoad('{{ $key }}', $event)"
                        src="{{ $img['url'] }}"
                        alt=""
                        style="display:block; width:100%; height:auto; pointer-events:none; user-select:none;"
                    >
                    <div
                        :style="`position:absolute; box-sizing:border-box; border:2px solid #fff; cursor:move; left:${(crops?.['{{ $key }}']?.crop_x ?? 0) * 100}%; top:${(crops?.['{{ $key }}']?.crop_y ?? 0) * 100}%; width:${(crops?.['{{ $key }}']?.crop_w ?? 1) * 100}%; height:${(crops?.['{{ $key }}']?.crop_h ?? 1) * 100}%; box-shadow:0 0 0 9999px rgba(0,0,0,0.5);`"
                        x-on:mousedown.prevent.stop="startDrag('{{ $key }}', $event)"
                        x-on:touchstart.prevent.stop="startDrag('{{ $key }}', $event)"
                    ></div>
                </div>
            </div>
        @empty
            <div style="display:flex; align-items:center; justify-content:center; height:120px; font-size:13px; color:#9ca3af; border:1px dashed rgba(120,120,120,0.4); border-radius:8px;">
                Загрузите изображения выше — здесь появится настройка области для каждого.
            </div>
        @endforelse

        <p style="font-size:12px; line-height:1.4; color:#6b7280; margin:0;">
            Для каждого изображения тяните рамку по фото (позиция) и крутите колесо мыши (приближение).
            Пропорция рамки общая для всех слайдов и задаётся ползунком «Высота» (≤768px при Full HD).
        </p>
    </div>
</x-dynamic-component>
