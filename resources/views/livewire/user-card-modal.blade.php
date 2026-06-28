{{--
    Карточка пользователя.
    Управляется через Livewire ($isOpen / $user). Alpine — только для закрытия по Esc/клику вне окна.
--}}
<div>
    @if($isOpen && $user)
        <div
            class="fixed inset-0 z-[60] flex md:items-center md:justify-center p-4 bg-black/50 overflow-y-auto"
            @keydown.escape.window="$wire.closeModal()"
        >
            <div
                class="relative w-full max-w-[90vw] md:max-w-[480px] bg-white shadow-xl"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                @click.outside="$wire.closeModal()"
            >
                <button
                    wire:click="closeModal"
                    class="absolute top-3 right-4 text-2xl font-bold leading-none text-white transition-colors cursor-pointer z-10"
                    aria-label="Закрыть"
                >&times;</button>

                {{-- Шапка: аватар, имя, номер участника --}}
                <div class="flex items-center gap-4 p-6 bg-[#2E325C] text-white">
                    <div class="w-20 h-20 rounded-full overflow-hidden bg-white/15 flex items-center justify-center text-2xl font-bold flex-shrink-0">
                        @if(!empty($user['avatar']))
                            <img src="{{ $user['avatar'] }}" alt="{{ $user['name'] }}" class="w-full h-full object-cover">
                        @else
                            <span>{{ \Illuminate\Support\Str::upper(collect(preg_split('/\s+/', trim($user['name'])))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('')) ?: '?' }}</span>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <h4 class="a-font text-xl md:text-2xl leading-tight">{{ $user['name'] }}</h4>
                        <!--<div class="text-sm text-white/70 mt-1">Участник № {{ $user['number'] }}</div>-->
                    </div>
                </div>

                <div class="p-6 space-y-5">
                    {{-- Основные данные --}}
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        <div>
                            <dt class="text-brand-gray-light">Дата рождения</dt>
                            <dd class="font-medium text-brand-dark">{{ $user['birthday'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-brand-gray-light">Разряд</dt>
                            <dd class="font-medium text-brand-dark">{{ $user['rank'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-brand-gray-light">Участий в регатах</dt>
                            <dd class="font-medium text-brand-dark">{{ $user['regattas'] }}</dd>
                        </div>
                        @if($user['rating'])
                            <div>
                                <dt class="text-brand-gray-light">Личный рейтинг@if($user['rating']['season']) ({{ $user['rating']['season'] }})@endif</dt>
                                <dd class="font-medium text-brand-dark">
                                    {{ $user['rating']['position'] ? '№ '.$user['rating']['position'] : '—' }}
                                    @if($user['rating']['points'] !== null)
                                        <span class="text-brand-gray-light">· {{ rtrim(rtrim(number_format($user['rating']['points'], 3, '.', ''), '0'), '.') }} очк.</span>
                                    @endif
                                </dd>
                            </div>
                        @endif
                    </dl>

                    {{-- Главная команда --}}
                    <div>
                        <div class="text-sm text-brand-gray-light mb-2">Команда</div>
                        @if(!empty($user['team']))
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="font-medium text-brand-dark">{{ $user['team']['name'] }}</span>
                                <span class="text-xs text-brand-gray-light flex-shrink-0">{{ $user['team']['role'] }}</span>
                            </div>
                        @else
                            <div class="text-sm text-brand-gray-light">Не состоит в команде</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
