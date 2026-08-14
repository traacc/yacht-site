@php
    $photos = $regatta->galleryPhotos();
    $videos = $regatta->videos();
    $cover = $regatta->getFirstMedia('cover');
    $place = $regatta->placeLabel();
    $duration = $regatta->durationLabel();
    $participation = $regatta->participationOptions();
    $prices = array_filter([
        $regatta->seatPriceLabel(),
        $regatta->cabinPriceLabel(),
    ]);
    $fleetGroups = $regatta->fleetGroups();
    $freeYachts = $regatta->yachtsForWholeCharter()->count();
    $freeSeats = $regatta->freeCrewSeats();
    // Одна форма заявки на весь флот: кнопки у лодок открывают её событием и
    // подставляют выбранную лодку (@see components/service-request-button).
    $fleetRequestEvent = $regatta->acceptsServiceRequests() ? 'fleet-request' : null;
@endphp

<x-public-layout
    :title="$regatta->title . ' — регата за рубежом'"
    :description="Str::limit(strip_tags($regatta->summary ?: $regatta->content), 160)">

    <x-breadcrumbs_page :title="$regatta->title"></x-breadcrumbs_page>

    <main>
        <section class="py-10 px-4 sm:px-6 lg:px-8">
            <div class="container mx-auto">
                <a href="{{ route('services.foreign-regattas') }}" class="text-[#2D92CE] font-semibold hover:underline text-sm">← Все регаты за рубежом</a>

                <h1 class="a-font text-3xl md:text-5xl text-[#2E325C] mt-4 mb-3">{{ $regatta->title }}</h1>

                <div class="text-brand-gray-light md:text-lg mb-6">
                    {{ $regatta->dateRange() }}@if ($duration) · {{ $duration }}@endif
                    @if ($place) · {{ $place }}@endif
                </div>

                @if ($cover)
                    <div class="mb-8">
                        <x-responsive-picture :media="$cover" :alt="$regatta->title"
                            img-class="w-full max-h-[520px] object-cover" />
                    </div>
                @endif

                @if ($regatta->summary)
                    <p class="text-brand-gray font-medium md:text-lg mb-8">{{ $regatta->summary }}</p>
                @endif

                {{-- ===== Маршрут, флот, стоимость ===== --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                    @if ($regatta->route_summary)
                        <div class="border border-[#C6C6C6] p-6">
                            <h2 class="a-font text-xl mb-2 text-[#2E325C]">Маршрут</h2>
                            <p class="text-brand-gray-light">{{ $regatta->route_summary }}</p>
                        </div>
                    @endif

                    @if ($regatta->fleet_note)
                        <div class="border border-[#C6C6C6] p-6">
                            <h2 class="a-font text-xl mb-2 text-[#2E325C]">Флот</h2>
                            <p class="text-brand-gray-light">{{ $regatta->fleet_note }}</p>
                        </div>
                    @endif

                    @if (count($prices) > 0)
                        <div class="border border-[#C6C6C6] p-6">
                            <h2 class="a-font text-xl mb-2 text-[#2E325C]">Стоимость</h2>
                            <ul class="text-brand-gray-light space-y-1">
                                @foreach ($prices as $price)
                                    <li>{{ $price }}</li>
                                @endforeach
                            </ul>
                            @if ($regatta->price_note)
                                <p class="text-sm text-brand-gray-light mt-3">{{ $regatta->price_note }}</p>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- ===== Варианты участия ===== --}}
                @if (count($participation) > 0)
                    <div class="mb-10">
                        <h2 class="section-title a-font text-2xl md:text-3xl mb-4">Варианты участия</h2>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($participation as $option)
                                <span class="inline-block px-4 py-2 bg-[#F8F8F8] text-[#2E325C] text-sm">{{ $option->label() }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- ===== Флот регаты =====
                     Лодки разложены по дивизионам. У лодки со шкипером
                     продаются места в экипаж, у лодки без шкипера — она сама. --}}
                @if ($regatta->showsCharterFleet())
                    <div class="mb-10">
                        <h2 class="section-title a-font text-2xl md:text-3xl mb-2">Яхты регаты</h2>

                        <p class="text-brand-gray-light mb-6">
                            @if ($freeYachts > 0)
                                Свободно {{ \App\Support\Plural::with($freeYachts, 'яхта', 'яхты', 'яхт') }} под чартер целиком.
                            @endif
                            @if ($freeSeats > 0)
                                В экипажи набирается {{ \App\Support\Plural::with($freeSeats, 'человек', 'человека', 'человек') }}.
                            @endif
                            @if ($freeYachts === 0 && $freeSeats === 0)
                                Весь флот разобран — напишите нам, если хотите в лист ожидания.
                            @endif
                        </p>

                        @foreach ($fleetGroups as $group)
                            <div class="mb-8">
                                @if ($group['division'])
                                    <h3 class="a-font text-xl md:text-2xl text-[#2E325C] mb-1">{{ $group['division']->title() }}</h3>
                                    @if ($group['division']->summaryLabel())
                                        <div class="text-brand-gray-light text-sm mb-4">{{ $group['division']->summaryLabel() }}</div>
                                    @else
                                        <div class="mb-4"></div>
                                    @endif
                                @endif

                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                    @foreach ($group['yachts'] as $yacht)
                                        <x-foreign-yacht-card :yacht="$yacht" :request-event="$fleetRequestEvent" />
                                    @endforeach
                                </div>
                            </div>
                        @endforeach

                        {{-- Одна форма на весь флот: своей кнопки не рисует,
                             открывается событием от карточки лодки. --}}
                        @if ($fleetRequestEvent)
                            <x-service-request-button
                                :type="$type"
                                :subject="$regatta"
                                lock-dates
                                open-event="fleet-request"
                                :preset="[
                                    'date_start' => $regatta->date_start?->toDateString(),
                                    'date_end' => ($regatta->date_end ?? $regatta->date_start)?->toDateString(),
                                ]"
                                heading="Заявка на участие" />
                        @endif
                    </div>
                @endif

                {{-- ===== Описание регаты ===== --}}
                @if (trim(strip_tags($regatta->content ?? '', '<img>')) !== '')
                    <div class="mb-10">
                        <h2 class="section-title a-font text-2xl md:text-3xl mb-6">О регате</h2>
                        <div class="text prose max-w-none space-y-4 text-lg">{!! $regatta->content !!}</div>
                    </div>
                @endif

                {{-- ===== Маршрут и расписание ===== --}}
                @if (trim(strip_tags($regatta->schedule ?? '', '<img>')) !== '')
                    <div class="mb-10">
                        <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Маршрут и расписание</h2>
                        <div class="text prose max-w-none space-y-4 text-lg">{!! $regatta->schedule !!}</div>
                    </div>
                @endif

                {{-- ===== Фотографии ===== --}}
                @if (count($photos) > 0)
                    <div class="mb-10">
                        <x-photo-gallery :photos="$photos" title="Фотографии" />
                    </div>
                @endif

                {{-- ===== Видео ===== --}}
                @if (count($videos) > 0)
                    <div class="mb-10">
                        <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Видео</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            @foreach ($videos as $video)
                                <figure>
                                    <div class="aspect-video bg-black">
                                        <iframe src="{{ $video['embed_url'] }}"
                                                class="w-full h-full"
                                                frameborder="0"
                                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                                allowfullscreen></iframe>
                                    </div>
                                    @if ($video['caption'] !== '')
                                        <figcaption class="text-sm text-brand-gray-light mt-2">{{ $video['caption'] }}</figcaption>
                                    @endif
                                </figure>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- ===== Заявка на участие ===== --}}
                <div class="mt-12 bg-[#F8F8F8] p-8 text-center">
                    @if ($regatta->acceptsServiceRequests())
                        <h2 class="section-title a-font text-[#2E325C] md:text-3xl text-2xl mb-4">Идём с нами?</h2>
                        <p class="text-brand-gray font-medium md:text-lg text-sm mb-6">
                            Оставьте заявку — расскажем про перелёт, чартер, взносы и подготовку экипажа.
                        </p>
                        <x-service-request-button
                            :type="$type"
                            :subject="$regatta"
                            lock-dates
                            :preset="[
                                'date_start' => $regatta->date_start?->toDateString(),
                                'date_end' => ($regatta->date_end ?? $regatta->date_start)?->toDateString(),
                            ]"
                            label="Отправить заявку на участие"
                            heading="Заявка на участие" />
                    @else
                        <h2 class="section-title a-font text-[#2E325C] md:text-3xl text-2xl mb-4">Эта регата уже прошла</h2>
                        <p class="text-brand-gray font-medium md:text-lg text-sm mb-6">
                            Посмотрите ближайшие регаты — календарь обновляется.
                        </p>
                        <a href="{{ route('services.foreign-regattas') }}"
                           class="inline-block bg-[#2D92CE] text-white py-3 px-8 hover:bg-[#0074CC] transition-colors md:text-lg text-sm font-semibold">
                            Ближайшие регаты →
                        </a>
                    @endif
                </div>

                {{-- ===== Другие регаты ===== --}}
                @if ($others->isNotEmpty())
                    <div class="mt-12">
                        <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Другие регаты за рубежом</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                            @foreach ($others as $other)
                                <x-foreign-regatta-card :regatta="$other" />
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </section>
    </main>

    <x-feedback-section></x-feedback-section>
</x-public-layout>
