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
                <p class="text-gray-600 mb-6">Чтобы подать заявку на участие в регате, необходимо войти в аккаунт участника Ассоциации.</p>
                <button @click="$dispatch('open-login-modal'); $wire.closeModal()"
                        class="inline-flex justify-center bg-[#2D92CE] px-4 py-2 text-sm font-semibold text-white shadow hover:bg-[#2D92CE]/90">
                    Войти или зарегистрироваться
                </button>
            </div>
        @elseif ($this->state === 'no-team')
            <div class="flex items-center justify-between pb-3 mb-4">
                <h3 class="text-lg font-medium text-[#2E325C] a-font">Подать заявку пока нельзя</h3>
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
                    <select id="team" wire:model="teamId"
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
                        @foreach ($this->userYachts as $yacht)
                            <option value="{{ $yacht->id }}">{{ $yacht->name }} ({{ $yacht->vfps_number ?? 'без номера ВФПС' }})</option>
                        @endforeach
                    </select>
                    @error('yachtId')
                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                    @enderror
                </div>

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
