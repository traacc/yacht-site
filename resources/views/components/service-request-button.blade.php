@props([
    // App\Enums\ServiceType — задаёт маршрут, набор полей и подписи.
    'type',
    'label' => 'Оставить заявку',
    'heading' => null,
    // Предзаполнение формы: ['date_start' => …, 'quantity' => …, 'payload' => [...]].
    'preset' => [],
])

{{--
    Кнопка «Оставить заявку» с модалкой для любого подраздела «Услуг».

    Поля собираются из ServiceType::payloadFields(), а не верстаются вручную,
    иначе модалку пришлось бы копировать под каждый из семи подразделов.
    Состояние локальное (x-data на обёртке): кнопка встречается на странице
    несколько раз.
--}}
@php
    $user = auth()->user();

    $initialForm = array_merge([
        'name' => $user?->name ?? '',
        'phone' => $user?->phone ?? '',
        'email' => $user?->email ?? '',
        'comment' => '',
        'date_start' => null,
        'date_end' => null,
        'quantity' => null,
        'privacy' => false,
        'payload' => (object) [],
    ], $preset);
@endphp

<div x-data="{
        open: false,
        submitted: false,
        loading: false,
        error: '',
        form: @js($initialForm),

        async submitForm() {
            this.error = '';
            this.loading = true;

            try {
                const response = await fetch('{{ route('services.request', $type) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify(this.form),
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
             class="px-3 py-3 relative overflow-y-auto max-h-[90vh] transition-all bg-white w-full max-w-[500px] z-30 top-1/2 left-1/2 -translate-1/2">

            <div class="bg-white p-3.5 md:p-2 text-left">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="a-font text-2xl md:text-3xl">{{ $heading ?? 'Заявка на услугу' }}</h3>
                    <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-500 text-2xl font-bold">&times;</button>
                </div>

                <p class="text-brand-gray-light text-sm mb-4">{{ $type->label() }}</p>

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
                    <input type="text" x-model="form.name" required maxlength="255"
                           placeholder="Ваше имя"
                           class="block appearance-none border border-[#C6C6C6] w-full mb-4 text-sm md:text-base p-3">

                    <input type="tel" x-model="form.phone" required maxlength="50"
                           placeholder="Телефон"
                           class="block appearance-none border border-[#C6C6C6] w-full mb-4 text-sm md:text-base p-3">

                    <input type="email" x-model="form.email" maxlength="255"
                           @if ($type->requiresEmail()) required @endif
                           placeholder="Email{{ $type->requiresEmail() ? '' : ' (необязательно)' }}"
                           class="block appearance-none border border-[#C6C6C6] w-full mb-4 text-sm md:text-base p-3">

                    @if ($type->usesDateRange())
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <label class="block">
                                <span class="block text-sm text-brand-gray-light mb-1">Дата начала</span>
                                <input type="date" x-model="form.date_start"
                                       @if ($type->requiresDateRange()) required @endif
                                       class="block appearance-none border border-[#C6C6C6] w-full text-sm md:text-base p-3">
                            </label>
                            <label class="block">
                                <span class="block text-sm text-brand-gray-light mb-1">Дата окончания</span>
                                <input type="date" x-model="form.date_end"
                                       @if ($type->requiresDateRange()) required @endif
                                       class="block appearance-none border border-[#C6C6C6] w-full text-sm md:text-base p-3">
                            </label>
                        </div>
                    @endif

                    @if ($type->usesQuantity())
                        <label class="block mb-4">
                            <span class="block text-sm text-brand-gray-light mb-1">{{ $type->quantityLabel() }}</span>
                            <input type="number" x-model="form.quantity" min="1" max="500"
                                   class="block appearance-none border border-[#C6C6C6] w-full text-sm md:text-base p-3">
                        </label>
                    @endif

                    @foreach ($type->payloadFields() as $key => $field)
                        @if ($field['type'] === 'select')
                            <label class="block mb-4">
                                <span class="block text-sm text-brand-gray-light mb-1">{{ $field['label'] }}</span>
                                <select x-model="form.payload['{{ $key }}']"
                                        @if ($field['required'] ?? false) required @endif
                                        class="block appearance-none border border-[#C6C6C6] w-full text-sm md:text-base p-3 bg-white">
                                    <option value="">Выберите вариант</option>
                                    @foreach ($field['options'] ?? [] as $value => $optionLabel)
                                        <option value="{{ $value }}">{{ $optionLabel }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @elseif ($field['type'] === 'checkbox')
                            <div class="privacy flex gap-4 mb-4">
                                <label class="custom-checkbox">
                                    <input type="checkbox" x-model="form.payload['{{ $key }}']"/>
                                    <span class="checkbox-box shrink-0"></span>
                                    <div class="text-sm text-brand-gray-light">{{ $field['label'] }}</div>
                                </label>
                            </div>
                        @elseif ($field['type'] === 'textarea')
                            <textarea x-model="form.payload['{{ $key }}']" maxlength="2000"
                                      @if ($field['required'] ?? false) required @endif
                                      placeholder="{{ $field['placeholder'] ?? $field['label'] }}"
                                      class="block appearance-none border border-[#C6C6C6] w-full mb-4 text-sm md:text-base p-3 min-h-[100px]"></textarea>
                        @else
                            <input type="text" x-model="form.payload['{{ $key }}']" maxlength="255"
                                   @if ($field['required'] ?? false) required @endif
                                   placeholder="{{ $field['placeholder'] ?? $field['label'] }}"
                                   class="block appearance-none border border-[#C6C6C6] w-full mb-4 text-sm md:text-base p-3">
                        @endif
                    @endforeach

                    <textarea x-model="form.comment" maxlength="2000"
                              placeholder="Комментарий"
                              class="block appearance-none border border-[#C6C6C6] w-full mb-4 text-sm md:text-base p-3 min-h-[100px]"></textarea>

                    <button type="submit" class="bg-[#2D92CE] text-white text-center w-full py-4 font-semibold"
                            :disabled="loading"
                            x-text="loading ? 'Отправка...' : 'Отправить заявку'"></button>

                    <div class="privacy flex gap-4 mt-4">
                        <label class="custom-checkbox">
                            <input type="checkbox" x-model="form.privacy" required/>
                            <span class="checkbox-box shrink-0"></span>
                            <div class="text-sm text-brand-gray-light">Отправляя данные через форму, вы соглашаетесь с <a class='underline' href="/files/Политика_обработки_персональных_данных_1.pdf">политикой обработки персональных данных</a></div>
                        </label>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
