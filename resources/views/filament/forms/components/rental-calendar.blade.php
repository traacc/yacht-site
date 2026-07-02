<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.$entangle('{{ $getStatePath() }}'),
            calMonth: (new Date()).getMonth(),
            calYear: (new Date()).getFullYear(),
            pendingStart: null,
            hoverDate: null,
            message: '',
            weekdays: ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'],
            monthNames: ['Январь','Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'],
            init() {
                if (! Array.isArray(this.state)) this.state = [];
                const starts = this.state.map(p => p.date_start).filter(Boolean).sort();
                if (starts.length) {
                    const d = new Date(starts[0] + 'T00:00:00');
                    this.calMonth = d.getMonth();
                    this.calYear = d.getFullYear();
                }
            },
            get monthLabel() { return this.monthNames[this.calMonth] + ' ' + this.calYear; },
            prevMonth() { if (this.calMonth === 0) { this.calMonth = 11; this.calYear--; } else { this.calMonth--; } },
            nextMonth() { if (this.calMonth === 11) { this.calMonth = 0; this.calYear++; } else { this.calMonth++; } },
            periodIndexFor(dateStr) { return this.state.findIndex(p => p.date_start && p.date_end && dateStr >= p.date_start && dateStr <= p.date_end); },
            inPreview(dateStr) {
                if (! this.pendingStart || ! this.hoverDate) return false;
                const a = this.pendingStart < this.hoverDate ? this.pendingStart : this.hoverDate;
                const b = this.pendingStart < this.hoverDate ? this.hoverDate : this.pendingStart;
                return dateStr >= a && dateStr <= b;
            },
            dayStatus(dateStr) {
                if (dateStr === this.pendingStart) return 'pending';
                if (this.periodIndexFor(dateStr) !== -1) return 'period';
                if (this.inPreview(dateStr)) return 'preview';
                return 'free';
            },
            cellStyle(cell) {
                const base = 'height:2.75rem;display:flex;align-items:center;justify-content:center;user-select:none;background:#fff;';
                if (! cell.current) return base + 'color:#cbd5e1;';
                const s = this.dayStatus(cell.date);
                if (s === 'period')  return base + 'background:#BAD5C6;color:#1f2937;font-weight:600;cursor:pointer;';
                if (s === 'pending') return base + 'background:#2D92CE;color:#fff;font-weight:600;cursor:pointer;';
                if (s === 'preview') return base + 'background:#DCEBE3;color:#1f2937;cursor:pointer;';
                return base + 'cursor:pointer;color:#374151;';
            },
            clickDay(cell) {
                if (! cell.current) return;
                const d = cell.date;
                const idx = this.periodIndexFor(d);
                if (idx !== -1) { this.state.splice(idx, 1); this.pendingStart = null; this.message = ''; return; }
                if (! this.pendingStart) { this.pendingStart = d; this.message = 'Выберите дату окончания периода'; return; }
                let start = this.pendingStart, end = d;
                if (end < start) { const t = start; start = end; end = t; }
                if (this.state.some(p => p.date_start && p.date_end && start <= p.date_end && end >= p.date_start)) {
                    this.message = 'Период пересекается с уже добавленным';
                    this.pendingStart = null;
                    return;
                }
                this.state.push({ date_start: start, date_end: end, price_event: null, price_pro: null });
                this.pendingStart = null;
                this.message = '';
            },
            get days() {
                const first = new Date(this.calYear, this.calMonth, 1);
                let lead = (first.getDay() + 6) % 7;
                const inMonth = new Date(this.calYear, this.calMonth + 1, 0).getDate();
                const prevDays = new Date(this.calYear, this.calMonth, 0).getDate();
                const cells = [];
                for (let i = lead - 1; i >= 0; i--) cells.push({ day: prevDays - i, current: false });
                for (let dd = 1; dd <= inMonth; dd++) {
                    const dateStr = this.calYear + '-' + String(this.calMonth + 1).padStart(2,'0') + '-' + String(dd).padStart(2,'0');
                    cells.push({ day: dd, current: true, date: dateStr });
                }
                let next = 1;
                while (cells.length % 7 !== 0) cells.push({ day: next++, current: false });
                return cells;
            },
            formatRu(dateStr) {
                if (! dateStr) return '—';
                const [y,m,d] = dateStr.split('-');
                return d + '.' + m + '.' + y;
            }
        }"
        style="max-width:640px;"
    >
        <style>
            .rc-price-input::-webkit-outer-spin-button,
            .rc-price-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
            .rc-price-input { -moz-appearance: textfield; appearance: textfield; }
        </style>

        {{-- Заголовок: месяц + переключение --}}
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
            <span style="font-weight:600;font-size:1.05rem;color:#1f2937;" x-text="monthLabel"></span>
            <div style="display:flex;gap:0.25rem;">
                <button type="button" x-on:click="prevMonth()"
                        style="width:2rem;height:2rem;border:1px solid #e5e7eb;background:#fff;color:#2D92CE;font-size:1.1rem;line-height:1;cursor:pointer;">‹</button>
                <button type="button" x-on:click="nextMonth()"
                        style="width:2rem;height:2rem;border:1px solid #e5e7eb;background:#fff;color:#2D92CE;font-size:1.1rem;line-height:1;cursor:pointer;">›</button>
            </div>
        </div>

        {{-- Легенда / подсказка --}}
        <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:0.5rem;font-size:0.8rem;color:#6b7280;">
            <span style="display:inline-flex;align-items:center;gap:0.35rem;"><span style="width:0.75rem;height:0.75rem;border-radius:9999px;background:#BAD5C6;display:inline-block;"></span> Период аренды</span>
            <span style="display:inline-flex;align-items:center;gap:0.35rem;"><span style="width:0.75rem;height:0.75rem;border-radius:9999px;background:#2D92CE;display:inline-block;"></span> Выбор начала</span>
            <span x-show="message" x-text="message" style="color:#2D92CE;font-weight:500;"></span>
        </div>

        {{-- Заголовки дней недели --}}
        <div style="display:grid;grid-template-columns:repeat(7,1fr);">
            <template x-for="wd in weekdays" :key="wd">
                <div style="text-align:center;font-weight:600;color:#374151;padding:0.35rem 0;font-size:0.85rem;" x-text="wd"></div>
            </template>
        </div>

        {{-- Сетка календаря --}}
        <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:1px;background:#e5e7eb;border:1px solid #e5e7eb;">
            <template x-for="(cell, i) in days" :key="i">
                <div :style="cellStyle(cell)"
                     x-on:click="clickDay(cell)"
                     x-on:mouseenter="hoverDate = cell.current ? cell.date : null"
                     x-on:mouseleave="hoverDate = null"
                     x-text="cell.day"></div>
            </template>
        </div>

        <p style="margin-top:0.5rem;font-size:0.8rem;color:#6b7280;">
            Кликните по дате начала, затем по дате окончания, чтобы добавить период. Клик по добавленному периоду удаляет его.
        </p>

        {{-- Список добавленных периодов с ценами --}}
        <div style="margin-top:1rem;" x-show="state.length > 0">
            <div style="font-weight:600;color:#1f2937;margin-bottom:0.5rem;">Периоды аренды и стоимость</div>
            <div style="display:flex;flex-direction:column;gap:0.5rem;">
                <template x-for="(period, i) in state" :key="i">
                    <div style="display:flex;flex-direction:column;gap:0.5rem;padding:0.5rem 0.75rem;background:#f9fafb;border:1px solid #e5e7eb;">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;">
                            <span style="font-weight:500;color:#1f2937;"
                                  x-text="formatRu(period.date_start) + ' — ' + formatRu(period.date_end)"></span>
                            <button type="button" x-on:click="state.splice(i, 1)"
                                    style="color:#dc2626;background:none;border:none;cursor:pointer;font-size:0.85rem;">Удалить</button>
                        </div>
                        <div class="flex justify-between md:flex-row flex-col gap-2">
                            <label style="display:flex;flex-direction:column;font-size:0.75rem;color:#6b7280;">
                                Стоимость для мероприятий, ₽/день
                                <input type="number" min="0" x-model="state[i].price_event" placeholder="напр. 50000"
                                    class="rc-price-input"
                                    style="border:1px solid #d1d5db;padding:0.3rem 0.5rem;width:100%;background:#fff;color:#111827;">
                            </label>
                            <label style="display:flex;flex-direction:column;font-size:0.75rem;color:#6b7280;">
                                Стоимость для профессиональных команд, ₽/день
                                <input type="number" min="0" x-model="state[i].price_pro" placeholder="напр. 70000"
                                    class="rc-price-input"
                                    style="border:1px solid #d1d5db;padding:0.3rem 0.5rem;width:100%;background:#fff;color:#111827;">
                            </label>
                        </div>

                    </div>
                </template>
            </div>
        </div>
    </div>
</x-dynamic-component>
