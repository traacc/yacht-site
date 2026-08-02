{{--
    Витрина бронирования яхт (ТЗ 3-го этапа, п. 7).

    Поиск и фильтры серверные: форма шлёт GET, параметры переживают пагинацию
    благодаря withQueryString() в App\Services\YachtBooking.

    Ожидает: $type, $intro, $heroImage, $search, $filters, $regions, $classes,
    $purposes, $steps, $note.
--}}
@php
    $yachts = $search['yachts'];
    $from = $search['from'];
    $to = $search['to'];
@endphp

<x-public-layout
    title="Аренда яхт — бронирование онлайн — Yacht Association"
    description="Аренда парусной яхты на день или несколько суток: поиск по свободным датам, стоимость периода и бронирование.">

    <x-breadcrumbs_page title="Аренда яхт"></x-breadcrumbs_page>

    <x-hero-section
        title="Аренда яхт"
        desc="Выберите даты — покажем свободные яхты и стоимость периода"
        bgImage="{{ $heroImage ?? asset('images/bg/yachts.webp') }}"></x-hero-section>

    <section class="py-10 px-4 sm:px-6 lg:px-8">
        <div class="container mx-auto">
            @if (trim(strip_tags($intro, '<img>')) !== '')
                <div class="prose max-w-none text-brand-gray font-medium mb-10">{!! $intro !!}</div>
            @endif

            {{-- ===== Поиск ===== --}}
            <form method="GET" action="{{ route('services.yacht-rental') }}" class="bg-[#F8F8F8] p-4 md:p-6 mb-8">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                    <label class="block">
                        <span class="block text-sm text-brand-gray-light mb-1">Заезд</span>
                        <input type="date" name="date_start" value="{{ $from?->toDateString() }}"
                               class="block appearance-none border border-[#C6C6C6] bg-white w-full text-sm md:text-base p-3">
                    </label>

                    <label class="block">
                        <span class="block text-sm text-brand-gray-light mb-1">Выезд</span>
                        <input type="date" name="date_end" value="{{ $to?->toDateString() }}"
                               class="block appearance-none border border-[#C6C6C6] bg-white w-full text-sm md:text-base p-3">
                    </label>

                    <label class="block">
                        <span class="block text-sm text-brand-gray-light mb-1">Регион</span>
                        <select name="region"
                                class="block appearance-none border border-[#C6C6C6] bg-white w-full text-sm md:text-base p-3">
                            <option value="">Любой регион</option>
                            @foreach ($regions as $region)
                                <option value="{{ $region }}" @selected(($filters['region'] ?? '') === $region)>{{ $region }}</option>
                            @endforeach
                        </select>
                    </label>

                    <div class="flex items-end">
                        <button type="submit"
                                class="bg-[#2D92CE] text-white w-full py-3 px-8 hover:bg-[#0074CC] transition-colors font-semibold">
                            Найти яхту
                        </button>
                    </div>
                </div>

                {{-- Дополнительные фильтры: раскрываются, чтобы не загромождать поиск. --}}
                <div x-data="{ open: {{ collect($filters)->only(['q', 'yacht_class', 'purpose', 'price_from', 'price_to'])->filter()->isNotEmpty() ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open"
                            class="text-[#2D92CE] font-semibold hover:underline text-sm">
                        <span x-show="!open">Больше фильтров ▾</span>
                        <span x-show="open" x-cloak>Свернуть фильтры ▴</span>
                    </button>

                    <div x-show="open" x-cloak class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
                        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
                               placeholder="Название или парусный номер"
                               class="border border-[#C6C6C6] bg-white p-3 text-sm md:text-base w-full">

                        @if ($classes !== [])
                            <select name="yacht_class" class="border border-[#C6C6C6] bg-white p-3 text-sm md:text-base w-full">
                                <option value="">Любой класс</option>
                                @foreach ($classes as $class)
                                    <option value="{{ $class }}" @selected(($filters['yacht_class'] ?? '') === $class)>{{ $class }}</option>
                                @endforeach
                            </select>
                        @endif

                        @if ($purposes !== [])
                            <select name="purpose" class="border border-[#C6C6C6] bg-white p-3 text-sm md:text-base w-full">
                                <option value="">Для чего угодно</option>
                                @foreach ($purposes as $purpose)
                                    <option value="{{ $purpose }}" @selected(($filters['purpose'] ?? '') === $purpose)>{{ $purpose }}</option>
                                @endforeach
                            </select>
                        @endif

                        <input type="number" name="price_from" min="0" value="{{ $filters['price_from'] ?? '' }}"
                               placeholder="Цена от, ₽/сутки"
                               class="border border-[#C6C6C6] bg-white p-3 text-sm md:text-base w-full">

                        <input type="number" name="price_to" min="0" value="{{ $filters['price_to'] ?? '' }}"
                               placeholder="Цена до, ₽/сутки"
                               class="border border-[#C6C6C6] bg-white p-3 text-sm md:text-base w-full">

                        <select name="sort" class="border border-[#C6C6C6] bg-white p-3 text-sm md:text-base w-full">
                            @foreach (\App\Services\YachtBooking::sortOptions() as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['sort'] ?? 'name') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>

                        <a href="{{ route('services.yacht-rental') }}"
                           class="border border-[#2D92CE] text-[#2D92CE] py-3 px-6 hover:bg-[#2D92CE] hover:text-white transition-colors font-semibold text-center">
                            Сбросить
                        </a>
                    </div>
                </div>
            </form>

            {{-- ===== Результат ===== --}}
            <div class="flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-2 mb-6">
                <h2 class="section-title a-font text-2xl md:text-3xl">
                    @if ($search['searched'])
                        Свободно {{ \App\Support\Plural::with($yachts->total(), 'яхта', 'яхты', 'яхт') }}
                    @else
                        Яхты в аренду: {{ $yachts->total() }}
                    @endif
                </h2>

                @if ($search['searched'])
                    <p class="text-brand-gray-light">
                        {{ $from->format('d.m.Y') }} — {{ $to->format('d.m.Y') }},
                        {{ \App\Support\Plural::with($search['days'], 'день', 'дня', 'дней') }}
                    </p>
                @else
                    <p class="text-brand-gray-light">Выберите даты, чтобы увидеть свободные яхты и стоимость периода.</p>
                @endif
            </div>

            @if ($yachts->isNotEmpty())
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach ($yachts as $yacht)
                        @include('partials.yacht-booking-card', [
                            'yacht' => $yacht,
                            'days' => $search['days'],
                            'from' => $from,
                            'to' => $to,
                        ])
                    @endforeach
                </div>

                @if ($yachts->hasPages())
                    <div class="mt-10">{{ $yachts->links() }}</div>
                @endif
            @else
                <div class="border border-[#C6C6C6] p-8 text-center">
                    <p class="text-brand-gray font-medium mb-4">
                        На выбранные даты свободных яхт не нашлось.
                    </p>
                    <p class="text-brand-gray-light mb-6">
                        Попробуйте изменить период или посмотрите аренду флота — там подбор идёт сразу на несколько яхт.
                    </p>
                    <a href="{{ route('services.fleet-rental') }}"
                       class="inline-block bg-[#2D92CE] text-white py-3 px-8 hover:bg-[#0074CC] transition-colors font-semibold">
                        Аренда флота →
                    </a>
                </div>
            @endif

            @if (trim((string) $note) !== '')
                <p class="text-sm text-brand-gray-light mt-6">{{ $note }}</p>
            @endif

            {{-- ===== Как забронировать ===== --}}
            @if (count($steps) > 0)
                <div class="mt-12">
                    <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Как забронировать</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        @foreach ($steps as $index => $step)
                            <div class="border-l-2 border-[#2D92CE] pl-4">
                                <div class="text-[#2D92CE] a-font text-3xl mb-1">{{ $index + 1 }}</div>
                                <h3 class="a-font text-xl mb-2 text-[#2E325C]">{{ $step['title'] }}</h3>
                                @if (! empty($step['text']))
                                    <p class="text-brand-gray-light text-sm">{{ $step['text'] }}</p>
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
