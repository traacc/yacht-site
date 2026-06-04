<div
    x-data="{ visible: ! localStorage.getItem('cookie_consent') }"
    x-show="visible"
    x-cloak
    x-transition:enter="transition ease-out duration-500"
    x-transition:enter-start="opacity-0 translate-y-full"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-300"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 translate-y-full"
    class="fixed bottom-0 left-0 right-0 z-[9999] bg-white/95 backdrop-blur-md border-t border-gray-200 shadow-2xl"
>
    <div class="max-w-7xl mx-auto px-4 py-4 md:py-5 flex flex-col sm:flex-row items-center gap-4 sm:gap-6">
        <div class="flex items-start gap-3 flex-1">
            <div>
                <p class="text-sm md:text-base text-gray-800 font-medium">Мы используем cookies</p>
                <p class="text-xs md:text-sm text-gray-500 mt-0.5">
                    Продолжая использовать сайт, вы соглашаетесь на обработку
                    <a href="/files/Политика_обработки_персональных_данных_1.pdf" class="text-[#2D92CE] hover:underline">персональных данных</a>
                    и использование cookie-файлов.
                </p>
            </div>
        </div>
        <div class="flex items-center gap-3 shrink-0">
            <button
                @click="localStorage.setItem('cookie_consent', 'accepted'); visible = false"
                class="px-6 py-2.5 bg-[#2D92CE] text-white text-sm font-semibold  hover:opacity-90 transition-opacity"
            >
                Принять
            </button>
            <button
                @click="visible = false"
                class="px-6 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors"
            >
                Закрыть
            </button>
        </div>
    </div>
</div>