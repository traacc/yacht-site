<div x-data="{ isOpen: @entangle('isOpen') }"
     x-show="isOpen"
     x-cloak
     @keydown.escape.window="$wire.closeModal()"
     class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto">

    <!-- Overlay -->
    <div x-show="isOpen"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="$wire.closeModal()"
         class="fixed inset-0 bg-black/50 transition-opacity">
    </div>

    <!-- Modal -->
    <div x-show="isOpen"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         class="bg-white overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full p-6 z-10 relative">


        @if ($this->state === 'guest')
            <div class="flex items-center justify-between pb-3 mb-4">
                <h3 class="text-lg font-medium text-[#2E325C] a-font">Войдите в личный кабинет</h3>
                <button @click="$wire.closeModal()" class="text-gray-400 hover:text-gray-500 text-2xl font-bold">&times;</button>
            </div>
            <div class="text-center py-6">
                <p class="text-gray-600 mb-6">Чтобы подать заявку на участие в регате, необходимо войти в Личный кабинет.</p>
                <button @click="$dispatch('open-login-modal'); $wire.closeModal()"
                        class="inline-flex justify-center bg-[#2D92CE] px-4 py-2 text-sm font-semibold text-white shadow hover:bg-[#2D92CE]/90">
                    Войти или зарегистрироваться
                </button>
            </div>
        @elseif ($this->state === 'no-team')
            <div class="flex items-center justify-end pb-3 mb-4">
                <button @click="$wire.closeModal()" class="text-gray-400 hover:text-gray-500 text-2xl font-bold">&times;</button>
            </div>
            <div class="text-center py-6">
                <p class="text-gray-600 mb-2">Для участия в регате нужно:</p>
                <ul class="list-disc list-inside pl-4 mb-3">
                    <li class="text-gray-600 mb-1">зарегистрировать команду</li>
                    <li class="text-gray-600 mb-1">зарегистрировать и подтвердить яхту</li>
                </ul>
                <a href="{{ url('/user') }}"
                   class="inline-flex justify-center bg-[#2D92CE] px-4 py-2 text-sm font-semibold text-white shadow hover:bg-[#2D92CE]/90">
                    Перейти в личный кабинет
                </a>
            </div>
        @elseif ($this->state === 'in-crew')
            <div class="flex items-center justify-between pb-3 mb-4">
                <h3 class="text-lg font-medium text-[#2E325C] a-font">Участие в регате</h3>
                <button @click="$wire.closeModal()" class="text-gray-400 hover:text-gray-500 text-2xl font-bold">&times;</button>
            </div>
            <div class="py-4">
                <p class="text-gray-600 mb-2">Вы зарегистрированы в экипаже для участия в данной регате.</p>
                <p class="text-gray-600 mb-6">Вы уверены, что хотите отказаться от участия?</p>
                <div class="flex gap-3">
                    <button wire:click="leaveCrew"
                            wire:loading.attr="disabled"
                            class="inline-flex justify-center bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-red-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="leaveCrew">Отказаться от участия</span>
                        <span wire:loading wire:target="leaveCrew">Обработка...</span>
                    </button>
                    <button type="button" @click="$wire.closeModal()"
                            class="inline-flex justify-center border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow hover:bg-gray-50">
                        Отмена
                    </button>
                </div>
            </div>
        @elseif ($this->state === 'in-crew-captain')
            <div class="flex items-center justify-between pb-3 mb-4">
                <h3 class="text-lg font-medium text-[#2E325C] a-font">Участие в регате</h3>
                <button @click="$wire.closeModal()" class="text-gray-400 hover:text-gray-500 text-2xl font-bold">&times;</button>
            </div>
            <div class="py-4">
                <div class="flex items-start gap-3 p-4 bg-yellow-50 border border-yellow-200 mb-5">
                    <svg class="h-5 w-5 text-yellow-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-yellow-800">Вы являетесь капитаном экипажа</p>
                        <p class="text-sm text-yellow-700 mt-1">Для отказа от участия необходимо сначала назначить другого капитана в составе экипажа.</p>
                    </div>
                </div>
                <button type="button" @click="$wire.closeModal()"
                        class="inline-flex w-full justify-center border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow hover:bg-gray-50">
                    Закрыть
                </button>
            </div>
        @elseif ($leftCrew)
            <div class="flex items-center justify-between pb-3 mb-4">
                <h3 class="text-lg font-medium text-[#2E325C] a-font">Участие отменено</h3>
                <button @click="$wire.closeModal()" class="text-gray-400 hover:text-gray-500 text-2xl font-bold">&times;</button>
            </div>
            <div class="p-6 text-center">
                <p class="font-medium text-lg">Вы успешно отказались от участия в регате</p>
                <button type="button" @click="$wire.closeModal()"
                        class="mt-4 inline-flex justify-center bg-[#2D92CE] px-4 py-2 text-sm font-semibold text-white shadow hover:bg-[#2D92CE]/90">
                    Закрыть
                </button>
            </div>
        @elseif ($submitted)
            <div class="flex items-center justify-between pb-3 mb-4">
                <h3 class="text-lg font-medium text-[#2E325C] a-font">Заявка подана</h3>
                <button @click="$wire.closeModal()" class="text-gray-400 hover:text-gray-500 text-2xl font-bold">&times;</button>
            </div>
            <div class=" p-6 text-center">
                <svg class="mx-auto h-12 w-12 text-green-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <p class="font-medium text-lg">Ваша заявка успешно подана, ожидайте подтверждения</p>
            </div>
        @else
            <div class="flex items-center justify-between pb-3 mb-4">
                <h3 class="text-lg font-medium text-[#2E325C] a-font">Подать заявку</h3>
                <button @click="$wire.closeModal()" class="text-gray-400 hover:text-gray-500 text-2xl font-bold">&times;</button>
            </div>
            <p>Выберите команду и яхту, которые будут участвовать в регате.</p>
            <p class="mb-3">Подать заявку может только организатор команды.</p>
            <form wire:submit.prevent="submit" class="space-y-4">
                @error('general')
                    <div class="text-sm text-red-600 bg-red-50 p-3 rounded">{{ $message }}</div>
                @enderror

                <div>
                    <label for="team" class="block text-sm text-brand-gray-light">Команда</label>
                    <select id="team" wire:model.live="teamId"
                            class="mt-1 block w-full border-none font-medium shadow-sm bg-[#F8F8F8] sm:text-sm @error('teamId') border-red-300 @enderror">
                        <option value="">Выберите команду</option>
                        @foreach ($this->organizerTeams as $team)
                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                        @endforeach
                    </select>
                    @error('teamId')
                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label for="yacht" class="block text-sm text-brand-gray-light">Яхта</label>
                    <select id="yacht" wire:model="yachtId"
                            class="mt-1 block w-full border-none font-medium shadow-sm bg-[#F8F8F8] sm:text-sm  @error('yachtId') border-red-300 @enderror">
                        <option value="">Выберите яхту</option>
                        @foreach ($this->allFreeYachts as $yacht)
                            <option value="{{ $yacht->id }}">{{ $yacht->name }} ({{ $yacht->vfps_number ?? 'без номера ВФПС' }})</option>
                        @endforeach
                    </select>
                    @error('yachtId')
                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                    @enderror
                </div>

                @php $teamMembers = $this->teamMembers(); @endphp
                @if ($teamId && $teamMembers->isNotEmpty())
                    <div class="border-t border-gray-200 pt-4">
                        <p class="text-sm font-medium text-[#2E325C] mb-3">Экипаж</p>
                        <p class="text-xs text-gray-500 mb-3">Выберите участников команды и укажите роль: основной или запасной.</p>
                        @error('crew')
                            <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                        @enderror
                        @foreach ($teamMembers as $member)
                            <div class="flex items-center gap-3 mb-2 py-2 px-3 bg-[#F8F8F8] rounded">
                                <span class="text-sm flex-1">
                                    {{ $member->user?->full_name ?? $member->user?->name ?? 'Неизвестный участник' }}
                                    @if ($member->is_captain ?? false)
                                        <span class="text-yellow-500 text-xs font-semibold ml-1">Капитан</span>
                                    @endif
                                </span>
                                <select wire:model.change="crew.{{ $member->id }}"
                                        class="text-sm border-gray-200 bg-white rounded p-1 min-w-[140px]">
                                    <option value="">Не участвует</option>
                                    <option value="main">Основной</option>
                                    <option value="reserve">Запасной</option>
                                    <option value="captain">Капитан</option>
                                </select>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Поиск и добавление участников, не состоящих в команде --}}
                @if ($teamId)
                    <div class="border-t border-gray-200 pt-4" x-data="{
                        query: @entangle('searchQuery'),
                        results: @entangle('searchResults'),
                        isOpen: false,
                        selectedIndex: -1,
                        init() {
                            this.$watch('results', v => { this.isOpen = v.length > 0; this.selectedIndex = -1; });
                        },
                        selectItem(userId) {
                            $wire.searchQuery = '';
                            this.isOpen = false;
                            $wire.addExternalMember(userId);
                        },
                        onKeydown(e) {
                            if (!this.isOpen || this.results.length === 0) return;
                            if (e.key === 'ArrowDown') { e.preventDefault(); this.selectedIndex = Math.min(this.selectedIndex + 1, this.results.length - 1); }
                            else if (e.key === 'ArrowUp') { e.preventDefault(); this.selectedIndex = Math.max(this.selectedIndex - 1, -1); }
                            else if (e.key === 'Enter' && this.selectedIndex >= 0) { e.preventDefault(); this.selectItem(this.results[this.selectedIndex].id); }
                            else if (e.key === 'Escape') { this.isOpen = false; $wire.searchQuery = ''; }
                        }
                    }" x-on:click.away="isOpen = false">
                        <p class="text-sm font-medium text-[#2E325C] mb-2">Добавить участника</p>
                        <p class="text-xs text-gray-500 mb-3">Найдите пользователя, который ещё не состоит в команде, чтобы добавить его в экипаж.</p>

                        <div class="relative">
                            <input type="text"
                                   wire:model.live.debounce.350ms="searchQuery"
                                   x-on:keydown="onKeydown($event)"
                                   placeholder="Поиск по имени, фамилии или email..."
                                   class="w-full border border-gray-200 bg-[#F8F8F8] rounded px-3 py-2 text-sm focus:border-[#2D92CE] focus:outline-none">

                            {{-- Спиннер загрузки --}}
                            <div wire:loading wire:target="searchQuery" class="absolute right-3 top-2.5 text-gray-400 text-xs">
                                Поиск...
                            </div>

                            {{-- Выпадающий список результатов --}}
                            <div x-show="isOpen" x-cloak
                                 class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded shadow-lg max-h-56 overflow-y-auto">
                                <template x-for="(user, index) in results" :key="user.id">
                                    <div x-on:click="selectItem(user.id)"
                                         x-on:mouseenter="selectedIndex = index"
                                         :class="{ 'bg-[#2D92CE]/10': selectedIndex === index }"
                                         class="px-3 py-2 cursor-pointer hover:bg-[#2D92CE]/10 text-sm border-b border-gray-100 last:border-b-0">
                                        <span class="font-medium" x-text="user.name"></span>
                                        <span x-show="user.patronymic" x-text="' ' + user.patronymic" class="text-gray-500"></span>
                                        <span class="text-gray-400 text-xs ml-2" x-text="user.email"></span>
                                    </div>
                                </template>
                                <div x-show="results.length === 0 && query.length > 0" class="px-3 py-2 text-sm text-gray-400">
                                    Ничего не найдено
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($this->requiredDocuments())
                    <div class="border-t border-gray-200 pt-4">
                        <p class="text-sm font-medium text-[#2E325C] mb-3">Документы</p>
                        @foreach ($this->requiredDocuments() as $doc)
                            <div class="mb-3">
                                <label for="doc_{{ $doc['doc_type'] }}" class="block text-sm text-brand-gray-light">
                                    {{ $doc['title'] }}
                                    @if ($doc['is_required'] ?? false)
                                        <span class="text-red-500">*</span>
                                    @else
                                        <span class="text-gray-400 text-xs">(необязательный)</span>
                                    @endif
                                </label>
                                <input type="file"
                                       id="doc_{{ $doc['doc_type'] }}"
                                       wire:model="documentFiles.{{ $doc['doc_type'] }}"
                                       accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp" multiple
                                       class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:border-0 file:text-sm file:font-semibold file:bg-[#F8F8F8] file:text-[#2E325C] hover:file:bg-gray-200">
                                @error('documentFiles.' . $doc['doc_type'])
                                    <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                                @enderror
                                @error('documentFiles.' . $doc['doc_type'] . '.*')
                                    <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                                @enderror
                                <div wire:loading wire:target="documentFiles.{{ $doc['doc_type'] }}" class="text-xs text-blue-500 mt-1">Загрузка...</div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-5 sm:mt-6">
                    <button type="submit"
                            wire:loading.attr="disabled"
                            class="inline-flex w-full justify-center bg-[#2D92CE] px-3 py-2 text-sm font-semibold text-white shadow hover:bg-[#2D92CE]/90 disabled:opacity-50">
                        <span wire:loading.remove>Подать заявку →</span>
                        <span wire:loading>Отправка...</span>
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>
