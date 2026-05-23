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
             class="bg-white overflow-hidden shadow-xl transform transition-all sm:max-w-md sm:w-full p-6 z-10">
            
            <div class="flex items-center justify-between pb-3">
                <h3 class="text-3xl a-font text-[#2E325C]">Вход в аккаунт</h3>
                <button @click="isOpen = false" class="text-gray-400 hover:text-gray-500">
                    <span class="sr-only">Закрыть</span>
                    &#x2715; </button>
            </div>

            <form wire:submit.prevent="login" class="space-y-4 mt-2" x-show="tab === 'login'">
                
                <div>
                    <input type="email" id="email" wire:model="email" placeholder="Адрес электронной почты"
                           class="mt-1 block w-full border-0 border-b border-[#EAEAEA] sm:text-sm @error('email') border-red-300 @enderror">
                    @error('email') 
                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> 
                    @enderror
                </div>

                <div>
                    <input type="password" id="password" wire:model="password"  placeholder="Пароль"
                           class="mt-1 block w-full border-0 border-b border-[#EAEAEA] sm:text-sm @error('password') border-red-300 @enderror">
                    @error('password') 
                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> 
                    @enderror
                </div>
                <div class="text-right text-brand-gray-light text-sm">
                    <a href="#">Забыли пароль?</a>
                </div>
                <div class="mt-5 sm:mt-6">
                    <button type="submit" 
                            wire:loading.attr="disabled"
                            class="inline-flex w-full justify-center bg-[#2D92CE] px-3 py-2 text-sm font-semibold text-white shadow-xs focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50">
                        <span wire:loading.remove>Войти</span>
                        <span wire:loading>Входим...</span>
                    </button>
                </div>
                
                <p class="text-center">Нет аккаунта? <a class="text-[#2D92CE]" @click="tab = 'register'" href="#">Зарегистрироватся</a></p>
                
            </form>
            
            <form wire:submit.prevent="register" class="space-y-4 mt-2" x-show="tab === 'register'">
                <div>
                    <input type="text" id="first_name" wire:model="first_name" placeholder="Имя"
                           class="mt-1 block w-full border-0 border-b border-[#EAEAEA] shadow-xs focus:border-indigo-500 focus:ring-indigo-500 text-xs md:text-base @error('first_name') border-red-300 @enderror">
                    @error('first_name') 
                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> 
                    @enderror
                </div>
                <div>
                    <input type="text" id="last_name" wire:model="last_name" placeholder="Фамилия"
                           class="mt-1 block w-full border-0 border-b border-[#EAEAEA] shadow-xs focus:border-indigo-500 focus:ring-indigo-500 text-xs md:text-base @error('last_name') border-red-300 @enderror">
                    @error('last_name') 
                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> 
                    @enderror
                </div>
                <div>
                    <input type="date" id="birthday" wire:model="birthday" placeholder="День рождения"
                           class="mt-1 block w-full border-0 border-b border-[#EAEAEA] shadow-xs focus:border-indigo-500 focus:ring-indigo-500 text-xs md:text-base @error('birthday') border-red-300 @enderror">
                    @error('birthday') 
                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> 
                    @enderror
                </div>

                <div>
                    <select id="sports_category" wire:model="sports_category" placeholder="Спортивный разряд"
                           class="mt-1 block w-full border-0 border-b border-[#EAEAEA] shadow-xs focus:border-indigo-500 focus:ring-indigo-500 text-xs md:text-base @error('sports_category') border-red-300 @enderror">
                        <option value="" disabled selected>Спортивный разряд</option>
                        <option value="3">Третий</option>
                        <option value="2">Второй</option>
                        <option value="1">Первый</option>
                        <option value="kms">КМС</option>
                        <option value="ms">МС</option>
                        <option value="msmk">МСМК</option>
                        <option value="zms">ЗМС</option>
                    </select>
                    @error('sports_category') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <input type="email" id="email" wire:model="email" placeholder="E-Mail"
                           class="mt-1 block w-full border-0 border-b border-[#EAEAEA] shadow-xs focus:border-indigo-500 focus:ring-indigo-500 text-xs md:text-base @error('email') border-red-300 @enderror">
                    @error('email') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <input type="tel" id="phone" wire:model="phone" placeholder="Номер телефона"
                           class="mt-1 block w-full border-0 border-b border-[#EAEAEA] shadow-xs focus:border-indigo-500 focus:ring-indigo-500 text-xs md:text-base @error('phone') border-red-300 @enderror">
                    @error('phone') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <input type="password" id="password" wire:model="password"  placeholder="Пароль"
                           class="mt-1 block w-full border-0 border-b border-[#EAEAEA] shadow-xs focus:border-indigo-500 focus:ring-indigo-500 text-xs md:text-base @error('password') border-red-300 @enderror">
                    @error('password') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <input type="password" id="password_confirmation" wire:model="password_confirmation" placeholder="Подтвердите пароль"
                           class="mt-1 block w-full border-0 border-b border-[#EAEAEA] shadow-xs focus:border-indigo-500 focus:ring-indigo-500 text-xs md:text-base">
                </div>

                <div class="mt-5 sm:mt-6">
                    <button type="submit" 
                            wire:loading.attr="disabled"
                            class="inline-flex w-full justify-center rounded-md bg-[#2D92CE] px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50">
                        <span wire:loading.remove>Зарегистрироватся</span>
                        <span wire:loading>Регестрируемся...</span>
                    </button>
                </div>
                <p class="text-center">Есть аккаунт? <a @click="tab = 'login'" href="#">Войти</a></p>
            </form>
        </div>
    </div>
</div>