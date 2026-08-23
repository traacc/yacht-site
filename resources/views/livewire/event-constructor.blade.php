{{--
    «Конструктор мероприятия» (ТЗ 3-го этапа, п. 7).

    Три шага: параметры → подбор флота и расчёт → контакты. Всё считается на
    сервере (App\Livewire\EventConstructor + App\Services\EventPlanner):
    занятость яхт и тарифы живут в одном месте с «Арендой флота» и админкой.
--}}
<div>
    @php $planner = $this->planner(); @endphp

    @if ($planner->isEnabled())
        <div class="mb-12" id="event-constructor">
            <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Конструктор мероприятия</h2>

            @if ($planner->intro() !== '')
                <p class="text-brand-gray-light mb-6">{{ $planner->intro() }}</p>
            @endif

            {{-- Прогресс: видно, где ты и сколько осталось --}}
            @if ($step !== 'done')
                @php
                    $steps = ['params' => 'Параметры', 'fleet' => 'Флот и расчёт', 'contacts' => 'Заявка'];
                @endphp
                <div class="flex flex-wrap gap-x-3 gap-y-1 mb-6 text-sm">
                    @foreach ($steps as $key => $label)
                        <span class="{{ $key === $step ? 'text-[#2E325C] font-semibold' : 'text-brand-gray-light' }}">
                            {{ $loop->iteration }}. {{ $label }}
                        </span>
                        @if (! $loop->last)<span class="text-brand-gray-light">→</span>@endif
                    @endforeach
                </div>
            @endif

            {{-- ===== ШАГ 1. Параметры мероприятия ===== --}}
            @if ($step === 'params')
                <form wire:submit="plan" class="border border-[#C6C6C6] p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="block md:col-span-2">
                            <span class="block text-sm text-brand-gray-light mb-1">Название мероприятия</span>
                            <input type="text" wire:model="eventName" maxlength="255"
                                   placeholder="Например: Летний корпоратив «Ветер перемен»"
                                   class="block appearance-none border border-[#C6C6C6] w-full text-sm md:text-base p-3">
                            @error('eventName') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="block text-sm text-brand-gray-light mb-1">Что это</span>
                            <select wire:model="format"
                                    class="block appearance-none border border-[#C6C6C6] w-full text-sm md:text-base p-3 bg-white">
                                <option value="">Выберите вариант</option>
                                @foreach ($this->options('event_format') as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('format') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="block text-sm text-brand-gray-light mb-1">Активность на воде</span>
                            <select wire:model.live="activity"
                                    class="block appearance-none border border-[#C6C6C6] w-full text-sm md:text-base p-3 bg-white">
                                <option value="">Не выбрана</option>
                                @foreach ($planner->activities() as $option)
                                    <option value="{{ $option['title'] }}">
                                        {{ $option['title'] }}@if ($option['surcharge'] > 0) (+{{ $planner->money($option['surcharge']) }})@endif
                                    </option>
                                @endforeach
                                <option value="{{ \App\Livewire\EventConstructor::ACTIVITY_OTHER }}">Другое</option>
                            </select>
                        </label>

                        @if ($activity === \App\Livewire\EventConstructor::ACTIVITY_OTHER)
                            <label class="block md:col-span-2">
                                <span class="block text-sm text-brand-gray-light mb-1">Опишите активность на воде</span>
                                <input type="text" wire:model="activityOther" maxlength="255"
                                       placeholder="Прогулка, соревнования, фотосессия…"
                                       class="block appearance-none border border-[#C6C6C6] w-full text-sm md:text-base p-3">
                                @error('activityOther') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </label>
                        @endif

                        <label class="block md:col-span-2">
                            <span class="block text-sm text-brand-gray-light mb-1">Подробнее о мероприятии</span>
                            <textarea wire:model="details" maxlength="2000" rows="3"
                                      placeholder="Что должно получиться, чего ждёте от дня, особые пожелания"
                                      class="block appearance-none border border-[#C6C6C6] w-full text-sm md:text-base p-3"></textarea>
                            @error('details') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </label>

                        {{-- Возможные даты: по каждой считается свой вариант подбора --}}
                        <div class="md:col-span-2">
                            <span class="block text-sm text-brand-gray-light mb-1">
                                Возможные даты проведения (до {{ \App\Services\EventPlanner::MAX_DATES }})
                            </span>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                @foreach ($dates as $index => $date)
                                    <div class="flex gap-2" wire:key="date-{{ $index }}">
                                        <input type="date" wire:model="dates.{{ $index }}"
                                               min="{{ now()->toDateString() }}"
                                               class="block appearance-none border border-[#C6C6C6] w-full text-sm md:text-base p-3">
                                        @if (count($dates) > 1)
                                            <button type="button" wire:click="removeDate({{ $index }})"
                                                    class="px-3 border border-[#C6C6C6] text-brand-gray-light hover:text-[#2E325C] cursor-pointer"
                                                    aria-label="Убрать дату">&times;</button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            @error('dates.0') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            @error('dates') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            @if (count($dates) < \App\Services\EventPlanner::MAX_DATES)
                                <button type="button" wire:click="addDate"
                                        class="mt-2 text-[#2D92CE] font-semibold text-sm cursor-pointer">
                                    + Добавить дату
                                </button>
                            @endif
                        </div>

                        <label class="block">
                            <span class="block text-sm text-brand-gray-light mb-1">Всего участников</span>
                            <input type="number" wire:model="guestsTotal" min="1" max="500"
                                   class="block appearance-none border border-[#C6C6C6] w-full text-sm md:text-base p-3">
                            @error('guestsTotal') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="block text-sm text-brand-gray-light mb-1">Из них выйдут на воду</span>
                            <input type="number" wire:model="guestsAfloat" min="1" max="500"
                                   class="block appearance-none border border-[#C6C6C6] w-full text-sm md:text-base p-3">
                            @error('guestsAfloat') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            <span class="block text-xs text-brand-gray-light mt-1">
                                На одну яхту берём до {{ $planner->yachtCapacity() }}
                                {{ \App\Support\Plural::form($planner->yachtCapacity(), 'гостя', 'гостей', 'гостей') }}.
                            </span>
                        </label>

                        <label class="block">
                            <span class="block text-sm text-brand-gray-light mb-1">Начало водной части</span>
                            <input type="time" wire:model="startTime"
                                   class="block appearance-none border border-[#C6C6C6] w-full text-sm md:text-base p-3">
                            @error('startTime') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="block text-sm text-brand-gray-light mb-1">Времени на воде, часов</span>
                            <input type="number" wire:model="hoursAfloat" min="1" max="12"
                                   class="block appearance-none border border-[#C6C6C6] w-full text-sm md:text-base p-3">
                            @error('hoursAfloat') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            <span class="block text-xs text-brand-gray-light mt-1">
                                Минимальная продолжительность — {{ \App\Support\Plural::with($planner->minHours(), 'час', 'часа', 'часов') }}.
                            </span>
                        </label>

                        <label class="block">
                            <span class="block text-sm text-brand-gray-light mb-1">Фото- и видеосъёмка</span>
                            <select wire:model="media"
                                    class="block appearance-none border border-[#C6C6C6] w-full text-sm md:text-base p-3 bg-white">
                                @foreach ($this->options('media') as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block">
                            <span class="block text-sm text-brand-gray-light mb-1">Питание гостей</span>
                            <select wire:model="catering"
                                    class="block appearance-none border border-[#C6C6C6] w-full text-sm md:text-base p-3 bg-white">
                                @foreach ($this->options('catering') as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <div class="md:col-span-2">
                            <div class="privacy flex gap-4">
                                <label class="custom-checkbox">
                                    <input type="checkbox" wire:model.live="needsVenue"/>
                                    <span class="checkbox-box shrink-0"></span>
                                    <div class="text-sm text-brand-gray-light">Нужна площадка на берегу</div>
                                </label>
                            </div>

                            @if ($needsVenue && count($planner->venues()) > 0)
                                <label class="block mt-3">
                                    <span class="block text-sm text-brand-gray-light mb-1">Площадка</span>
                                    <select wire:model="venue"
                                            class="block appearance-none border border-[#C6C6C6] w-full text-sm md:text-base p-3 bg-white">
                                        <option value="">Подберите сами — самую дешёвую подходящую</option>
                                        @foreach ($planner->venues() as $option)
                                            <option value="{{ $option['title'] }}">
                                                {{ $option['title'] }}@if ($option['price'] !== null) — {{ $planner->money($option['price']) }}@endif
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif
                        </div>
                    </div>

                    <button type="submit"
                            class="mt-6 bg-[#2D92CE] text-white py-3 px-8 hover:bg-[#0074CC] transition-colors md:text-lg text-sm font-semibold cursor-pointer"
                            wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="plan">Подобрать яхты и рассчитать →</span>
                        <span wire:loading wire:target="plan">Считаем…</span>
                    </button>
                </form>
            @endif

            {{-- ===== ШАГ 2. Флот и расчёт ===== --}}
            @if ($step === 'fleet')
                @php
                    $variant = $this->variant();
                    $quote = $this->quote;
                    $needed = $this->needed();
                @endphp

                <div class="border border-[#C6C6C6] p-6">
                    <h3 class="a-font text-xl mb-4 text-[#2E325C]">Свободные яхты по вашим датам</h3>

                    <p class="text-brand-gray-light mb-4">
                        Под {{ \App\Support\Plural::with((int) $guestsAfloat, 'гостя', 'гостей', 'гостей') }} на воде
                        нужно {{ \App\Support\Plural::with($needed, 'яхта', 'яхты', 'яхт') }}.
                    </p>

                    {{-- Вариант на каждую перечисленную дату --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
                        @foreach ($this->variants as $item)
                            <button type="button" wire:click="chooseDate('{{ $item['key'] }}')"
                                    wire:key="variant-{{ $item['key'] }}"
                                    class="p-4 text-left border transition-colors cursor-pointer
                                           {{ $item['key'] === $selectedDate ? 'border-[#2D92CE] bg-[#2D92CE26]' : 'border-[#C6C6C6] hover:bg-brand-light-bg' }}">
                                <span class="block font-semibold text-[#2E325C]">{{ $item['date']->format('d.m.Y') }}</span>
                                <span class="block text-sm {{ $item['enough'] ? 'text-brand-gray-light' : 'text-red-600' }}">
                                    свободно {{ \App\Support\Plural::with($item['available'], 'яхта', 'яхты', 'яхт') }}
                                    @unless ($item['enough']) из {{ $needed }} @endunless
                                </span>
                            </button>
                        @endforeach
                    </div>

                    @if ($variant === null)
                        <p class="text-brand-gray-light">Выберите дату из списка.</p>
                    @else
                        @unless ($variant['enough'])
                            <p class="bg-yellow-50 border border-yellow-300 text-[#2E325C] p-4 mb-6">
                                На {{ $variant['date']->format('d.m.Y') }} свободно
                                {{ \App\Support\Plural::with($variant['available'], 'яхта', 'яхты', 'яхт') }} —
                                меньше, чем нужно ({{ $needed }}). Выберите другую дату или отправьте запрос:
                                подберём флот вручную, в том числе у партнёров.
                            </p>
                        @endunless

                        @if ($variant['yachts']->isNotEmpty())
                            <p class="text-sm text-brand-gray-light mb-3">
                                Отмечены самые доступные яхты — состав можно изменить.
                            </p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                                @foreach ($variant['yachts'] as $yacht)
                                    @php
                                        $checked = in_array((string) $yacht->getKey(), $selectedYachts, true);
                                        $cost = $planner->yachtCost($yacht, $variant['date'], (int) $hoursAfloat);
                                    @endphp
                                    <button type="button" wire:click="toggleYacht('{{ $yacht->getKey() }}')"
                                            wire:key="yacht-{{ $yacht->getKey() }}"
                                            class="p-4 text-left border transition-colors cursor-pointer
                                                   {{ $checked ? 'border-[#2D92CE] bg-[#2D92CE26]' : 'border-[#C6C6C6] hover:bg-brand-light-bg' }}">
                                        <span class="block font-semibold text-[#2E325C]">{{ $yacht->name }}</span>
                                        <span class="block text-sm text-brand-gray-light">{{ $yacht->class ?? 'Carter 30' }}</span>
                                        <span class="block text-sm mt-1">{{ $planner->money($cost) }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @else
                            <p class="text-brand-gray-light mb-6">
                                На эту дату свободных яхт нет. Выберите другую дату или отправьте запрос — подберём вручную.
                            </p>
                        @endif

                        {{-- Смета: каждая позиция названа, сумма — минимальная --}}
                        @if ($quote !== null && count($quote['items']) > 0)
                            <div class="bg-brand-light-bg p-6 mb-6">
                                <h4 class="a-font text-lg mb-3 text-[#2E325C]">Ориентировочная стоимость</h4>

                                <div class="divide-y divide-[#C6C6C6]">
                                    @foreach ($quote['items'] as $item)
                                        <div class="flex justify-between gap-4 py-2">
                                            <span class="text-sm text-brand-gray">
                                                {{ $item['title'] }}
                                                @if ($item['note'] !== '')
                                                    <span class="block text-xs text-brand-gray-light">{{ $item['note'] }}</span>
                                                @endif
                                            </span>
                                            <span class="text-sm whitespace-nowrap {{ $item['amount'] === null ? 'text-brand-gray-light' : 'font-semibold' }}">
                                                {{ $planner->money($item['amount']) }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>

                                {{-- Итог «по запросу», когда ни одна позиция не
                                     тарифицирована: ноль ввёл бы в заблуждение. --}}
                                <div class="flex justify-between gap-4 pt-4 mt-2 border-t-2 border-[#2E325C]">
                                    <span class="font-semibold text-[#2E325C]">
                                        {{ $quote['total'] > 0 ? 'Итого от' : 'Итого' }}
                                    </span>
                                    <span class="text-xl font-semibold text-[#2D92CE] whitespace-nowrap">
                                        {{ $planner->money($quote['total'] > 0 ? $quote['total'] : null) }}
                                    </span>
                                </div>

                                @if ($quote['has_unpriced'])
                                    <p class="text-xs text-brand-gray-light mt-2">
                                        Часть позиций отмечена «по запросу» — их стоимость менеджер подтвердит отдельно.
                                    </p>
                                @endif
                            </div>
                        @endif

                        @if ($planner->note() !== '')
                            <p class="text-sm text-brand-gray-light mb-6">{{ $planner->note() }}</p>
                        @endif
                    @endif

                    <div class="flex flex-wrap gap-3">
                        <button type="button" wire:click="back"
                                class="border border-[#C6C6C6] text-[#2E325C] py-3 px-8 hover:bg-brand-light-bg transition-colors font-semibold cursor-pointer">
                            ← Изменить параметры
                        </button>
                        <button type="button" wire:click="toContacts"
                                class="bg-[#2D92CE] text-white py-3 px-8 hover:bg-[#0074CC] transition-colors font-semibold cursor-pointer">
                            Отправить запрос →
                        </button>
                    </div>
                </div>
            @endif

            {{-- ===== ШАГ 3. Контакты ===== --}}
            @if ($step === 'contacts')
                @php $quote = $this->quote; @endphp

                <form wire:submit="submit" class="border border-[#C6C6C6] p-6">
                    <h3 class="a-font text-xl mb-2 text-[#2E325C]">Куда прислать ответ</h3>

                    <p class="text-brand-gray-light text-sm mb-4">
                        {{ $eventName }}@if ($this->variant()), {{ $this->variant()['date']->format('d.m.Y') }}@endif,
                        {{ \App\Support\Plural::with($this->selectedFleet()->count(), 'яхта', 'яхты', 'яхт') }}@if ($quote !== null),
                        от {{ $planner->money($quote['total']) }}@endif
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <label class="block">
                            <span class="block text-sm text-brand-gray-light mb-1">Ваше имя</span>
                            <input type="text" wire:model="name" maxlength="255"
                                   class="block appearance-none border border-[#C6C6C6] w-full text-sm md:text-base p-3">
                            @error('name') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="block text-sm text-brand-gray-light mb-1">Телефон</span>
                            <input type="tel" wire:model="phone" maxlength="50"
                                   class="block appearance-none border border-[#C6C6C6] w-full text-sm md:text-base p-3">
                            @error('phone') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="block text-sm text-brand-gray-light mb-1">Email</span>
                            <input type="email" wire:model="email" maxlength="255"
                                   class="block appearance-none border border-[#C6C6C6] w-full text-sm md:text-base p-3">
                            @error('email') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </label>

                        <label class="block md:col-span-3">
                            <span class="block text-sm text-brand-gray-light mb-1">Комментарий</span>
                            <textarea wire:model="comment" maxlength="2000" rows="3"
                                      class="block appearance-none border border-[#C6C6C6] w-full text-sm md:text-base p-3"></textarea>
                            @error('comment') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    <div class="privacy flex gap-4 mt-4">
                        <label class="custom-checkbox">
                            <input type="checkbox" wire:model="privacy"/>
                            <span class="checkbox-box shrink-0"></span>
                            <div class="text-sm text-brand-gray-light">
                                Отправляя данные через форму, вы соглашаетесь с
                                <a class="underline" href="/files/Политика_обработки_персональных_данных_1.pdf">политикой обработки персональных данных</a>
                            </div>
                        </label>
                    </div>
                    @error('privacy') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror

                    <div class="flex flex-wrap gap-3 mt-6">
                        <button type="button" wire:click="back"
                                class="border border-[#C6C6C6] text-[#2E325C] py-3 px-8 hover:bg-brand-light-bg transition-colors font-semibold cursor-pointer">
                            ← Назад к подбору
                        </button>
                        <button type="submit"
                                class="bg-[#2D92CE] text-white py-3 px-8 hover:bg-[#0074CC] transition-colors font-semibold cursor-pointer"
                                wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="submit">Отправить запрос</span>
                            <span wire:loading wire:target="submit">Отправляем…</span>
                        </button>
                    </div>
                </form>
            @endif

            {{-- ===== Готово ===== --}}
            @if ($step === 'done')
                <div class="border border-[#2D92CE] bg-[#2D92CE26] p-6">
                    <h3 class="a-font text-xl mb-2 text-[#2E325C]">Запрос отправлен</h3>
                    <p class="text-brand-gray mb-4">
                        Мы получили параметры мероприятия и подобранный флот. Копия расчёта ушла вам на почту —
                        менеджер свяжется и подтвердит итоговую стоимость.
                    </p>
                    <button type="button" wire:click="restart"
                            class="bg-[#2D92CE] text-white py-3 px-8 hover:bg-[#0074CC] transition-colors font-semibold cursor-pointer">
                        Собрать ещё одно мероприятие
                    </button>
                </div>
            @endif
        </div>
    @endif
</div>
