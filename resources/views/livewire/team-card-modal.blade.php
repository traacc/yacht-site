{{--
    Карточка команды.
    Управляется через Livewire ($isOpen / $team). Alpine — только для закрытия по Esc/клику вне окна.

    Открывается событием 'open-team-card' с id команды (см. App\Livewire\TeamCardModal).
    Участники в составе кликабельны и открывают карточку пользователя (open-user-card)
    поверх этой карточки — по аналогии с {@see resources/views/livewire/user-card-modal.blade.php}.
--}}
<div>
    @if($isOpen && $team)
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

                {{-- Шапка: фото, название, статус --}}
                <div class="flex items-center gap-4 p-6 bg-[#2E325C] text-white">
                    <div class="w-20 h-20 rounded-full overflow-hidden bg-white/15 flex items-center justify-center text-2xl font-bold flex-shrink-0">
                        @if(!empty($team['photo']))
                            <img src="{{ $team['photo'] }}" alt="{{ $team['name'] }}" class="w-full h-full object-cover">
                        @else
                            <span>{{ \Illuminate\Support\Str::upper(collect(preg_split('/\s+/', trim($team['name'])))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('')) ?: '?' }}</span>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <h4 class="a-font text-xl md:text-2xl leading-tight">{{ $team['name'] }}</h4>
                        <div class="text-sm text-white/70 mt-1">{{ $team['status'] }}</div>
                    </div>
                </div>

                <div class="p-6 space-y-5">
                    {{-- Основные данные --}}
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        <div>
                            <dt class="text-brand-gray-light">Капитан</dt>
                            <dd class="font-medium text-brand-dark">
                                @if(!empty($team['captain_id']) && !empty($team['captain']))
                                    <button
                                        type="button"
                                        wire:click="$dispatch('open-user-card', { userId: '{{ $team['captain_id'] }}' })"
                                        class="text-[#2D92CE] hover:underline cursor-pointer text-left"
                                    >{{ $team['captain'] }}</button>
                                @else
                                    {{ $team['captain'] ?? '—' }}
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-brand-gray-light">Рейтинг</dt>
                            <dd class="font-medium text-brand-dark">{{ $team['rating'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-brand-gray-light">Участий в регатах</dt>
                            <dd class="font-medium text-brand-dark">{{ $team['regattas'] }}</dd>
                        </div>
                    </dl>

                    @if(!empty($team['description']))
                        <div>
                            <div class="text-sm text-brand-gray-light mb-1">О команде</div>
                            <p class="text-sm text-brand-dark">{{ $team['description'] }}</p>
                        </div>
                    @endif

                    {{-- Состав команды --}}
                    <div>
                        <div class="text-sm text-brand-gray-light mb-2">Состав команды</div>
                        @if(!empty($team['members']))
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="text-left text-brand-gray-light border-b border-brand-border">
                                            <th class="py-1.5 font-medium">Участник</th>
                                            <th class="py-1.5 font-medium">Дата рождения</th>
                                            <th class="py-1.5 font-medium">Разряд</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-brand-border">
                                        @foreach($team['members'] as $member)
                                            <tr class="hover:bg-gray-50 transition-colors">
                                                <td class="py-2">
                                                    <button
                                                        type="button"
                                                        wire:click="$dispatch('open-user-card', { userId: '{{ $member['id'] }}' })"
                                                        class="flex items-center gap-3 text-left hover:text-[#2D92CE] transition-colors cursor-pointer group"
                                                    >
                                                        <div class="w-8 h-8 rounded-full overflow-hidden bg-[#2E325C] text-white flex items-center justify-center text-sm font-bold flex-shrink-0 group-hover:ring-2 group-hover:ring-[#2D92CE] transition-all">
                                                            @if(!empty($member['avatar']))
                                                                <img src="{{ $member['avatar'] }}" alt="{{ $member['name'] }}" class="w-full h-full object-cover">
                                                            @else
                                                                <span>{{ \Illuminate\Support\Str::upper(collect(preg_split('/\s+/', trim($member['name'])))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('')) ?: '?' }}</span>
                                                            @endif
                                                        </div>
                                                        <span class="group-hover:underline">{{ $member['name'] }}</span>
                                                    </button>
                                                </td>
                                                <td class="py-2 text-brand-dark">{{ $member['birthday'] }}</td>
                                                <td class="py-2 text-brand-dark">{{ $member['category'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-sm text-brand-gray-light">Нет данных о составе</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
