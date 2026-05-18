<div x-data="{ isOpen: false, tab: 'login' }" 
     @open-login-modal.window="isOpen = true" 
     @keydown.escape.window="isOpen = false">


    <div x-show="isOpen" 
         class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto" 
         style="display: none;">
        
        <div x-show="isOpen" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="isOpen = false" 
             class="fixed inset-0 bg-black/50 transition-opacity">
        </div>

        <div x-show="isOpen" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-md sm:w-full p-6 z-10">
            
            <div class="flex items-center justify-between pb-3">
                <h3 class="text-lg font-medium text-gray-900">Вход в аккаунт</h3>
                <button @click="isOpen = false" class="text-gray-400 hover:text-gray-500">
                    <span class="sr-only">Закрыть</span>
                    &#x2715; </button>
            </div>

            <form wire:submit.prevent="login" class="space-y-4 mt-2" x-show="tab === 'login'">
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" id="email" wire:model="email" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('email') border-red-300 @enderror">
                    @error('email') 
                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> 
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Пароль</label>
                    <input type="password" id="password" wire:model="password" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('password') border-red-300 @enderror">
                    @error('password') 
                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> 
                    @enderror
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input type="checkbox" id="remember" wire:model="remember" 
                               class="h-4 w-4 rounded-sm border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <label for="remember" class="ml-2 block text-sm text-gray-900">Запомнить меня</label>
                    </div>
                </div>

                <div class="mt-5 sm:mt-6">
                    <button type="submit" 
                            wire:loading.attr="disabled"
                            class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50">
                        <span wire:loading.remove>Войти</span>
                        <span wire:loading>Входим...</span>
                    </button>
                </div>
                
                <p>Нет аккаунта? <a @click="tab = 'register'" href="#">Зарегистрироватся</a></p>
                
            </form>
            
            <form wire:submit.prevent="register" class="space-y-4 mt-2" x-show="tab === 'register'">
                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700">Имя</label>
                    <input type="text" id="first_name" wire:model="first_name" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('first_name') border-red-300 @enderror">
                    @error('first_name') 
                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> 
                    @enderror
                </div>
                <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700">Фамилия</label>
                    <input type="text" id="last_name" wire:model="last_name" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('last_name') border-red-300 @enderror">
                    @error('last_name') 
                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> 
                    @enderror
                </div>
                <div>
                    <label for="birthday" class="block text-sm font-medium text-gray-700">Дата рождения</label>
                    <input type="date" id="birthday" wire:model="birthday" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('birthday') border-red-300 @enderror">
                    @error('birthday') 
                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> 
                    @enderror
                </div>

                <div>
                    <label for="sports_category" class="block text-sm font-medium text-gray-700">Категория</label>
                    <select id="sports_category" wire:model="sports_category" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('sports_category') border-red-300 @enderror">
                        <option value="">Спортивный разряд</option>
                        <option value="МС">МС</option>
                    </select>
                    @error('sports_category') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">E-mail</label>
                    <input type="email" id="email" wire:model="email" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('email') border-red-300 @enderror">
                    @error('email') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700">Телефон</label>
                    <input type="tel" id="phone" wire:model="phone" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('phone') border-red-300 @enderror">
                    @error('phone') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Пароль</label>
                    <input type="password" id="password" wire:model="password" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('password') border-red-300 @enderror">
                    @error('password') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Подтвердите пароль</label>
                    <input type="password" id="password_confirmation" wire:model="password_confirmation" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>

                <div class="mt-5 sm:mt-6">
                    <button type="submit" 
                            wire:loading.attr="disabled"
                            class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50">
                        <span wire:loading.remove>Зарегистрироватся</span>
                        <span wire:loading>Регестрируемся...</span>
                    </button>
                </div>
                <p>Есть аккаунт? <a @click="tab = 'login'" href="#">Войти</a></p>
            </form>
        </div>
    </div>
</div>