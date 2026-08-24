<x-public-layout
    :title="$type->label() . ' — Yacht Association'"
    :description="$type->seoDescription()">

    <x-breadcrumbs_page :title="$type->label()"></x-breadcrumbs_page>

    <x-hero-section
        :title="$type->label()"
        :desc="$type->tagline()"
        bgImage="{{ $heroImage ?? asset('images/bg/competitions.webp') }}"></x-hero-section>

    <section class="py-10 px-4 sm:px-6 lg:px-8">
        <div class="container mx-auto">
            @if (trim(strip_tags($intro, '<img>')) !== '')
                <div class="prose max-w-none text-brand-gray font-medium mb-10">{!! $intro !!}</div>
            @endif

            <div class="mb-12">
                <x-service-request-button
                    :type="$type"
                    label="Обсудить мероприятие"
                    heading="Заявка на мероприятие" />
            </div>

            {{-- ===== Форматы ===== --}}
            @if (count($formats) > 0)
                <div class="mb-12">
                    <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Форматы мероприятий</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach ($formats as $format)
                            <div class="border border-[#C6C6C6] p-6">
                                <h3 class="a-font text-xl mb-2 text-[#2E325C]">{{ $format['title'] }}</h3>
                                @if (! empty($format['desc']))
                                    <p class="text-brand-gray-light text-sm">{{ $format['desc'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ===== Флот ===== --}}
            @if ($fleet->isNotEmpty())
                <div class="mb-12">
                    <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Наш флот</h2>

                    @if (trim((string) $fleetNote) !== '')
                        <p class="text-brand-gray-light mb-6">{{ $fleetNote }}</p>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        @foreach ($fleet as $yacht)
                            @php $photo = $yacht->getMedia('gallery')->first(); @endphp
                            <div class="border border-[#C6C6C6]">
                                @if ($photo)
                                    <x-responsive-picture :media="$photo" conversion="thumb"
                                                          :alt="$yacht->name"
                                                          img-class="w-full h-40 object-cover" />
                                @else
                                    <div class="w-full h-40 bg-gray-100"></div>
                                @endif
                                <div class="p-4">
                                    <h3 class="a-font text-lg text-[#2E325C]">{{ $yacht->name }}</h3>
                                    <p class="text-sm text-brand-gray-light">{{ $yacht->class ?? 'Carter 30' }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <p class="mt-4">
                        <a href="{{ route('services.fleet-rental') }}" class="text-[#2D92CE] font-semibold">
                            Подобрать яхты на конкретные даты →
                        </a>
                    </p>
                </div>
            @endif

            {{-- ===== Площадки ===== --}}
            @if (count($venues) > 0)
                <div class="mb-12">
                    <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Площадки</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach ($venues as $venue)
                            <div class="border border-[#C6C6C6]">
                                @if (! empty($venue['photo']))
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($venue['photo']) }}"
                                         alt="{{ $venue['title'] }}" loading="lazy"
                                         class="w-full h-52 object-cover">
                                @endif
                                <div class="p-6">
                                    <h3 class="a-font text-xl mb-2 text-[#2E325C]">{{ $venue['title'] }}</h3>
                                    @if (! empty($venue['address']))
                                        <p class="text-sm text-brand-gray-light">{{ $venue['address'] }}</p>
                                    @endif
                                    @if (! empty($venue['capacity']))
                                        <p class="text-sm text-brand-gray-light">{{ $venue['capacity'] }}</p>
                                    @endif
                                    @if (! empty($venue['desc']))
                                        <p class="text-brand-gray-light text-sm mt-2">{{ $venue['desc'] }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ===== Галерея мероприятий ===== --}}
            @if (count($gallery) > 0)
                <div class="mb-12">
                    <x-photo-gallery :photos="$gallery" title="Галерея мероприятий" />
                </div>
            @endif

            {{-- ===== Проведённые мероприятия ===== --}}
            @if (count($cases) > 0)
                <div class="mb-12">
                    <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Проведённые мероприятия</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach ($cases as $case)
                            <div class="border-l-2 border-[#2D92CE] pl-4">
                                <h3 class="a-font text-xl mb-1 text-[#2E325C]">{{ $case['title'] }}</h3>
                                @if (! empty($case['date']))
                                    <p class="text-sm text-brand-gray-light mb-2">{{ $case['date'] }}</p>
                                @endif
                                @if (! empty($case['desc']))
                                    <p class="text-brand-gray-light text-sm">{{ $case['desc'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ===== Конструктор мероприятия (ТЗ п. 7) =====
                 Параметры ивента, автоподбор свободных яхт по каждой из
                 возможных дат и расчёт минимальной стоимости. Секция целиком
                 внутри компонента: он же прячет её, если конструктор выключен
                 в админке. --}}
            <livewire:event-constructor />

            {{-- ===== Другие услуги ===== --}}
            <div>
                <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Другие услуги</h2>
                <x-service-cards :current="$type" />
            </div>
        </div>
    </section>

    @if($documents !== [])
    <section class="py-10 px-4 sm:px-6 lg:px-8">
        <div class="container mx-auto">
            <h3 class="text-2xl font-semibold text-[#2E325C] mb-6">Документы</h3>
            <x-document-list :documents="$documents" />
        </div>
    </section>
    @endif

    <x-feedback-section></x-feedback-section>
</x-public-layout>
