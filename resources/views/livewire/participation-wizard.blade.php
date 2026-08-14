{{--
    Мастер «Хочу участвовать»: вариант участия → тип регаты → регата →
    лодка или число мест → заявка.

    Управляется Livewire ($isOpen / $step). Alpine — только закрытие по Esc и
    клику вне окна. Открывается событием 'open-participation-wizard'
    (см. App\Livewire\ParticipationWizard).
--}}
<div>
    @if($isOpen)
        @php
            $steps = [
                'kind' => 'Участие',
                'regatta' => 'Регата',
                'boat' => $this->needsSeatPicker() ? 'Места' : 'Лодка',
                'form' => 'Заявка',
            ];
            $currentIndex = array_search($step, array_keys($steps), true);
        @endphp

        <div
            class="fixed inset-0 z-[55] flex items-center justify-center p-4 bg-black/50 overflow-y-auto"
            @keydown.escape.window.capture.stop="$wire.closeModal()"
            @click="$event.stopPropagation(); if ($event.target === $event.currentTarget) $wire.closeModal()"
        >
            <div
                class="relative w-full max-w-[90vw] md:max-w-[640px] max-h-[85vh] overflow-y-auto bg-white shadow-xl"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
            >
                <button
                    wire:click="closeModal"
                    class="absolute top-3 right-4 text-2xl font-bold leading-none text-white transition-colors cursor-pointer z-10"
                    aria-label="Закрыть"
                >&times;</button>

                <div class="p-6 bg-[#2E325C] text-white">
                    <h4 class="a-font text-xl md:text-2xl leading-tight">Хочу участвовать</h4>

                    {{-- Прогресс по шагам: видно, где ты и сколько осталось --}}
                    @if($step !== 'done')
                        <div class="flex flex-wrap gap-x-2 gap-y-1 mt-3 text-xs">
                            @foreach($steps as $key => $label)
                                <span class="{{ $key === $step ? 'text-white font-semibold' : 'text-white/50' }}">
                                    {{ $loop->iteration }}. {{ $label }}
                                </span>
                                @if(! $loop->last)<span class="text-white/30">→</span>@endif
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="p-6">
                    {{-- ШАГ 1. Вариант участия --}}
                    @if($step === 'kind')
                        <p class="text-brand-gray mb-4">Как хотите участвовать?</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <button type="button" wire:click="chooseKind('crew')"
                                    class="p-5 bg-brand-light-bg hover:bg-[#2D92CE26] text-left transition-colors cursor-pointer">
                                <span class="block text-brand-dark font-semibold text-lg">Экипажем</span>
                                <span class="block text-sm text-brand-gray-light mt-1">Своя команда на своей или арендованной лодке</span>
                            </button>
                            <button type="button" wire:click="chooseKind('individual')"
                                    class="p-5 bg-brand-light-bg hover:bg-[#2D92CE26] text-left transition-colors cursor-pointer">
                                <span class="block text-brand-dark font-semibold text-lg">Индивидуально</span>
                                <span class="block text-sm text-brand-gray-light mt-1">Место на лодке ассоциации или в чужом экипаже</span>
                            </button>
                        </div>

                    {{-- ШАГ 2. Выбор регаты --}}
                    @elseif($step === 'regatta')
                        <p class="text-brand-gray mb-4">Регаты, куда можно заявиться:</p>

                        @forelse($this->regattas() as $item)
                            @php
                                // Зарубежные регаты живут в «Услугах» со своей формой заявки
                                // (место в каюте, каюта, яхта целиком) — ведём прямо в карточку.
                                $isForeign = $item['source'] === \App\Services\ParticipationOptions::SOURCE_FOREIGN;
                                // Тип берётся из самой регаты: списка по типам больше нет.
                                $isClub = $item['type'] === \App\Enums\RegattaType::Club->value;
                                $isTravel = $item['type'] === \App\Enums\RegattaType::Travel->value;
                            @endphp
                            <{{ $isForeign ? 'a' : 'button' }}
                                @if($isForeign) href="{{ $item['url'] }}" @else type="button" wire:click="chooseRegatta('{{ $item['id'] }}')" @endif
                                class="w-full flex items-start gap-3 p-4 mb-2 bg-brand-light-bg hover:bg-[#2D92CE26] text-left transition-colors cursor-pointer">
                                <span class="size-3 rounded-full inline-block mt-1.5 shrink-0 {{ $item['background_class'] }}"></span>
                                <span class="flex-1">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <span class="text-brand-dark font-semibold">{{ $item['name'] }}</span>
                                        <span class="text-[10px] uppercase tracking-wide text-white px-2 py-0.5 {{ $item['background_class'] }}">{{ $item['type_label'] }}</span>
                                    </span>
                                    <span class="block text-sm text-brand-gray-light">
                                        {{ $item['dates'] }}@if($item['location']) · {{ $item['location'] }}@endif
                                    </span>
                                    <span class="block text-sm text-brand-blue mt-1">
                                        @if($isForeign)
                                            @if($item['seat_price'])Место — {{ number_format((float) $item['seat_price'], 0, ',', ' ') }} ₽ · @endif Подробности и заявка →
                                        @elseif($kind === 'individual' && $isClub)
                                            Экипажей с добором: {{ $item['crews_count'] }}
                                        @elseif($kind === 'individual')
                                            @if($item['seat_price'])
                                                Место — {{ number_format((float) $item['seat_price'], 0, ',', ' ') }} ₽
                                            @else
                                                Место в экипаже · цену уточним в заявке
                                            @endif
                                        @else
                                            @if($item['yachts_count'] > 0)Свободных лодок: {{ $item['yachts_count'] }}@endif
                                            @if($item['boat_price'])@if($item['yachts_count'] > 0) · @endif Лодка — {{ number_format((float) $item['boat_price'], 0, ',', ' ') }} ₽@endif
                                            @if($item['yachts_count'] === 0 && ! $item['boat_price'])
                                                {{ $isTravel ? 'Лодка целиком · цену уточним в заявке' : 'Заявка со своей лодкой' }}
                                            @endif
                                        @endif
                                    </span>
                                </span>
                            </{{ $isForeign ? 'a' : 'button' }}>
                        @empty
                            {{-- Разные причины пустого списка: регат нет вовсе или мест в них не осталось --}}
                            @php($empty = $this->emptyState())
                            <div class="py-6 text-center">
                                <p class="text-brand-dark font-semibold">{{ $empty['title'] }}</p>
                                <p class="text-brand-gray-light text-sm mt-1">{{ $empty['hint'] }}</p>
                                <div class="flex flex-wrap gap-3 justify-center mt-4">
                                    <button type="button" wire:click="back"
                                            class="px-4 py-2 bg-brand-light-bg text-brand-dark text-sm font-semibold cursor-pointer hover:bg-[#2D92CE26] transition-colors">
                                        Другой вариант участия
                                    </button>
                                    <a href="{{ route('competitions') }}"
                                       class="px-4 py-2 bg-brand-blue text-white text-sm font-semibold hover:opacity-90 transition-opacity">
                                        Календарь регат
                                    </a>
                                </div>
                            </div>
                        @endforelse

                        {{-- В пустом состоянии кнопка возврата уже есть в подсказке --}}
                        @if($this->regattas() !== [])
                            <button type="button" wire:click="back" class="mt-2 text-sm text-brand-gray-light hover:text-brand-dark cursor-pointer">← Назад</button>
                        @endif

                    {{-- ШАГ 3. Лодка, экипаж или количество мест --}}
                    @elseif($step === 'boat')
                        @if($kind === 'individual' && $type === 'club')
                            <p class="text-brand-gray mb-4">Выберите экипаж, который набирает людей:</p>

                            @forelse($this->crews() as $crew)
                                <button type="button" wire:click="chooseCrew('{{ $crew['id'] }}')"
                                        class="w-full p-4 mb-2 bg-brand-light-bg hover:bg-[#2D92CE26] text-left transition-colors cursor-pointer">
                                    <span class="block text-brand-dark font-semibold">{{ $crew['title'] }}</span>
                                    <span class="block text-sm text-brand-gray-light">В экипаже уже {{ $crew['taken'] }} чел.</span>
                                    @if($crew['conditions'])
                                        <span class="block text-sm text-brand-gray mt-1">{{ \Illuminate\Support\Str::limit($crew['conditions'], 120) }}</span>
                                    @endif
                                </button>
                            @empty
                                <p class="text-brand-gray-light py-6 text-center">Экипажи этой регаты пока никого не набирают.</p>
                            @endforelse

                        @elseif($this->needsSeatPicker())
                            <p class="text-brand-gray mb-4">
                                {{ $type === 'travel' ? 'Сколько мест в экипаже нужно?' : 'Сколько мест нужно?' }}
                            </p>

                            <div class="flex items-center justify-center gap-6 py-4">
                                <button type="button" wire:click="setSeats({{ $seats - 1 }})"
                                        class="size-11 bg-brand-light-bg text-brand-dark text-2xl leading-none cursor-pointer hover:bg-[#2D92CE26] transition-colors"
                                        @disabled($seats <= 1)>−</button>
                                <span class="text-4xl a-font text-brand-dark w-12 text-center">{{ $seats }}</span>
                                <button type="button" wire:click="setSeats({{ $seats + 1 }})"
                                        class="size-11 bg-brand-light-bg text-brand-dark text-2xl leading-none cursor-pointer hover:bg-[#2D92CE26] transition-colors">+</button>
                            </div>

                            @if($this->price())
                                <p class="text-center text-brand-dark font-semibold mb-4">
                                    Итого: {{ number_format($this->price(), 0, ',', ' ') }} ₽
                                </p>
                            @else
                                <p class="text-center text-brand-gray-light text-sm mb-4">
                                    Стоимость подтвердит организатор — она зависит от условий принимающей стороны.
                                </p>
                            @endif

                            <button type="button" wire:click="confirmSeats"
                                    class="w-full bg-brand-blue text-white py-3 px-6 hover:opacity-90 transition-opacity font-semibold cursor-pointer">
                                Продолжить
                            </button>

                        @elseif($type === 'travel')
                            {{-- Выездная: флот даёт принимающая сторона, выбирать не из чего --}}
                            <p class="text-brand-gray mb-4">Как идёте на регату партнёров?</p>

                            <button type="button" wire:click="skipYacht"
                                    class="w-full p-4 mb-2 bg-brand-light-bg hover:bg-[#2D92CE26] text-left transition-colors cursor-pointer">
                                <span class="block text-brand-dark font-semibold">Лодка целиком</span>
                                <span class="block text-sm text-brand-gray-light">
                                    @if($this->regatta()?->boat_price)
                                        {{ number_format((float) $this->regatta()->boat_price, 0, ',', ' ') }} ₽ · лодку подбирает организатор
                                    @else
                                        Лодку и стоимость подберёт организатор
                                    @endif
                                </span>
                            </button>

                        @else
                            <p class="text-brand-gray mb-4">На какой лодке пойдёте?</p>

                            @if($type === 'club')
                                <button type="button" wire:click="chooseYacht('{{ \App\Livewire\ParticipationWizard::OWN_YACHT }}')"
                                        class="w-full p-4 mb-2 bg-brand-light-bg hover:bg-[#2D92CE26] text-left transition-colors cursor-pointer">
                                    <span class="block text-brand-dark font-semibold">Своя лодка</span>
                                    <span class="block text-sm text-brand-gray-light">Перейти к обычной заявке экипажа</span>
                                </button>
                            @endif

                            @foreach($this->yachts() as $yacht)
                                <button type="button" wire:click="chooseYacht('{{ $yacht['id'] }}')"
                                        class="w-full p-4 mb-2 bg-brand-light-bg hover:bg-[#2D92CE26] text-left transition-colors cursor-pointer">
                                    <span class="block text-brand-dark font-semibold">{{ $yacht['name'] }}</span>
                                    <span class="block text-sm text-brand-gray-light">
                                        @if($yacht['vfps']) № {{ $yacht['vfps'] }} · @endif
                                        {{ $type === 'club' ? 'Свободна на даты регаты — аренда' : 'Свободна на даты регаты' }}
                                    </span>
                                </button>
                            @endforeach

                            @if($type === 'regular')
                                <button type="button" wire:click="skipYacht"
                                        class="w-full p-4 mb-2 bg-brand-light-bg hover:bg-[#2D92CE26] text-left transition-colors cursor-pointer">
                                    <span class="block text-brand-dark font-semibold">Любая свободная лодка</span>
                                    <span class="block text-sm text-brand-gray-light">Лодку назначит ассоциация</span>
                                </button>
                            @elseif($this->yachts() === [])
                                <p class="text-sm text-brand-gray-light mt-2">Свободных лодок в аренду на эти даты нет.</p>
                            @endif
                        @endif

                        <button type="button" wire:click="back" class="mt-2 text-sm text-brand-gray-light hover:text-brand-dark cursor-pointer">← Назад</button>

                    {{-- ШАГ 4. Заявка --}}
                    @elseif($step === 'form')
                        <div class="mb-5 p-4 bg-brand-light-bg text-sm text-brand-gray space-y-1">
                            <p class="text-brand-dark font-semibold">{{ $this->regatta()?->name }}</p>
                            <p>{{ $this->regatta()?->dateRange() }}</p>
                            @if($this->isRentalBranch())
                                <p>Аренда лодки на даты регаты. Заявку на регату подадите после подтверждения брони.</p>
                            @elseif($kind === 'individual')
                                <p>{{ $type === 'travel' ? 'Мест в экипаже' : 'Мест' }}: <span class="font-semibold">{{ $seats }}</span></p>
                            @else
                                <p>{{ $type === 'travel' ? 'Лодка целиком на регате партнёров' : 'Участие экипажем' }}</p>
                            @endif
                            @if($this->price())
                                <p>Итого: <span class="font-semibold">{{ number_format($this->price(), 0, ',', ' ') }} ₽</span></p>
                            @elseif($type === 'travel')
                                <p>Стоимость подтвердит организатор.</p>
                            @endif
                        </div>

                        <form wire:submit="submit" class="space-y-4">
                            <div>
                                <label for="wizard-name" class="block text-sm font-medium text-brand-dark mb-1">Имя и фамилия</label>
                                <input id="wizard-name" type="text" wire:model="name"
                                       class="w-full border border-brand-border px-3 py-2 focus:outline-none focus:border-brand-blue">
                                @error('name') <p class="text-brand-red text-sm mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="wizard-email" class="block text-sm font-medium text-brand-dark mb-1">E-mail</label>
                                <input id="wizard-email" type="email" wire:model="email"
                                       class="w-full border border-brand-border px-3 py-2 focus:outline-none focus:border-brand-blue">
                                @error('email') <p class="text-brand-red text-sm mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="wizard-phone" class="block text-sm font-medium text-brand-dark mb-1">Телефон</label>
                                <input id="wizard-phone" type="tel" wire:model="phone"
                                       class="w-full border border-brand-border px-3 py-2 focus:outline-none focus:border-brand-blue">
                                @error('phone') <p class="text-brand-red text-sm mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="wizard-comment" class="block text-sm font-medium text-brand-dark mb-1">Комментарий</label>
                                <textarea id="wizard-comment" rows="3" wire:model="comment"
                                          class="w-full border border-brand-border px-3 py-2 focus:outline-none focus:border-brand-blue"></textarea>
                                @error('comment') <p class="text-brand-red text-sm mt-1">{{ $message }}</p> @enderror
                            </div>

                            @if($this->isRentalBranch())
                                <label class="flex items-start gap-2 cursor-pointer">
                                    <input type="checkbox" wire:model="agreement" class="mt-1 border-gray-300 text-brand-blue">
                                    <span class="text-sm text-brand-gray">Согласен с условиями аренды лодки</span>
                                </label>
                                @error('agreement') <p class="text-brand-red text-sm">{{ $message }}</p> @enderror
                            @endif

                            @error('regatta') <p class="text-brand-red text-sm">{{ $message }}</p> @enderror
                            @error('seats') <p class="text-brand-red text-sm">{{ $message }}</p> @enderror
                            @error('participation_kind') <p class="text-brand-red text-sm">{{ $message }}</p> @enderror

                            <div class="flex gap-3">
                                <button type="button" wire:click="back"
                                        class="px-4 py-3 bg-brand-light-bg text-brand-dark font-semibold cursor-pointer hover:bg-[#2D92CE26] transition-colors">
                                    Назад
                                </button>
                                <button type="submit"
                                        class="flex-1 bg-brand-blue text-white py-3 px-6 hover:opacity-90 transition-opacity font-semibold cursor-pointer"
                                        wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="submit">Отправить заявку</span>
                                    <span wire:loading wire:target="submit">Отправляем…</span>
                                </button>
                            </div>
                        </form>

                    {{-- Готово --}}
                    @elseif($step === 'done')
                        <div class="text-center py-6">
                            <p class="text-brand-dark text-lg font-semibold mb-2">Заявка отправлена</p>
                            <p class="text-brand-gray-light">
                                @if($this->isRentalBranch())
                                    Отдел заказов свяжется с вами по аренде лодки на даты регаты.
                                @else
                                    Заявку рассмотрит администратор ассоциации. Ответ придёт на указанный e-mail.
                                @endif
                            </p>
                            <button type="button" wire:click="closeModal"
                                    class="mt-6 bg-brand-blue text-white py-2 px-6 hover:opacity-90 transition-opacity font-semibold cursor-pointer">
                                Закрыть
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
