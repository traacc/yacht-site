<div x-data="{ 
        
     }"

     x-show="isRequestModalOpen"
     class="fixed inset-0 z-50 overflow-y-auto"
     aria-labelledby="modal-title" 
     role="dialog" 
     aria-modal="true"
     style="display:none"
     >
    
    <div 
            x-show="isRequestModalOpen"
            x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 transition-opacity bg-black/50 z-20" 
    class="flex items-center justify-center min-h-screen p-4 pb-20 text-center sm:block sm:p-0">
        

        <!-- Само модальное окно -->
        <div x-show="isRequestModalOpen"
            @click.outside="isRequestModalOpen = false"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="px-3 py-3 relative overflow-hidden transition-all bg-white w-full max-w-[500px] z-30 top-1/2 left-1/2 -translate-1/2">
            

        <div class="form-wrapper bg-white max-w-lg p-3.5 md:p-2 mx-3 z-20 relative"
             x-data="{
                submitted: false,
                loading: false,
                error: '',
                captchaToken: '',
                successMessage: 'Спасибо! Ваша заявка успешно отправлена. Мы свяжемся с вами в ближайшее время.',

                async submitForm() {
                    this.error = '';
                    this.loading = true;

                    try {
                        const response = await fetch('{{ route('feedback.submit') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({
                                name: this.$refs.name.value,
                                phone: this.$refs.phone.value,
                                captchaToken: this.captchaToken,
                            }),
                        });

                        if (!response.ok) {
                            const data = await response.json();
                            throw new Error(data.message || 'Произошла ошибка при отправке.');
                        }

                        this.submitted = true;
                        this.$refs.name.value = '';
                        this.$refs.phone.value = '';
                    } catch (err) {
                        this.error = err.message || 'Произошла ошибка при отправке. Попробуйте позже.';
                        // Токен капчи одноразовый — после ошибки нужен новый.
                        this.captchaToken = '';
                        window.yandexCaptcha?.reset('request-modal');
                    } finally {
                        this.loading = false;
                    }
                },
             }">
                @guest
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-[#2E325C] a-font">Сначала зарегистрируетесь</h3>
                    <button @click="isRequestModalOpen=false" class="text-gray-400 hover:text-gray-500 text-2xl font-bold">&times;</button>
                </div>
                <div class="text-center py-6">
                    <p class="text-gray-600 mb-6">Чтобы подать заявку на вступление, необходимо войти в аккаунт участника Ассоциации.</p>
                    <button @click="$dispatch('open-login-modal');isRequestModalOpen=false; "
                            class="inline-flex justify-center bg-[#2D92CE] px-4 py-2 text-sm font-semibold text-white shadow hover:bg-[#2D92CE]/90">
                        Войти или зарегистрироваться
                    </button>
                </div>
                @else
                <h3 class="a-font text-2xl md:text-4xl md:pb-6">Подайте заявку</h3>
            
                <p class="text-brand-gray pb-6 text-sm md:text-base">По вопросам участия в соревнованиях, вступления в Ассоциацию и другим организационным вопросам вы можете обратиться к нам.</p>

                {{-- Сообщение об успехе (AJAX) --}}
                <div x-show="submitted" x-cloak
                    class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 mb-4" role="alert">
                    <span class="block sm:inline" x-text="successMessage"></span>
                </div>

                {{-- Сообщение об ошибке (AJAX) --}}
                <div x-show="error" x-cloak
                    class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 mb-4" role="alert">
                    <span class="block sm:inline" x-text="error"></span>
                </div>

                {{-- Сообщение об успехе (fallback при обычной отправке) --}}
                @if (session('feedback_sent'))
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 mb-4" role="alert">
                        <span class="block sm:inline">Спасибо! Ваша заявка успешно отправлена. Мы свяжемся с вами в ближайшее время.</span>
                    </div>
                @endif

                <form @submit.prevent="submitForm()" x-show="!submitted" class="">
                    @csrf
                    <input class="block appearance-none border-0 border-b border-b-[#C6C6C6] w-full mb-4 text-sm md:text-base"
                        type="text" name="name" placeholder="Ваше имя" required maxlength="255"
                        x-ref="name">
                    <input class="block appearance-none border-0 border-b border-b-[#C6C6C6] w-full mb-4 text-sm md:text-base"
                        type="tel" name="phone" placeholder="+7 (___) ___-__-__" required maxlength="20"
                        x-mask="+7 (999) 999-99-99"
                        x-ref="phone">

                    <x-yandex-captcha name="request-modal" callback="captchaToken = token" />

                    <button class="bg-[#2D92CE] text-white text-center w-full py-4 font-semibold mt-4"
                            :disabled="loading"
                            x-text="loading ? 'Отправка...' : 'Отправить'"></button>
                    <div class="privacy flex gap-4 mt-4">
                        <label class="custom-checkbox">
                            <input type="checkbox" name="privacy" required/>
                            <span class="checkbox-box shrink-0"></span>
                            <div class="text-sm text-brand-gray-light">Отправляя данные через форму, вы соглашаетесь с <a class='underline' href="/files/Политика_обработки_персональных_данных_1.pdf">политикой обработки персональных данных</a></div>
                        </label>
                        
                    </div>
                </form>
                @endguest
            </div>
            
        </div>
    </div>
</div>