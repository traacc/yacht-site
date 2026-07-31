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
    $fleet = $regatta->charterYachts;
    $freeYachts = $regatta->availableCharterYachts()->count();
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

                {{-- ===== Яхты под аренду =====
                     По ТЗ список раскрывается по клику: заказчику он нужен только
                     при варианте участия «яхта целиком». --}}
                @if ($regatta->showsCharterFleet())
                    <div class="mb-10" x-data="{ open: false }">
                        <button type="button" @click="open = !open"
                                class="flex items-center justify-between w-full border border-[#C6C6C6] p-6 text-left hover:border-[#2D92CE] transition-colors">
                            <span>
                                <span class="a-font text-xl md:text-2xl text-[#2E325C] block">Яхты под аренду</span>
                                <span class="text-brand-gray-light text-sm">
                                    {{ \App\Support\Plural::with($fleet->count(), 'яхта', 'яхты', 'яхт') }} в чартере@if ($freeYachts > 0), свободных — {{ $freeYachts }}@endif
                                </span>
                            </span>
                            <svg :class="open ? 'rotate-180' : ''" class="w-6 h-6 shrink-0 text-[#2D92CE] transition-transform"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <div x-show="open" x-cloak x-transition class="border border-t-0 border-[#C6C6C6] overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-[#F8F8F8] text-[#2E325C] text-left">
                                        <th class="p-3 font-semibold">Модель</th>
                                        <th class="p-3 font-semibold">Название</th>
                                        <th class="p-3 font-semibold">Год</th>
                                        <th class="p-3 font-semibold">Аренда</th>
                                        <th class="p-3 font-semibold">Занятость</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($fleet as $yacht)
                                        <tr class="border-t border-[#EAEAEA]">
                                            <td class="p-3 text-[#2E325C] font-medium">{{ $yacht->model }}</td>
                                            <td class="p-3 text-brand-gray-light">{{ $yacht->name ?: '—' }}</td>
                                            <td class="p-3 text-brand-gray-light">{{ $yacht->year ?: '—' }}</td>
                                            <td class="p-3 text-brand-gray-light">
                                                {{ $yacht->priceLabel() ?? 'по запросу' }}
                                                @if ($yacht->price_note)
                                                    <span class="block text-xs">{{ $yacht->price_note }}</span>
                                                @endif
                                            </td>
                                            <td class="p-3">
                                                <span class="inline-block text-xs px-2 py-1
                                                    {{ $yacht->isAvailable() ? 'bg-[#DCEBE3] text-[#2E325C]' : 'bg-gray-200 text-brand-gray-light' }}">
                                                    {{ $yacht->status->label() }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
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
