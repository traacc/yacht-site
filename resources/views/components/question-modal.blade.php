<div x-show="isQuestionModalOpen"
     class="fixed inset-0 z-50 overflow-y-auto"
     aria-labelledby="question-modal-title"
     role="dialog"
     aria-modal="true"
     style="display:none"
     >

    <div
            x-show="isQuestionModalOpen"
            x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 transition-opacity bg-black/50 z-20"
    class="flex items-center justify-center min-h-screen p-4 pb-20 text-center sm:block sm:p-0">


        <!-- Само модальное окно -->
        <div x-show="isQuestionModalOpen"
            @click.outside="isQuestionModalOpen = false"
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
                successMessage: 'Спасибо! Ваш вопрос успешно отправлен. Мы ответим вам в ближайшее время.',

                async submitForm() {
                    this.error = '';
                    this.loading = true;

                    try {
                        const response = await fetch('{{ route('questions.store') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({
                                question: this.$refs.question.value,
                            }),
                        });

                        if (!response.ok) {
                            const data = await response.json();
                            throw new Error(data.message || 'Произошла ошибка при отправке.');
                        }

                        this.submitted = true;
                        this.$refs.question.value = '';
                    } catch (err) {
                        this.error = err.message || 'Произошла ошибка при отправке. Попробуйте позже.';
                    } finally {
                        this.loading = false;
                    }
                },
             }">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="a-font text-2xl md:text-3xl" id="question-modal-title">Задать вопрос</h3>
                    <button @click="isQuestionModalOpen=false" class="text-gray-400 hover:text-gray-500 text-2xl font-bold">&times;</button>
                </div>

                @guest
                <div class="text-center py-6">
                    <p class="text-gray-600 mb-6">Чтобы задать вопрос администрации, необходимо войти в аккаунт участника Ассоциации.</p>
                    <button @click="$dispatch('open-login-modal');isQuestionModalOpen=false;"
                            class="inline-flex justify-center bg-[#2D92CE] px-4 py-2 text-sm font-semibold text-white shadow hover:bg-[#2D92CE]/90">
                        Войти или зарегистрироваться
                    </button>
                </div>
                @else
                <p class="text-brand-gray pb-6 text-sm md:text-base">Задайте интересующий вас вопрос — мы ответим вам в ближайшее время.</p>

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

                <form @submit.prevent="submitForm()" x-show="!submitted">
                    @csrf
                    <textarea
                        class="block appearance-none border border-[#C6C6C6] w-full mb-4 text-sm md:text-base p-3 min-h-[120px]"
                        name="question" placeholder="Ваш вопрос" required maxlength="2000"
                        x-ref="question"></textarea>
                    <button class="bg-[#2D92CE] text-white text-center w-full py-4 font-semibold mt-4"
                            :disabled="loading"
                            x-text="loading ? 'Отправка...' : 'Отправить вопрос'"></button>
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
