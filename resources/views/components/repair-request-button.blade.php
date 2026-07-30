@props([
    // Кейс, с чьей страницы отправляется заявка. null — обзорная страница раздела.
    'case' => null,
    'label' => 'Хотите такой ремонт?',
])

{{--
    Кнопка «Хотите такой ремонт?» с собственной модалкой.

    Состояние локальное (Alpine x-data на обёртке), а не глобальное как у
    question-modal: кнопка встречается на странице несколько раз и на каждом
    кейсе, глобальный флаг здесь только мешал бы.
--}}
<div x-data="{
        open: false,
        submitted: false,
        loading: false,
        error: '',

        async submitForm() {
            this.error = '';
            this.loading = true;

            try {
                const response = await fetch('{{ route('carter30.repair-request') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({
                        repair_case_id: @js($case?->getKey()),
                        name: this.$refs.name.value,
                        phone: this.$refs.phone.value,
                        email: this.$refs.email.value,
                        comment: this.$refs.comment.value,
                        privacy: this.$refs.privacy.checked,
                    }),
                });

                if (!response.ok) {
                    if (response.status === 429) {
                        throw new Error('Слишком много заявок подряд. Попробуйте через минуту.');
                    }

                    const data = await response.json();
                    const firstError = Object.values(data.errors ?? {})[0]?.[0];
                    throw new Error(firstError || data.message || 'Произошла ошибка при отправке.');
                }

                this.submitted = true;
            } catch (err) {
                this.error = err.message || 'Произошла ошибка при отправке. Попробуйте позже.';
            } finally {
                this.loading = false;
            }
        },
     }"
     {{ $attributes->merge(['class' => 'inline-block']) }}>

    <button type="button" @click="open = true"
            class="bg-[#2D92CE] text-white py-3 px-8 hover:bg-[#0074CC] transition-colors md:text-lg text-sm font-semibold">
        {{ $label }} →
    </button>

    <div x-show="open" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         role="dialog" aria-modal="true">

        <div x-show="open"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 transition-opacity bg-black/50 z-20"></div>

        <div x-show="open"
             @click.outside="open = false"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="px-3 py-3 relative overflow-hidden transition-all bg-white w-full max-w-[500px] z-30 top-1/2 left-1/2 -translate-1/2">

            <div class="bg-white p-3.5 md:p-2 text-left">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="a-font text-2xl md:text-3xl">Заявка на ремонт</h3>
                    <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-500 text-2xl font-bold">&times;</button>
                </div>

                @if ($case)
                    <p class="text-brand-gray-light text-sm mb-4">Кейс: {{ $case->title }}</p>
                @endif

                <div x-show="submitted" x-cloak
                     class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 mb-4" role="alert">
                    Спасибо! Заявка отправлена — мы свяжемся с вами в ближайшее время.
                </div>

                <div x-show="error" x-cloak
                     class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 mb-4" role="alert">
                    <span x-text="error"></span>
                </div>

                <form @submit.prevent="submitForm()" x-show="!submitted">
                    @csrf
                    <input type="text" x-ref="name" required maxlength="255"
                           value="{{ auth()->user()?->name }}"
                           placeholder="Ваше имя"
                           class="block appearance-none border border-[#C6C6C6] w-full mb-4 text-sm md:text-base p-3">
                    <input type="tel" x-ref="phone" required maxlength="50"
                           value="{{ auth()->user()?->phone }}"
                           placeholder="Телефон"
                           class="block appearance-none border border-[#C6C6C6] w-full mb-4 text-sm md:text-base p-3">
                    <input type="email" x-ref="email" maxlength="255"
                           value="{{ auth()->user()?->email }}"
                           placeholder="Email (необязательно)"
                           class="block appearance-none border border-[#C6C6C6] w-full mb-4 text-sm md:text-base p-3">
                    <textarea x-ref="comment" maxlength="2000"
                              placeholder="Что нужно сделать"
                              class="block appearance-none border border-[#C6C6C6] w-full mb-4 text-sm md:text-base p-3 min-h-[120px]"></textarea>

                    <button type="submit" class="bg-[#2D92CE] text-white text-center w-full py-4 font-semibold"
                            :disabled="loading"
                            x-text="loading ? 'Отправка...' : 'Отправить заявку'"></button>

                    <div class="privacy flex gap-4 mt-4">
                        <label class="custom-checkbox">
                            <input type="checkbox" x-ref="privacy" required/>
                            <span class="checkbox-box shrink-0"></span>
                            <div class="text-sm text-brand-gray-light">Отправляя данные через форму, вы соглашаетесь с <a class='underline' href="/files/Политика_обработки_персональных_данных_1.pdf">политикой обработки персональных данных</a></div>
                        </label>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
