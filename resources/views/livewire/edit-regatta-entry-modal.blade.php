<div x-data="{ isOpen: @entangle('isOpen') }"
     x-show="isOpen"
     x-cloak
     @keydown.escape.window="$wire.closeModal()"
     class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4">

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
         class="bg-white overflow-y-auto max-h-[90vh] shadow-xl transform transition-all sm:max-w-lg sm:w-full p-6 z-10 relative">

        @if ($saved)
            <div class="flex items-center justify-between pb-3 mb-4">
                <h3 class="text-lg font-medium text-[#2E325C] a-font">Изменения сохранены</h3>
                <button @click="$wire.closeModal()" class="text-gray-400 hover:text-gray-500 text-2xl font-bold">&times;</button>
            </div>
            <div class="p-6 text-center">
                <svg class="mx-auto h-12 w-12 text-green-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <p class="font-medium text-lg">Заявка успешно обновлена.</p>
                <button type="button" @click="$wire.closeModal(); window.location.reload()"
                        class="mt-4 inline-flex justify-center bg-[#2D92CE] px-4 py-2 text-sm font-semibold text-white shadow hover:bg-[#2D92CE]/90">
                    Закрыть
                </button>
            </div>

        @elseif (! $authenticated)
            {{-- Шаг 1: пароль --}}
            <div class="flex items-center justify-between pb-3 mb-4">
                <h3 class="text-lg font-medium text-[#2E325C] a-font">Редактирование заявки</h3>
                <button @click="$wire.closeModal()" class="text-gray-400 hover:text-gray-500 text-2xl font-bold">&times;</button>
            </div>
            <p class="text-gray-600 mb-4 text-sm">
                Введите пароль заявки, чтобы редактировать её без входа в аккаунт.
                @if ($this->entry?->team)
                    <span class="block mt-1 font-medium text-[#2E325C]">Команда: {{ $this->entry->team->name }}</span>
                @endif
            </p>
            <form wire:submit.prevent="authenticate" class="space-y-4">
                <div x-data="{ showPassword: false }">
                    <label for="entry_edit_password" class="block text-sm text-gray-500 mb-1">Пароль заявки</label>
                    <div class="relative">
                        <input :type="showPassword ? 'text' : 'password'" id="entry_edit_password" wire:model="password"
                               class="block w-full border bg-[#F8F8F8] rounded px-3 py-2 text-sm pr-10 focus:border-[#2D92CE] focus:outline-none @error('password') border-red-300 @else border-gray-200 @enderror">
                        <button type="button" @click="showPassword = !showPassword"
                                :aria-label="showPassword ? 'Скрыть пароль' : 'Показать пароль'"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
                            <svg x-show="!showPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                            <svg x-show="showPassword" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>
                    @error('password')
                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                    @enderror
                </div>
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="inline-flex w-full justify-center bg-[#2D92CE] px-3 py-2 text-sm font-semibold text-white shadow hover:bg-[#2D92CE]/90 disabled:opacity-50">
                    Продолжить →
                </button>
            </form>

        @else
            {{-- Шаг 2: форма редактирования --}}
            <div class="flex items-center justify-between pb-3 mb-4">
                <h3 class="text-lg font-medium text-[#2E325C] a-font">Редактирование заявки</h3>
                <button @click="$wire.closeModal()" class="text-gray-400 hover:text-gray-500 text-2xl font-bold">&times;</button>
            </div>

            <form wire:submit.prevent="save" class="space-y-5">
                @error('general')
                    <div class="bg-red-50 text-red-700 text-sm p-3 rounded">{{ $message }}</div>
                @enderror

                {{-- Яхта --}}
                <div>
                    <p class="text-sm font-medium text-[#2E325C] mb-2">Яхта</p>
                    @if ($yachtMode === 'select')
                        <select wire:model="yachtId"
                                class="w-full border bg-[#F8F8F8] rounded px-3 py-2 text-sm focus:border-[#2D92CE] focus:outline-none @error('yachtId') border-red-300 @else border-gray-200 @enderror">
                            <option value="">— выберите яхту —</option>
                            @foreach ($freeYachts as $y)
                                <option value="{{ $y['id'] }}">{{ $y['name'] }}@if ($y['vfps']) ({{ $y['vfps'] }})@endif</option>
                            @endforeach
                        </select>
                        @error('yachtId')
                            <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                        @enderror
                        <button type="button" wire:click="startNewYacht"
                                class="text-xs text-[#2D92CE] hover:underline mt-2">+ Добавить свою яхту</button>
                    @else
                        <input type="text" wire:model="newYachtName" placeholder="Название яхты"
                               class="block w-full border bg-white rounded px-3 py-2 text-sm focus:border-[#2D92CE] focus:outline-none mb-2 @error('newYachtName') border-red-300 @else border-gray-200 @enderror">
                        @error('newYachtName')
                            <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                        @enderror
                        <input type="text" wire:model="newYachtVfps" placeholder="Номер паруса"
                               class="block w-full border border-gray-200 bg-white rounded px-3 py-2 text-sm focus:border-[#2D92CE] focus:outline-none">
                        <button type="button" wire:click="clearNewYacht"
                                class="text-xs text-gray-500 hover:underline mt-2">Выбрать из списка</button>
                    @endif
                </div>

                {{-- Экипаж --}}
                <div class="border-t border-gray-200 pt-4">
                    <p class="text-sm font-medium text-[#2E325C] mb-2">Экипаж</p>
                    @error('crew')
                        <span class="text-xs text-red-600 mb-2 block">{{ $message }}</span>
                    @enderror

                    <div class="space-y-2">
                        @foreach ($crew as $i => $member)
                            <div wire:key="crew-{{ $member['ref'] }}"
                                 class="flex items-center gap-2 bg-[#F8F8F8] rounded px-3 py-2">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-[#2E325C] truncate">{{ $member['name'] }}</p>
                                </div>
                                <select wire:model="crew.{{ $i }}.role"
                                        class="border border-gray-200 bg-white rounded px-2 py-1 text-xs focus:border-[#2D92CE] focus:outline-none">
                                    <option value="captain">Капитан</option>
                                    <option value="main">Основной</option>
                                    <option value="reserve">Запасной</option>
                                </select>
                                <button type="button" wire:click="removeMember('{{ $member['ref'] }}')"
                                        class="text-red-500 hover:text-red-700 text-lg leading-none px-1">&times;</button>
                            </div>
                        @endforeach
                    </div>

                    {{-- Добавить участника: поиск --}}
                    <div class="mt-3 relative">
                        <input type="text" wire:model.live.debounce.300ms="searchQuery"
                               placeholder="Найти участника по имени или email"
                               class="block w-full border border-gray-200 bg-white rounded px-3 py-2 text-sm focus:border-[#2D92CE] focus:outline-none">
                        @if (! empty($searchResults))
                            <div class="absolute z-20 left-0 right-0 mt-1 bg-white border border-gray-200 rounded shadow max-h-48 overflow-y-auto">
                                @foreach ($searchResults as $result)
                                    <button type="button" wire:click="addMember('{{ $result['id'] }}')"
                                            class="block w-full text-left px-3 py-2 text-sm hover:bg-[#F8F8F8]">
                                        {{ $result['name'] }}
                                        <span class="text-gray-400 text-xs">{{ $result['email'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Добавить незарегистрированного участника --}}
                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <input type="text" wire:model="newMemberName" placeholder="ФИО участника"
                               class="block w-full border bg-white rounded px-3 py-2 text-sm focus:border-[#2D92CE] focus:outline-none @error('newMemberName') border-red-300 @else border-gray-200 @enderror">
                        <input type="date" wire:model="newMemberBirthDate"
                               class="block w-full border bg-white rounded px-3 py-2 text-sm focus:border-[#2D92CE] focus:outline-none @error('newMemberBirthDate') border-red-300 @else border-gray-200 @enderror">
                        <select wire:model="newMemberSportCategory"
                                class="block w-full border border-gray-200 bg-white rounded px-3 py-2 text-sm focus:border-[#2D92CE] focus:outline-none">
                            <option value="">Разряд не указан</option>
                            @foreach (\App\Enums\SportCategory::cases() as $cat)
                                <option value="{{ $cat->value }}">{{ $cat->getLabel() }}</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="addUnregisteredMember"
                                class="bg-white border border-[#2D92CE] text-[#2D92CE] rounded px-3 py-2 text-sm font-medium hover:bg-[#2D92CE]/5">
                            + Добавить участника
                        </button>
                    </div>
                    @error('newMemberName')
                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                    @enderror
                    @error('newMemberBirthDate')
                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                    @enderror
                </div>

                {{-- Документы --}}
                @if ($this->requiredDocuments())
                    <div class="border-t border-gray-200 pt-4">
                        <p class="text-sm font-medium text-[#2E325C] mb-3">Документы</p>
                        @foreach ($this->requiredDocuments() as $doc)
                            <div class="mb-4">
                                <label for="edit_doc_{{ $doc['doc_type'] }}" class="block text-sm text-brand-gray-light">
                                    {{ $doc['title'] }}
                                    @if ($doc['is_required'] ?? false)
                                        <span class="text-red-500">*</span>
                                    @else
                                        <span class="text-gray-400 text-xs">(необязательный)</span>
                                    @endif
                                </label>

                                {{-- Существующие файлы --}}
                                @foreach ($existingDocuments[$doc['doc_type']] ?? [] as $file)
                                    <div class="flex items-center gap-2 text-sm mt-1
                                                {{ in_array($file['url'], $removedDocuments, true) ? 'opacity-50 line-through' : '' }}">
                                        <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($file['url']) }}"
                                           target="_blank" class="text-[#2D92CE] hover:underline truncate flex-1">{{ $file['name'] }}</a>
                                        @if (in_array($file['url'], $removedDocuments, true))
                                            <button type="button" wire:click="restoreExistingDocument('{{ $file['url'] }}')"
                                                    class="text-xs text-gray-500 hover:underline">Вернуть</button>
                                        @else
                                            <button type="button" wire:click="removeExistingDocument('{{ $doc['doc_type'] }}', '{{ $file['url'] }}')"
                                                    class="text-xs text-red-500 hover:underline">Удалить</button>
                                        @endif
                                    </div>
                                @endforeach

                                <input type="file"
                                       id="edit_doc_{{ $doc['doc_type'] }}"
                                       wire:model="documentFiles.{{ $doc['doc_type'] }}"
                                       accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp" multiple
                                       class="mt-2 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:border-0 file:text-sm file:font-semibold file:bg-[#F8F8F8] file:text-[#2E325C] hover:file:bg-gray-200">
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
                        <span wire:loading.remove wire:target="save">Сохранить изменения</span>
                        <span wire:loading wire:target="save">Сохранение...</span>
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>
