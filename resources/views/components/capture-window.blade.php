<div x-data="{
        isOpen: false,
        intervalId: null,
        init() {
            const checkAndShow = () => {
                let nextShowTime = localStorage.getItem('capture_window_next_show');
                let now = Date.now();
                
                if (!nextShowTime || now >= parseInt(nextShowTime)) {
                    if (!this.isOpen) {
                        this.isOpen = true;
                        // Устанавливаем время следующего показа через 10 минут
                        localStorage.setItem('capture_window_next_show', now + 10 * 60 * 1000);
                    }
                }
            };
            
            // Проверяем сразу с задержкой для UX
            setTimeout(() => checkAndShow(), 2000);
            
            // Проверяем каждую минуту, не пора ли показать окно
            this.intervalId = setInterval(() => checkAndShow(), 60000);
            
            // Очистка интервала при уничтожении компонента
            this.$cleanup = () => {
                if (this.intervalId) {
                    clearInterval(this.intervalId);
                }
            };
        },
        closeModal() {
            this.isOpen = false;
        }
     }"
     x-show="isOpen"
     style="display: none;"
     class="fixed inset-0 z-50 overflow-y-auto"
     aria-labelledby="modal-title"
     role="dialog"
     aria-modal="true">
    
    <div 
            x-show="isOpen"
            x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 transition-opacity bg-black/50 z-20" 
    class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        

        <!-- Само модальное окно -->
        <div x-show="isOpen"
            @click.outside="isOpen = false"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="px-6 py-12 relative overflow-hidden transition-all bg-white max-w-[1000px] w-full z-30 top-1/2 left-1/2 -translate-1/2">
            

            <!-- Контент формы захвата -->
            <img class="absolute right-0 top-0 z-0 h-full md:h-auto " src="{!! asset('images/bg/capture-form.png') !!}" alt="">
            <button @click="isOpen = false" class="text-2xl md:text-white text-[#2E325C] absolute right-0 top-0 font-bold z-30">{!! file_get_contents(public_path('images/icons/close.svg')) !!}</button>
            <div class="absolute hidden md:block inset-0 left-[40%] w-[60%] bg-linear-to-r from-[#FFFFFF] to-[#FFFFFF]/75 z-2"></div>
            <div class="absolute block md:hidden inset-0 left-[0%] w-full bg-linear-to-r from-[#FFFFFF] to-[#FFFFFF]/80 z-2"></div>
            <div class="max-w-[562px] relative z-10">
                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                    <h3 class="text-4xl text-[#2E325C] a-font mb-5">
                        Хотите гоняться с нами?
                    </h3>

                    <p class="text-lg text-[#444] mb-5">
                        Переходите в официальные сообщества CarterPro, чтобы получать анонсы регат, новости и обновления сезона.
                    </p>
                    <style>
                        .social a svg {
                            width: 64px;
                        }
                    </style>
                    <div class="social mt-2 flex gap-2 justify-center">
                        <a href="#" class="text-[#2D92CE] hover:text-white">
                            {!! file_get_contents(public_path('images/social_icons/tl.svg')) !!}
                        </a>
                        <a href="#" class="text-[#2D92CE] hover:text-white">
                            {!! file_get_contents(public_path('images/social_icons/vk.svg')) !!}
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
