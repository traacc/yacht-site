<section style="background-image: url('{{ asset('images/bg/feedback_bg.png') }}');" class="feedback-form bg-[#2E325C] bg-top-right
    bg-no-repeat py-24">
    <div class="max-w-(--breakpoint-2xl) m-auto pt-5">
        <div class="form-wrapper bg-white max-w-lg p-6"
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
            <h3 class="a-font text-4xl pb-6">Остались вопросы?</h3>
            <p class="text-brand-gray pb-6">По вопросам участия в соревнованиях, вступления в Ассоциацию и другим организационным вопросам вы можете обратиться к нам.</p>

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

            <form @submit.prevent="submitForm()" x-show="!submitted">
                @csrf
                <input class="block appearance-none border-0 border-b border-b-[#C6C6C6] w-full mb-4"
                       type="text" name="name" placeholder="Ваше имя" required maxlength="255"
                       x-ref="name">
                <input class="block appearance-none border-0 border-b border-b-[#C6C6C6] w-full mb-4"
                       type="tel" name="phone" placeholder="Ваш номер телефона" required maxlength="20"
                       x-ref="phone">
                <button class="bg-[#2D92CE] text-white text-center w-full py-4 font-semibold mt-4"
                        :disabled="loading"
                        x-text="loading ? 'Отправка...' : 'Отправить'"></button>
                <div class="privacy flex gap-4 mt-4">
                    <x-checkbox name="privacy" color="zinc" class="rounded-none" required/>
                    <div class="text-sm text-brand-gray-light">Отправляя данные через форму, вы соглашаетесь с <a class='underline' href="#">политикой обработки персональных данных</a></div>
                </div>
            </form>
        </div>
    </div>
</section>