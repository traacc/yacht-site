<x-public-layout
    title="Аренда флота — Yacht Association"
    description="Подбор нескольких яхт на нужный диапазон дат: корпоративная регата, тренировка, съёмки.">

    <x-breadcrumbs_page title="Аренда флота"></x-breadcrumbs_page>

    <x-hero-section
        title="Аренда флота"
        desc="Подбор нескольких яхт на нужный диапазон дат"
        bgImage="{{ $heroImage ?? asset('images/bg/yachts.webp') }}"></x-hero-section>

    <section class="py-10 px-4 sm:px-6 lg:px-8">
        <div class="container mx-auto">
            @if (trim(strip_tags($intro, '<img>')) !== '')
                <div class="prose max-w-none text-brand-gray font-medium mb-10">{!! $intro !!}</div>
            @endif

            {{-- ===== Форма подбора (GET: работает без JS и индексируется) ===== --}}
            <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Подбор яхт</h2>

            <form method="GET" action="{{ route('services.fleet-rental') }}"
                  class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <label class="block">
                    <span class="block text-sm text-brand-gray-light mb-1">Дата начала</span>
                    <input type="date" name="date_start"
                           value="{{ $summary['from']?->toDateString() }}"
                           class="block appearance-none border border-[#C6C6C6] w-full text-sm md:text-base p-3">
                </label>

                <label class="block">
                    <span class="block text-sm text-brand-gray-light mb-1">Дата окончания</span>
                    <input type="date" name="date_end"
                           value="{{ $summary['to']?->toDateString() }}"
                           class="block appearance-none border border-[#C6C6C6] w-full text-sm md:text-base p-3">
                </label>

                <label class="block">
                    <span class="block text-sm text-brand-gray-light mb-1">Количество яхт</span>
                    <input type="number" name="count" min="1" max="50"
                           value="{{ $summary['needed'] }}"
                           class="block appearance-none border border-[#C6C6C6] w-full text-sm md:text-base p-3">
                </label>

                <div class="flex items-end">
                    <button type="submit"
                            class="bg-[#2D92CE] text-white w-full py-3 px-8 hover:bg-[#0074CC] transition-colors font-semibold">
                        Подобрать
                    </button>
                </div>
            </form>

            {{-- ===== Результат подбора ===== --}}
            @if ($summary['searched'])
                <div class="border border-[#C6C6C6] p-6 mb-8">
                    <p class="text-lg md:text-xl mb-2">
                        @if ($summary['enough'])
                            Свободно <span class="font-semibold text-[#2D92CE]">{{ $summary['available'] }}</span>
                            {{ \App\Support\Plural::form($summary['available'], 'яхта', 'яхты', 'яхт') }}
                            на {{ \App\Support\Plural::with($summary['days'], 'день', 'дня', 'дней') }} —
                            этого достаточно для запроса на {{ $summary['needed'] }}.
                        @else
                            На выбранные даты свободно
                            <span class="font-semibold text-[#2D92CE]">{{ $summary['available'] }}</span>
                            {{ \App\Support\Plural::form($summary['available'], 'яхта', 'яхты', 'яхт') }}
                            из {{ $summary['needed'] }} запрошенных.
                        @endif
                    </p>

                    @if ($summary['estimate'] !== null)
                        <p class="text-brand-gray-light">
                            Ориентировочная стоимость: от
                            <span class="font-semibold">{{ number_format($summary['estimate'], 0, '.', ' ') }} ₽</span>
                            ({{ number_format($summary['price_from'], 0, '.', ' ') }} ₽/день ×
                            {{ $summary['days'] }} × {{ $summary['needed'] }})
                        </p>
                    @else
                        <p class="text-brand-gray-light">Стоимость аренды — по запросу.</p>
                    @endif

                    @if (trim((string) $note) !== '')
                        <p class="text-sm text-brand-gray-light mt-3">{{ $note }}</p>
                    @endif

                    <div class="mt-6">
                        <x-service-request-button
                            :type="$type"
                            label="Оставить запрос на флот"
                            heading="Заявка на аренду флота"
                            :preset="[
                                'date_start' => $summary['from']?->toDateString(),
                                'date_end' => $summary['to']?->toDateString(),
                                'quantity' => $summary['needed'],
                            ]" />
                    </div>
                </div>
            @else
                <p class="text-brand-gray-light mb-8">
                    Выберите даты, чтобы увидеть, какие яхты свободны. Ниже — весь флот, доступный для аренды.
                </p>
            @endif

            {{-- ===== Карточки яхт ===== --}}
            @if ($summary['yachts']->isNotEmpty())
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach ($summary['yachts'] as $yacht)
                        @php $photo = $yacht->getMedia('gallery')->first(); @endphp
                        <div class="border border-[#C6C6C6]">
                            @if ($photo)
                                <x-responsive-picture :media="$photo" conversion="thumb"
                                                      :alt="$yacht->name"
                                                      img-class="w-full h-52 object-cover" />
                            @else
                                <div class="w-full h-52 bg-gray-100"></div>
                            @endif

                            <div class="p-4">
                                <h3 class="a-font text-xl mb-1 text-[#2E325C]">{{ $yacht->name }}</h3>
                                <p class="text-sm text-brand-gray-light">
                                    {{ $yacht->class ?? 'Carter 30' }}@if ($yacht->year), {{ $yacht->year }} г.@endif
                                </p>
                                @if ($yacht->home_region)
                                    <p class="text-sm text-brand-gray-light">{{ $yacht->home_region }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-center text-brand-gray-light py-8">
                    На выбранные даты свободных яхт не нашлось. Оставьте запрос — подберём вариант вручную.
                </p>

                <div class="text-center">
                    <x-service-request-button
                        :type="$type"
                        label="Оставить запрос на флот"
                        heading="Заявка на аренду флота"
                        :preset="[
                            'date_start' => $summary['from']?->toDateString(),
                            'date_end' => $summary['to']?->toDateString(),
                            'quantity' => $summary['needed'],
                        ]" />
                </div>
            @endif

            {{-- ===== Преимущества ===== --}}
            @if (count($advantages) > 0)
                <div class="mt-12">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        @foreach ($advantages as $advantage)
                            <div class="border-l-2 border-[#2D92CE] pl-4">
                                <h3 class="a-font text-xl mb-2 text-[#2E325C]">{{ $advantage['title'] }}</h3>
                                @if (! empty($advantage['text']))
                                    <p class="text-brand-gray-light text-sm">{{ $advantage['text'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ===== Другие услуги ===== --}}
            <div class="mt-12">
                <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Другие услуги</h2>
                <x-service-cards :current="$type" />
            </div>
        </div>
    </section>

    <x-feedback-section></x-feedback-section>
</x-public-layout>
