{{--
    Заявка на регулярную или выездную регату (экипажем или индивидуально).

    Управляется Livewire ($isOpen / $regattaInfo). Alpine — только закрытие
    по Esc и клику вне окна. Открывается событием 'open-seat-entry' с id регаты
    (см. App\Livewire\SeatEntryModal).
--}}
<div>
    @if($isOpen && $regattaInfo)
        <div
            class="fixed inset-0 z-[55] flex items-center justify-center p-4 bg-black/50 overflow-y-auto"
            @keydown.escape.window.capture.stop="$wire.closeModal()"
            @click="$event.stopPropagation(); if ($event.target === $event.currentTarget) $wire.closeModal()"
        >
            <div
                class="relative w-full max-w-[90vw] md:max-w-[560px] max-h-[85vh] overflow-y-auto bg-white shadow-xl"
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
                    <div class="text-sm text-white/70">{{ $regattaInfo['type_label'] }} · заявка</div>
                    <h4 class="a-font text-xl md:text-2xl leading-tight mt-1">{{ $regattaInfo['name'] }}</h4>
                    <p class="text-sm text-white/70 mt-1">{{ $regattaInfo['dates'] }}</p>
                </div>

                <div class="p-6">
                    @if($submitted)
                        <div class="text-center py-6">
                            <p class="text-brand-dark text-lg font-semibold mb-2">Заявка отправлена</p>
                            <p class="text-brand-gray-light">
                                Заявку рассмотрит администратор ассоциации. Ответ придёт на указанный e-mail,
                                а зарегистрированным участникам — ещё и в личный кабинет.
                            </p>
                            <button type="button" wire:click="closeModal"
                                    class="mt-6 bg-brand-blue text-white py-2 px-6 hover:opacity-90 transition-opacity font-semibold cursor-pointer">
                                Закрыть
                            </button>
                        </div>
                    @else
                        {{-- Условия участия: длительность и цены --}}
                        <div class="mb-5 p-4 bg-brand-light-bg text-sm text-brand-gray space-y-1">
                            @if($regattaInfo['race_days'])
                                <p>Гоночных дней: <span class="font-semibold">{{ $regattaInfo['race_days'] }}</span>@if($regattaInfo['race_hours_per_day']), по {{ rtrim(rtrim(number_format((float) $regattaInfo['race_hours_per_day'], 1, ',', ' '), '0'), ',') }} ч в день@endif</p>
                            @endif
                            @if($regattaInfo['seat_price'])
                                <p>Место: <span class="font-semibold">{{ number_format((float) $regattaInfo['seat_price'], 0, ',', ' ') }} ₽</span></p>
                            @endif
                            @if($regattaInfo['boat_price'])
                                <p>Лодка целиком: <span class="font-semibold">{{ number_format((float) $regattaInfo['boat_price'], 0, ',', ' ') }} ₽</span></p>
                            @endif
                            @if($regattaInfo['crew_limit'])
                                <p>Мест в экипаже: <span class="font-semibold">{{ $regattaInfo['crew_limit'] }}</span></p>
                            @endif
                        </div>

                        <form wire:submit="submit" class="space-y-4">
                            @if($regattaInfo['allows_individual'])
                                <div>
                                    <span class="block text-sm font-medium text-brand-dark mb-2">Как участвуете</span>
                                    <div class="flex gap-2">
                                        <button type="button" wire:click="$set('kind', 'individual')"
                                                class="flex-1 px-3 py-2 text-sm cursor-pointer transition-colors {{ $kind === 'individual' ? 'bg-brand-blue text-white' : 'bg-brand-light-bg text-brand-dark' }}">
                                            Индивидуально
                                        </button>
                                        <button type="button" wire:click="$set('kind', 'crew')"
                                                class="flex-1 px-3 py-2 text-sm cursor-pointer transition-colors {{ $kind === 'crew' ? 'bg-brand-blue text-white' : 'bg-brand-light-bg text-brand-dark' }}">
                                            Экипажем
                                        </button>
                                    </div>
                                </div>
                            @endif

                            <div>
                                <label for="seat-entry-name" class="block text-sm font-medium text-brand-dark mb-1">Имя и фамилия</label>
                                <input id="seat-entry-name" type="text" wire:model="name"
                                       class="w-full border border-brand-border px-3 py-2 focus:outline-none focus:border-brand-blue">
                                @error('name') <p class="text-brand-red text-sm mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="seat-entry-email" class="block text-sm font-medium text-brand-dark mb-1">E-mail</label>
                                <input id="seat-entry-email" type="email" wire:model="email"
                                       class="w-full border border-brand-border px-3 py-2 focus:outline-none focus:border-brand-blue">
                                @error('email') <p class="text-brand-red text-sm mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="seat-entry-phone" class="block text-sm font-medium text-brand-dark mb-1">Телефон</label>
                                <input id="seat-entry-phone" type="tel" wire:model="phone"
                                       class="w-full border border-brand-border px-3 py-2 focus:outline-none focus:border-brand-blue">
                                @error('phone') <p class="text-brand-red text-sm mt-1">{{ $message }}</p> @enderror
                            </div>

                            @if($kind === 'crew')
                                <div class="border-t border-brand-border pt-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-sm font-medium text-brand-dark">Остальные участники экипажа</span>
                                        <button type="button" wire:click="addCrewMember"
                                                class="text-brand-blue text-sm font-semibold hover:underline cursor-pointer">
                                            + Добавить
                                        </button>
                                    </div>
                                    <p class="text-xs text-brand-gray-light mb-3">
                                        Вы записаны рулевым@if($regattaInfo['crew_limit']) — вместе с вами не более {{ $regattaInfo['crew_limit'] }} чел @endif
                                    </p>

                                    @foreach($crew as $index => $member)
                                        <div class="flex gap-2 mb-2" wire:key="crew-{{ $index }}">
                                            <input type="text" wire:model="crew.{{ $index }}.name" placeholder="Имя"
                                                   class="flex-1 border border-brand-border px-3 py-2 text-sm focus:outline-none focus:border-brand-blue">
                                            <input type="email" wire:model="crew.{{ $index }}.email" placeholder="E-mail"
                                                   class="flex-1 border border-brand-border px-3 py-2 text-sm focus:outline-none focus:border-brand-blue">
                                            <button type="button" wire:click="removeCrewMember({{ $index }})"
                                                    class="px-3 text-brand-red font-bold cursor-pointer" aria-label="Удалить участника">&times;</button>
                                        </div>
                                        @error('crew.'.$index.'.email') <p class="text-brand-red text-sm mb-2">{{ $message }}</p> @enderror
                                    @endforeach
                                </div>
                            @endif

                            @error('regatta') <p class="text-brand-red text-sm">{{ $message }}</p> @enderror
                            @error('participation_kind') <p class="text-brand-red text-sm">{{ $message }}</p> @enderror
                            @error('crew') <p class="text-brand-red text-sm">{{ $message }}</p> @enderror

                            <button type="submit"
                                    class="w-full bg-brand-blue text-white py-3 px-6 hover:opacity-90 transition-opacity font-semibold cursor-pointer"
                                    wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="submit">Подать заявку</span>
                                <span wire:loading wire:target="submit">Отправляем…</span>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
