{{--
    Экипаж заявки на регату.
    Управляется через Livewire ($isOpen / $entry). Alpine — только для закрытия по Esc/клику вне окна.

    Открывается событием 'open-entry-crew' с id заявки (см. App\Livewire\EntryCrewModal).
    Участники экипажа кликабельны и открывают карточку пользователя (open-user-card).
--}}
<div>
    @if($isOpen && $entry)
        <div
            class="fixed inset-0 z-[55] flex items-center justify-center p-4 bg-black/50 overflow-y-auto"
            @keydown.escape.window.capture.stop="$wire.closeModal()"
            @click="$event.stopPropagation(); if ($event.target === $event.currentTarget) $wire.closeModal()"
        >
            <div
                class="relative w-full max-w-[90vw] md:max-w-[480px] max-h-[85vh] overflow-y-auto bg-white shadow-xl"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
            >
                <button
                    wire:click="closeModal"
                    class="absolute top-3 right-4 text-2xl font-bold leading-none text-white transition-colors cursor-pointer z-10"
                    aria-label="Закрыть"
                >&times;</button>

                {{-- Шапка: яхта --}}
                <div class="p-6 bg-[#2E325C] text-white">
                    <div class="text-sm text-white/70">Экипаж</div>
                    <h4 class="a-font text-xl md:text-2xl leading-tight mt-1">{{ $entry['yacht'] }}</h4>
                </div>

                <div class="p-6">
                    @if(!empty($entry['crew']))
                        <ul class="divide-y divide-brand-border">
                            @foreach($entry['crew'] as $member)
                                <li>
                                    <{{ $member['id'] ? 'button' : 'div' }}
                                        @if($member['id'])
                                            type="button"
                                            wire:click="$dispatch('open-user-card', { userId: '{{ $member['id'] }}' })"
                                        @endif
                                        class="flex items-center gap-3 w-full py-3 text-left {{ $member['id'] ? 'hover:text-[#2D92CE] transition-colors cursor-pointer group' : '' }}"
                                    >
                                        <div class="w-10 h-10 rounded-full overflow-hidden bg-[#2E325C] text-white flex items-center justify-center text-sm font-bold flex-shrink-0 {{ $member['id'] ? 'group-hover:ring-2 group-hover:ring-[#2D92CE] transition-all' : '' }}">
                                            @if(!empty($member['avatar']))
                                                <img src="{{ $member['avatar'] }}" alt="{{ $member['name'] }}" class="w-full h-full object-cover">
                                            @else
                                                <span>{{ \Illuminate\Support\Str::upper(collect(preg_split('/\s+/', trim($member['name'])))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('')) ?: '?' }}</span>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-medium text-brand-dark {{ $member['id'] ? 'group-hover:underline' : '' }}">{{ $member['name'] }}</div>
                                            <div class="text-sm text-brand-gray-light">{{ $member['role'] }} · {{ $member['rank'] }}</div>
                                        </div>
                                    </{{ $member['id'] ? 'button' : 'div' }}>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="text-sm text-brand-gray-light">Экипаж не указан</div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
