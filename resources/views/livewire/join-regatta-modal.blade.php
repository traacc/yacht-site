<div x-data="{
        isOpen: @entangle('isOpen'),
        requireConfirm: @js($this->state === 'guest' || $this->state === 'form'),
        attemptClose() {
            if (this.requireConfirm && ! confirm('Закрыть окно? Введённые данные не будут сохранены.')) return;
            $wire.closeModal();
        }
     }"
     x-show="isOpen"
     x-cloak
     @keydown.escape.window="attemptClose()"
     class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4">

    <!-- Overlay -->
    <div x-show="isOpen"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="attemptClose()"
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


        @if ($submitted)
            <div class="flex items-center justify-between pb-3 mb-4">
                <h3 class="text-lg font-medium text-[#2E325C] a-font">Заявка подана</h3>
                <button @click="$wire.closeModal()" class="text-gray-400 hover:text-gray-500 text-2xl font-bold">&times;</button>
            </div>
            <div class=" p-6 text-center">
                <svg class="mx-auto h-12 w-12 text-green-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <p class="font-medium text-lg">Ваша заявка успешно подана, ожидайте подтверждения</p>
                @if ($guestRegistered)
                    <p class="text-gray-600 mt-3">Мы создали для вас личный кабинет. Данные для входа отправлены на указанный email.</p>
                @endif
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
        @elseif ($this->state === 'guest' || $this->state === 'form')
            <div class="flex items-center justify-between pb-3 mb-4">
                <h3 class="text-lg font-medium text-[#2E325C] a-font">Подать заявку</h3>
                <button @click="attemptClose()" class="text-gray-400 hover:text-gray-500 text-2xl font-bold">&times;</button>
            </div>
            <p class="text-gray-600 mb-1">Заполните форму, чтобы подать заявку на участие в регате.</p>
            @guest
                <p class="text-sm text-gray-500 mb-4">
                    @if ($captainMode === 'create')
                        Для нового капитана мы автоматически создадим личный кабинет, а данные для входа отправим на email.
                    @endif
                    Уже есть аккаунт?
                    <button type="button" @click="$dispatch('open-login-modal'); $wire.closeModal()"
                            class="text-[#2D92CE] font-medium hover:underline">Войти</button>
                </p>
            @else
                <p class="text-sm text-gray-500 mb-4">Выберите или создайте команду и укажите капитана — капитаном может быть любой зарегистрированный пользователь.</p>
            @endguest

            <form wire:submit.prevent="submitGuest" class="space-y-4">
                @error('general')
                    <div class="text-sm text-red-600 bg-red-50 p-3 rounded">{{ $message }}</div>
                @enderror

                {{-- Выбор регаты --}}
                <div>
                    <label for="regattaId" class="block text-sm text-brand-gray-light">Регата</label>
                    <select id="regattaId" wire:model.live="regattaId"
                            class="mt-1 block w-full border-none font-medium shadow-sm bg-[#F8F8F8] sm:text-sm @error('regattaId') border-red-300 @enderror">
                        <option value="">Выберите регату</option>
                        @foreach ($this->availableRegattas as $regatta)
                            <option value="{{ $regatta->id }}">{{ $regatta->name }}@if ($regatta->dateRange()) — {{ $regatta->dateRange() }}@endif</option>
                        @endforeach
                    </select>
                    @error('regattaId')
                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                    @enderror
                </div>

                {{-- Команда: выбрать любую существующую или создать новую (независимо от капитана) --}}
                <div x-data="{
                    query: @entangle('teamSearchQuery'),
                    results: @entangle('teamSearchResults'),
                    isOpen: false,
                    selectedIndex: -1,
                    init() {
                        this.$watch('results', () => { this.selectedIndex = -1; });
                        this.$watch('query', v => { this.isOpen = v.trim().length > 0; });
                    },
                    selectItem(teamId) {
                        this.isOpen = false;
                        $wire.selectTeam(teamId);
                    },
                    addNewFromQuery() {
                        const name = this.query.trim();
                        this.isOpen = false;
                        $wire.startNewTeam(name);
                    },
                    onKeydown(e) {
                        if (!this.isOpen) return;
                        if (e.key === 'ArrowDown') { e.preventDefault(); this.selectedIndex = Math.min(this.selectedIndex + 1, this.results.length - 1); }
                        else if (e.key === 'ArrowUp') { e.preventDefault(); this.selectedIndex = Math.max(this.selectedIndex - 1, -1); }
                        else if (e.key === 'Enter' && this.selectedIndex >= 0) { e.preventDefault(); this.selectItem(this.results[this.selectedIndex].id); }
                        else if (e.key === 'Escape') { this.isOpen = false; }
                    }
                }" x-on:click.away="isOpen = false">
                    <label class="block text-sm text-brand-gray-light mb-1">Команда</label>

                    @if ($teamMode === 'create')
                        {{-- Создание новой команды --}}
                        <div class="p-3 bg-[#F8F8F8] rounded space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-500">Новая команда</span>
                                <button type="button" wire:click="clearTeam"
                                        class="text-gray-400 hover:text-red-500 text-xl leading-none">&times;</button>
                            </div>
                            <input type="text" wire:model="teamName" placeholder="Название команды"
                                   class="block w-full border-none font-medium shadow-sm bg-white sm:text-sm @error('teamName') border-red-300 @enderror">
                            @error('teamName')
                                <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                            @enderror
                        </div>
                    @elseif ($teamId)
                        {{-- Выбранная команда --}}
                        <div class="flex items-center gap-3 py-2 px-3 bg-[#F8F8F8] rounded">
                            <span class="text-sm flex-1">{{ $teamSelectedName }}</span>
                            <button type="button" wire:click="clearTeam"
                                    class="text-gray-400 hover:text-red-500 text-xl leading-none">&times;</button>
                        </div>
                        @error('teamId')
                            <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                        @enderror
                    @else
                        {{-- Поиск любой команды --}}
                        <div class="relative">
                            <input type="text"
                                   wire:model.live.debounce.350ms="teamSearchQuery"
                                   x-on:keydown="onKeydown($event)"
                                   placeholder="Найдите команду или создайте новую..."
                                   class="w-full border bg-[#F8F8F8] rounded px-3 py-2 text-sm focus:border-[#2D92CE] focus:outline-none @error('teamId') border-red-300 @else border-gray-200 @enderror">

                            <div wire:loading wire:target="teamSearchQuery" class="absolute right-3 top-2.5 text-gray-400 text-xs">
                                Поиск...
                            </div>

                            <div x-show="isOpen" x-cloak
                                 class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded shadow-lg max-h-56 overflow-y-auto">
                                <template x-for="(team, index) in results" :key="team.id">
                                    <div x-on:click="selectItem(team.id)"
                                         x-on:mouseenter="selectedIndex = index"
                                         :class="{ 'bg-[#2D92CE]/10': selectedIndex === index }"
                                         class="px-3 py-2 cursor-pointer hover:bg-[#2D92CE]/10 text-sm border-b border-gray-100 last:border-b-0">
                                        <span class="font-medium" x-text="team.name"></span>
                                    </div>
                                </template>
                                <div x-show="results.length === 0 && query.length > 0" class="px-3 py-2 text-sm text-gray-400">
                                    Ничего не найдено
                                </div>
                                <div x-show="query.trim().length > 0"
                                     x-on:click="addNewFromQuery()"
                                     class="px-3 py-2 cursor-pointer hover:bg-[#2D92CE]/10 text-sm text-[#2D92CE] font-medium border-t border-gray-100">
                                    + Создать команду «<span x-text="query.trim()"></span>»
                                </div>
                            </div>
                        </div>
                        @error('teamId')
                            <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                        @enderror
                    @endif
                </div>

                {{-- Яхта: единый список — выбрать свободную или добавить свою --}}
                <div x-data="{
                    query: '',
                    isOpen: false,
                    selectedIndex: -1,
                    yachts: @entangle('freeYachts'),
                    init() {
                        this.$watch('query', () => { this.selectedIndex = -1; });
                    },
                    get filtered() {
                        const q = this.query.trim().toLowerCase();
                        if (q === '') return this.yachts;
                        return this.yachts.filter(y =>
                            (y.name ?? '').toLowerCase().includes(q) ||
                            (y.vfps ?? '').toLowerCase().includes(q)
                        );
                    },
                    selectYacht(yacht) {
                        this.isOpen = false;
                        this.query = '';
                        $wire.selectYacht(yacht.id);
                    },
                    addNewFromQuery() {
                        const name = this.query.trim();
                        this.isOpen = false;
                        this.query = '';
                        $wire.startNewYacht(name);
                    },
                    onKeydown(e) {
                        if (!this.isOpen) return;
                        const items = this.filtered;
                        if (e.key === 'ArrowDown') { e.preventDefault(); this.selectedIndex = Math.min(this.selectedIndex + 1, items.length - 1); }
                        else if (e.key === 'ArrowUp') { e.preventDefault(); this.selectedIndex = Math.max(this.selectedIndex - 1, -1); }
                        else if (e.key === 'Enter' && this.selectedIndex >= 0) { e.preventDefault(); this.selectYacht(items[this.selectedIndex]); }
                        else if (e.key === 'Escape') { this.isOpen = false; }
                    }
                }" x-on:click.away="isOpen = false">
                    <label class="block text-sm text-brand-gray-light mb-1">Яхта</label>

                    @if ($yachtMode === 'create')
                        {{-- Добавление своей яхты --}}
                        <div class="p-3 bg-[#F8F8F8] rounded space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-500">Новая яхта</span>
                                <button type="button" wire:click="clearYacht"
                                        class="text-gray-400 hover:text-red-500 text-xl leading-none">&times;</button>
                            </div>
                            <input type="text" wire:model="newYachtName" placeholder="Название яхты"
                                   class="block w-full border-none font-medium shadow-sm bg-white sm:text-sm @error('newYachtName') border-red-300 @enderror">
                            @error('newYachtName')
                                <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                            @enderror
                            <input type="text" wire:model="newYachtVfps" placeholder="Номер паруса"
                                   class="block w-full border-none font-medium shadow-sm bg-white sm:text-sm">
                        </div>
                    @elseif ($yachtId)
                        {{-- Выбранная свободная яхта --}}
                        @php($selectedYacht = collect($freeYachts)->firstWhere('id', $yachtId))
                        <div class="flex items-center gap-3 py-2 px-3 bg-[#F8F8F8] rounded">
                            <span class="text-sm flex-1">
                                {{ $selectedYacht['name'] ?? '' }}
                                <span class="text-gray-400 text-xs ml-1">({{ $selectedYacht['vfps'] ?? 'без номера ВФПС' }})</span>
                            </span>
                            <button type="button" wire:click="clearYacht"
                                    class="text-gray-400 hover:text-red-500 text-xl leading-none">&times;</button>
                        </div>
                    @else
                        {{-- Поиск по списку свободных яхт --}}
                        <div class="relative">
                            <input type="text" x-model="query"
                                   x-on:focus="isOpen = true"
                                   x-on:click="isOpen = true"
                                   x-on:keydown="onKeydown($event)"
                                   placeholder="Выберите свободную или добавьте свою..."
                                   class="w-full border bg-[#F8F8F8] rounded px-3 py-2 text-sm focus:border-[#2D92CE] focus:outline-none @error('yachtId') border-red-300 @else border-gray-200 @enderror">

                            <div x-show="isOpen" x-cloak
                                 class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded shadow-lg max-h-56 overflow-y-auto">
                                <template x-for="(yacht, index) in filtered" :key="yacht.id">
                                    <div x-on:click="selectYacht(yacht)"
                                         x-on:mouseenter="selectedIndex = index"
                                         :class="{ 'bg-[#2D92CE]/10': selectedIndex === index }"
                                         class="px-3 py-2 cursor-pointer hover:bg-[#2D92CE]/10 text-sm border-b border-gray-100 last:border-b-0">
                                        <span class="font-medium" x-text="yacht.name"></span>
                                        <span class="text-gray-400 text-xs ml-2" x-text="yacht.vfps ? yacht.vfps : 'без номера ВФПС'"></span>
                                    </div>
                                </template>
                                <div x-show="filtered.length === 0 && query.trim().length === 0" class="px-3 py-2 text-sm text-gray-400">
                                    Нет свободных яхт
                                </div>
                                <div x-show="query.trim().length > 0"
                                     x-on:click="addNewFromQuery()"
                                     class="px-3 py-2 cursor-pointer hover:bg-[#2D92CE]/10 text-sm text-[#2D92CE] font-medium border-t border-gray-100">
                                    + Добавить «<span x-text="query.trim()"></span>» как свою яхту
                                </div>
                            </div>
                        </div>
                        @error('yachtId')
                            <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                        @enderror
                    @endif
                </div>
                
                {{-- Капитан: выбрать существующего пользователя или создать нового --}}
                <div x-data="{
                    query: @entangle('captainSearchQuery'),
                    results: @entangle('captainSearchResults'),
                    isOpen: false,
                    selectedIndex: -1,
                    init() {
                        this.$watch('results', () => { this.selectedIndex = -1; });
                        this.$watch('query', v => { this.isOpen = v.trim().length > 0; });
                    },
                    selectItem(userId) {
                        this.isOpen = false;
                        $wire.selectCaptain(userId);
                    },
                    addNewFromQuery() {
                        const name = this.query.trim();
                        this.isOpen = false;
                        $wire.startNewCaptain(name);
                    },
                    onKeydown(e) {
                        if (!this.isOpen) return;
                        if (e.key === 'ArrowDown') { e.preventDefault(); this.selectedIndex = Math.min(this.selectedIndex + 1, this.results.length - 1); }
                        else if (e.key === 'ArrowUp') { e.preventDefault(); this.selectedIndex = Math.max(this.selectedIndex - 1, -1); }
                        else if (e.key === 'Enter' && this.selectedIndex >= 0) { e.preventDefault(); this.selectItem(this.results[this.selectedIndex].id); }
                        else if (e.key === 'Escape') { this.isOpen = false; }
                    }
                }" x-on:click.away="isOpen = false">
                    <label class="block text-sm text-brand-gray-light mb-1">Капитан</label>

                    @if ($captainMode === 'create')
                        {{-- Новый пользователь-капитан --}}
                        <div class="p-3 bg-[#F8F8F8] rounded space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-500">Новый капитан</span>
                                <button type="button" wire:click="clearCaptain"
                                        class="text-gray-400 hover:text-red-500 text-xl leading-none">&times;</button>
                            </div>
                            <div>
                                <label for="guestName" class="block text-xs text-gray-500 mb-1">ФИО</label>
                                <input type="text" id="guestName" wire:model="guestName"
                                       class="block w-full border-none font-medium shadow-sm bg-white sm:text-sm @error('guestName') border-red-300 @enderror">
                                @error('guestName')
                                    <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label for="guestEmail" class="block text-xs text-gray-500 mb-1">Email</label>
                                    <input type="email" id="guestEmail" wire:model="guestEmail"
                                           class="block w-full border-none font-medium shadow-sm bg-white sm:text-sm @error('guestEmail') border-red-300 @enderror">
                                    @error('guestEmail')
                                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div wire:key="guest-phone-wrap">
                                    <label for="guestPhone" class="block text-xs text-gray-500 mb-1">Телефон</label>
                                    <input type="text" id="guestPhone" wire:model="guestPhone" wire:key="guest-phone"
                                           x-mask="+7 (999) 999-99-99" x-ref="phone"
                                           class="block w-full border-none font-medium shadow-sm bg-white sm:text-sm @error('guestPhone') border-red-300 @enderror">
                                    @error('guestPhone')
                                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label for="guestBirthDate" class="block text-xs text-gray-500 mb-1">Дата рождения</label>
                                    <input type="date" id="guestBirthDate" wire:model="guestBirthDate"
                                           class="block w-full border bg-white rounded px-3 py-2 text-sm focus:border-[#2D92CE] focus:outline-none @error('guestBirthDate') border-red-300 @else border-gray-200 @enderror">
                                    @error('guestBirthDate')
                                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div>
                                    <label for="guestSportCategory" class="block text-xs text-gray-500 mb-1">Спортивный разряд</label>
                                    <select id="guestSportCategory" wire:model="guestSportCategory"
                                            class="block w-full border bg-white rounded px-3 py-2 text-sm focus:border-[#2D92CE] focus:outline-none @error('guestSportCategory') border-red-300 @else border-gray-200 @enderror">
                                        <option value="">Не указан</option>
                                        @foreach (\App\Enums\SportCategory::cases() as $cat)
                                            <option value="{{ $cat->value }}">{{ $cat->getLabel() }}</option>
                                        @endforeach
                                    </select>
                                    @error('guestSportCategory')
                                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    @elseif ($captainUserId)
                        {{-- Выбранный капитан --}}
                        <div class="flex items-center gap-3 py-2 px-3 bg-[#F8F8F8] rounded">
                            <span class="text-sm flex-1">{{ $captainName }}</span>
                            <button type="button" wire:click="clearCaptain"
                                    class="text-gray-400 hover:text-red-500 text-xl leading-none">&times;</button>
                        </div>
                        @error('captainUserId')
                            <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                        @enderror
                    @else
                        {{-- Поиск существующего пользователя --}}
                        <div class="relative">
                            <input type="text"
                                   wire:model.live.debounce.350ms="captainSearchQuery"
                                   x-on:keydown="onKeydown($event)"
                                   placeholder="Поиск по имени или email..."
                                   class="w-full border bg-[#F8F8F8] rounded px-3 py-2 text-sm focus:border-[#2D92CE] focus:outline-none @error('captainUserId') border-red-300 @else border-gray-200 @enderror">

                            <div wire:loading wire:target="captainSearchQuery" class="absolute right-3 top-2.5 text-gray-400 text-xs">
                                Поиск...
                            </div>

                            <div x-show="isOpen" x-cloak
                                 class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded shadow-lg max-h-56 overflow-y-auto">
                                <template x-for="(user, index) in results" :key="user.id">
                                    <div x-on:click="selectItem(user.id)"
                                         x-on:mouseenter="selectedIndex = index"
                                         :class="{ 'bg-[#2D92CE]/10': selectedIndex === index }"
                                         class="px-3 py-2 cursor-pointer hover:bg-[#2D92CE]/10 text-sm border-b border-gray-100 last:border-b-0">
                                        <span class="font-medium" x-text="user.name"></span>
                                        <span class="text-gray-400 text-xs ml-2" x-text="user.email"></span>
                                    </div>
                                </template>
                                <div x-show="results.length === 0 && query.length > 0" class="px-3 py-2 text-sm text-gray-400">
                                    Ничего не найдено
                                </div>
                                <div x-show="query.trim().length > 0"
                                     x-on:click="addNewFromQuery()"
                                     class="px-3 py-2 cursor-pointer hover:bg-[#2D92CE]/10 text-sm text-[#2D92CE] font-medium border-t border-gray-100">
                                    + Создать «<span x-text="query.trim()"></span>» как нового капитана
                                </div>
                            </div>
                        </div>
                        @error('captainUserId')
                            <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                        @enderror
                    @endif
                </div>

                {{-- Экипаж: добавление зарегистрированных пользователей --}}
                <div class="border-t border-gray-200 pt-4" x-data="{
                    query: @entangle('searchQuery'),
                    results: @entangle('searchResults'),
                    isOpen: false,
                    showNew: false,
                    selectedIndex: -1,
                    init() {
                        this.$watch('results', () => { this.selectedIndex = -1; });
                        this.$watch('query', v => { this.isOpen = v.trim().length > 0; });
                    },
                    selectItem(userId) {
                        $wire.searchQuery = '';
                        this.isOpen = false;
                        $wire.addGuestMember(userId);
                    },
                    addNewFromQuery() {
                        const name = this.query.trim();
                        this.isOpen = false;
                        $wire.searchQuery = '';
                        $wire.set('newMemberName', name);
                        this.showNew = true;
                    },
                    onKeydown(e) {
                        if (!this.isOpen) return;
                        if (e.key === 'ArrowDown') { e.preventDefault(); this.selectedIndex = Math.min(this.selectedIndex + 1, this.results.length - 1); }
                        else if (e.key === 'ArrowUp') { e.preventDefault(); this.selectedIndex = Math.max(this.selectedIndex - 1, -1); }
                        else if (e.key === 'Enter' && this.selectedIndex >= 0) { e.preventDefault(); this.selectItem(this.results[this.selectedIndex].id); }
                        else if (e.key === 'Escape') { this.isOpen = false; $wire.searchQuery = ''; }
                    }
                }" x-on:click.away="isOpen = false">
                    <p class="text-sm font-medium text-[#2E325C] mb-2">Экипаж</p>
                    <p class="text-xs text-gray-500 mb-3">Капитаном команды будет указанный выше пользователь. Можно добавить не более {{ \App\Livewire\JoinRegattaModal::MAX_ADDED_MEMBERS }} участников.</p>

                    @error('guestMembers')
                        <span class="text-xs text-red-600 mb-2 block">{{ $message }}</span>
                    @enderror

                    @if (!empty($guestMembers))
                        <div class="mb-3 space-y-2">
                            @foreach ($guestMembers as $i => $member)
                                <div class="flex items-center gap-3 py-2 px-3 bg-[#F8F8F8] rounded">
                                    <span class="text-sm flex-1">
                                        {{ $member['name'] }}
                                        @if (!($member['registered'] ?? true))
                                            <span class="text-gray-400 text-xs ml-1">(новый)</span>
                                        @endif
                                    </span>
                                    <select wire:model="guestMembers.{{ $i }}.role"
                                            class="text-sm border-gray-200 bg-white rounded p-1 min-w-[120px]">
                                        <option value="main">Основной</option>
                                        <option value="reserve">Запасной</option>
                                    </select>
                                    <button type="button" wire:click="removeGuestMember('{{ $member['ref'] }}')"
                                            class="text-gray-400 hover:text-red-500 text-xl leading-none">&times;</button>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if (count($guestMembers) >= \App\Livewire\JoinRegattaModal::MAX_ADDED_MEMBERS)
                        <p class="text-xs text-amber-600">Достигнут лимит участников ({{ \App\Livewire\JoinRegattaModal::MAX_ADDED_MEMBERS }}).</p>
                    @else
                    <div class="relative">
                        <input type="text"
                               wire:model.live.debounce.350ms="searchQuery"
                               x-on:keydown="onKeydown($event)"
                               placeholder="Поиск по имени или email..."
                               class="w-full border border-gray-200 bg-[#F8F8F8] rounded px-3 py-2 text-sm focus:border-[#2D92CE] focus:outline-none">

                        <div wire:loading wire:target="searchQuery" class="absolute right-3 top-2.5 text-gray-400 text-xs">
                            Поиск...
                        </div>

                        <div x-show="isOpen" x-cloak
                             class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded shadow-lg max-h-56 overflow-y-auto">
                            <template x-for="(user, index) in results" :key="user.id">
                                <div x-on:click="selectItem(user.id)"
                                     x-on:mouseenter="selectedIndex = index"
                                     :class="{ 'bg-[#2D92CE]/10': selectedIndex === index }"
                                     class="px-3 py-2 cursor-pointer hover:bg-[#2D92CE]/10 text-sm border-b border-gray-100 last:border-b-0">
                                    <span class="font-medium" x-text="user.name"></span>
                                    <span class="text-gray-400 text-xs ml-2" x-text="user.email"></span>
                                </div>
                            </template>
                            <div x-show="results.length === 0 && query.length > 0" class="px-3 py-2 text-sm text-gray-400">
                                Ничего не найдено
                            </div>
                            <div x-show="query.trim().length > 0"
                                 x-on:click="addNewFromQuery()"
                                 class="px-3 py-2 cursor-pointer hover:bg-[#2D92CE]/10 text-sm text-[#2D92CE] font-medium border-t border-gray-100">
                                + Добавить «<span x-text="query.trim()"></span>» как нового участника
                            </div>
                        </div>
                    </div>

                    {{-- Добавление незарегистрированного участника (showNew — в общем scope экипажа) --}}
                    <div class="mt-3">
                        <!--
                        <button type="button" x-show="!showNew" @click="showNew = true"
                                class="text-sm text-[#2D92CE] font-medium hover:underline">
                            + Добавить незарегистрированного участника
                        </button>
                        -->

                        <div x-show="showNew" x-cloak class="mt-2 p-3 bg-[#F8F8F8] rounded space-y-2">
                            <p class="text-xs text-gray-500">
                                Мы автоматически создадим для участника личный кабинет.
                            </p>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">ФИО</label>
                                <input type="text" wire:model="newMemberName"
                                       class="w-full border border-gray-200 bg-white rounded px-3 py-2 text-sm focus:border-[#2D92CE] focus:outline-none">
                                @error('newMemberName')
                                    <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Дата рождения</label>
                                    <input type="date" wire:model="newMemberBirthDate"
                                           class="w-full border border-gray-200 bg-white rounded px-3 py-2 text-sm focus:border-[#2D92CE] focus:outline-none">
                                    @error('newMemberBirthDate')
                                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Спортивный разряд</label>
                                    <select wire:model="newMemberSportCategory"
                                            class="w-full border border-gray-200 bg-white rounded px-3 py-2 text-sm focus:border-[#2D92CE] focus:outline-none">
                                        <option value="">Не указан</option>
                                        @foreach (\App\Enums\SportCategory::cases() as $cat)
                                            <option value="{{ $cat->value }}">{{ $cat->getLabel() }}</option>
                                        @endforeach
                                    </select>
                                    @error('newMemberSportCategory')
                                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="flex gap-2 pt-1">
                                <button type="button" wire:click="addUnregisteredGuestMember"
                                        class="bg-[#2D92CE] text-white text-sm px-3 py-1.5 rounded hover:bg-[#2D92CE]/90">
                                    Добавить
                                </button>
                                <button type="button"
                                        @click="showNew = false; $wire.set('newMemberName', ''); $wire.set('newMemberBirthDate', ''); $wire.set('newMemberSportCategory', '')"
                                        class="text-gray-500 text-sm px-3 py-1.5 hover:text-gray-700">
                                    Отмена
                                </button>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>

                {{-- Документы --}}
                @if ($this->requiredDocuments())
                    <div class="border-t border-gray-200 pt-4">
                        <p class="text-sm font-medium text-[#2E325C] mb-3">Документы</p>
                        @foreach ($this->requiredDocuments() as $doc)
                            <div class="mb-3">
                                <label for="guest_doc_{{ $doc['doc_type'] }}" class="block text-sm text-brand-gray-light">
                                    {{ $doc['title'] }}
                                    @if ($doc['is_required'] ?? false)
                                        <span class="text-red-500">*</span>
                                    @else
                                        <span class="text-gray-400 text-xs">(необязательный)</span>
                                    @endif
                                </label>
                                <input type="file"
                                       id="guest_doc_{{ $doc['doc_type'] }}"
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

                {{-- Пароль заявки: для редактирования на странице регаты без входа --}}
                <div class="border-t border-gray-200 pt-4">
                    <label for="entryPassword" class="block text-sm font-medium text-[#2E325C]">
                        Пароль заявки <span class="text-red-500">*</span>
                    </label>
                    <p class="text-xs text-gray-500 mt-1 mb-2">
                        Запомните его — по этому паролю можно будет редактировать заявку на странице регаты без входа в аккаунт.
                    </p>
                    <input type="text" id="entryPassword" wire:model="entryPassword"
                           placeholder="Придумайте пароль"
                           class="block w-full border-none font-medium shadow-sm bg-[#F8F8F8] sm:text-sm @error('entryPassword') border-red-300 @enderror">
                    @error('entryPassword')
                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mt-5 sm:mt-6">
                    <button type="submit"
                            wire:loading.attr="disabled"
                            class="inline-flex w-full justify-center bg-[#2D92CE] px-3 py-2 text-sm font-semibold text-white shadow hover:bg-[#2D92CE]/90 disabled:opacity-50">
                        <span wire:loading.remove wire:target="submitGuest">Подать заявку →</span>
                        <span wire:loading wire:target="submitGuest">Отправка...</span>
                    </button>
                </div>
            </form>
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
        @endif
    </div>
</div>
