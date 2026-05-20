<div x-data="{ 
        isRequestModalOpen: true, 
     }"
     @requestModal.window="isRequestModalOpen = true" 
     x-show="isRequestModalOpen"
     style="display: none;"
     class="fixed inset-0 z-50 overflow-y-auto"
     aria-labelledby="modal-title" 
     role="dialog" 
     aria-modal="true">
    
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
                    } finally {
                        this.loading = false;
                    }
                },
             }">
                <h3 class="a-font text-2xl md:text-4xl md:pb-6">Остались вопросы?</h3>
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
                        type="tel" name="phone" placeholder="Ваш номер телефона" required maxlength="20"
                        x-ref="phone">
                    <button class="bg-[#2D92CE] text-white text-center w-full py-4 font-semibold mt-4"
                            :disabled="loading"
                            x-text="loading ? 'Отправка...' : 'Отправить'"></button>
                    <div class="privacy flex gap-4 mt-4">
                        <style>
                            /* Контейнер для всего компонента */
                            .custom-checkbox {
                            display: inline-flex;
                            align-items: center;
                            cursor: pointer;
                            user-select: none;
                            font-family: sans-serif;
                            font-size: 16px;
                            color: #333;
                            }

                            /* Скрываем дефолтный чекбокс, но оставляем его доступным для скринридеров */
                            .custom-checkbox input {
                            position: absolute;
                            opacity: 0;
                            cursor: pointer;
                            height: 0;
                            width: 0;
                            }

                            /* Создаем кастомный квадрат */
                            .checkbox-box {
                            position: relative;
                            height: 22px;
                            width: 22px;
                            background-color: #fff;
                            border: 2px solid #2D92CE;
                            margin-right: 10px;
                            transition: all 0.2s ease-in-out;
                            }

                            /* Эффект при наведении на неотмеченный чекбокс */
                            .custom-checkbox:hover input ~ .checkbox-box {
                            border-color: #2D92CE;
                            }

                            /* Стили для чекбокса, когда он СТАНОВИТСЯ отмеченным */
                            .custom-checkbox input:checked ~ .checkbox-box {
                            background-color: #2D92CE;
                            border-color: #2D92CE;
                            }

                            /* Создаем галочку внутри (изначально она скрыта) */
                            .checkbox-box::after {
                            content: "";
                            position: absolute;
                            display: none;
                            
                            /* Рисуем галочку с помощью границ прямоугольника */
                            left: 7px;
                            top: 3px;
                            width: 5px;
                            height: 10px;
                            border: solid white;
                            border-width: 0 2px 2px 0;
                            transform: rotate(45deg);
                            }

                            /* Показываем галочку при активации */
                            .custom-checkbox input:checked ~ .checkbox-box::after {
                            display: block;
                            }

                            /* Добавляем фокус для доступности с клавиатуры (Tab) */
                            .custom-checkbox input:focus-visible ~ .checkbox-box {
                            outline: 2px solid #0056b3;
                            outline-offset: 2px;
                            }
                        </style>
                        <label class="custom-checkbox">
                            <input type="checkbox" name="privacy" required/>
                            <span class="checkbox-box shrink-0"></span>
                            <div class="text-sm text-brand-gray-light">Отправляя данные через форму, вы соглашаетесь с <a class='underline' href="#">политикой обработки персональных данных</a></div>
                        </label>
                        
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>