{{--
    Форма «Хочу в этот экипаж» (клубные регаты).

    Управляется Livewire ($isOpen / $entryInfo). Alpine — только закрытие по Esc
    и клику вне окна. Открывается событием 'open-crew-join' с id заявки
    (см. App\Livewire\CrewJoinModal).
--}}
<div>
    @if($isOpen && $entryInfo)
        <div
            class="fixed inset-0 z-[55] flex items-center justify-center p-4 bg-black/50 overflow-y-auto"
            @keydown.escape.window.capture.stop="$wire.closeModal()"
            @click="$event.stopPropagation(); if ($event.target === $event.currentTarget) $wire.closeModal()"
        >
            <div
                class="relative w-full max-w-[90vw] md:max-w-[520px] max-h-[85vh] overflow-y-auto bg-white shadow-xl"
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
                    <div class="text-sm text-white/70">Заявка в экипаж</div>
                    <h4 class="a-font text-xl md:text-2xl leading-tight mt-1">
                        {{ $entryInfo['team'] ?: 'Экипаж' }}
                    </h4>
                    <p class="text-sm text-white/70 mt-1">{{ $entryInfo['regatta'] }}</p>
                </div>

                <div class="p-6">
                    @if($submitted)
                        <div class="text-center py-6">
                            <p class="text-brand-dark text-lg font-semibold mb-2">Заявка отправлена</p>
                            <p class="text-brand-gray-light">
                                Экипаж получил ваши контакты и свяжется с вами. Ответ придёт на указанный e-mail.
                            </p>
                            <button type="button" wire:click="closeModal"
                                    class="mt-6 bg-brand-blue text-white py-2 px-6 hover:opacity-90 transition-opacity font-semibold cursor-pointer">
                                Закрыть
                            </button>
                        </div>
                    @else
                        @if(filled($entryInfo['conditions']))
                            <div class="mb-5 p-4 bg-brand-light-bg">
                                <p class="text-sm font-semibold text-brand-dark mb-1">Условия экипажа</p>
                                <p class="text-sm text-brand-gray whitespace-pre-line">{{ $entryInfo['conditions'] }}</p>
                            </div>
                        @endif

                        <form wire:submit="submit" class="space-y-4">
                            <div>
                                <label for="crew-join-name" class="block text-sm font-medium text-brand-dark mb-1">Имя и фамилия</label>
                                <input id="crew-join-name" type="text" wire:model="name"
                                       class="w-full border border-brand-border px-3 py-2 focus:outline-none focus:border-brand-blue">
                                @error('name') <p class="text-brand-red text-sm mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="crew-join-email" class="block text-sm font-medium text-brand-dark mb-1">E-mail</label>
                                <input id="crew-join-email" type="email" wire:model="email"
                                       class="w-full border border-brand-border px-3 py-2 focus:outline-none focus:border-brand-blue">
                                @error('email') <p class="text-brand-red text-sm mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="crew-join-phone" class="block text-sm font-medium text-brand-dark mb-1">Телефон</label>
                                <input id="crew-join-phone" type="tel" wire:model="phone"
                                       class="w-full border border-brand-border px-3 py-2 focus:outline-none focus:border-brand-blue">
                                @error('phone') <p class="text-brand-red text-sm mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="crew-join-message" class="block text-sm font-medium text-brand-dark mb-1">О себе и опыте</label>
                                <textarea id="crew-join-message" rows="4" wire:model="message"
                                          class="w-full border border-brand-border px-3 py-2 focus:outline-none focus:border-brand-blue"></textarea>
                                @error('message') <p class="text-brand-red text-sm mt-1">{{ $message }}</p> @enderror
                            </div>

                            @error('entry') <p class="text-brand-red text-sm">{{ $message }}</p> @enderror

                            <button type="submit"
                                    class="w-full bg-brand-blue text-white py-3 px-6 hover:opacity-90 transition-opacity font-semibold cursor-pointer"
                                    wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="submit">Отправить заявку</span>
                                <span wire:loading wire:target="submit">Отправляем…</span>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
