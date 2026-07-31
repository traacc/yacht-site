@php
    $photos = $tour->galleryPhotos();
    $videos = $tour->videos();
    $cover = $tour->getFirstMedia('cover');
    $vessel = $tour->vesselLabel();
    $seats = $tour->seatsLabel();
    $duration = $tour->durationLabel();
    $prices = array_filter([
        $tour->seatPriceLabel(),
        $tour->cabinPriceLabel(),
        $tour->orgFeeLabel(),
    ]);
@endphp

<x-public-layout
    :title="$tour->title . ' — яхтенный поход'"
    :description="Str::limit(strip_tags($tour->summary ?: $tour->content), 160)">

    <x-breadcrumbs_page :title="$tour->title"></x-breadcrumbs_page>

    <main>
        <section class="py-10 px-4 sm:px-6 lg:px-8">
            <div class="container mx-auto">
                <a href="{{ route('services.tours') }}" class="text-[#2D92CE] font-semibold hover:underline text-sm">← Все походы</a>

                <h1 class="a-font text-3xl md:text-5xl text-[#2E325C] mt-4 mb-3">{{ $tour->title }}</h1>

                <div class="text-brand-gray-light md:text-lg mb-6">
                    {{ $tour->dateRange() }}@if ($duration) · {{ $duration }}@endif
                    @if ($tour->region) · {{ $tour->region }}@endif
                </div>

                @if ($cover)
                    <div class="mb-8">
                        <x-responsive-picture :media="$cover" :alt="$tour->title"
                            img-class="w-full max-h-[520px] object-cover" />
                    </div>
                @endif

                @if ($tour->summary)
                    <p class="text-brand-gray font-medium md:text-lg mb-8">{{ $tour->summary }}</p>
                @endif

                {{-- ===== Маршрут, судно, стоимость ===== --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                    @if ($tour->route_summary)
                        <div class="border border-[#C6C6C6] p-6">
                            <h2 class="a-font text-xl mb-2 text-[#2E325C]">Маршрут</h2>
                            <p class="text-brand-gray-light">{{ $tour->route_summary }}</p>
                        </div>
                    @endif

                    @if ($vessel)
                        <div class="border border-[#C6C6C6] p-6">
                            <h2 class="a-font text-xl mb-2 text-[#2E325C]">На чём идём</h2>
                            <p class="text-brand-gray-light">
                                @if ($tour->yacht)
                                    {{-- Карточка яхты открывается модалкой в каталоге,
                                         прямой ссылки на конкретную яхту в проекте нет. --}}
                                    <a href="{{ route('yachts') }}" class="text-[#2D92CE] hover:underline">{{ $vessel }}</a>
                                    @if ($tour->yacht->class)
                                        <span class="block text-sm">{{ $tour->yacht->class }}@if ($tour->yacht->year), {{ $tour->yacht->year }} г.@endif</span>
                                    @endif
                                @else
                                    {{ $vessel }}
                                @endif
                            </p>
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
                            @if ($tour->price_note)
                                <p class="text-sm text-brand-gray-light mt-3">{{ $tour->price_note }}</p>
                            @endif
                        </div>
                    @endif
                </div>

                @if ($seats)
                    <div class="inline-block px-4 py-2 mb-8 {{ $tour->hasSeats() ? 'bg-[#DCEBE3] text-[#2E325C]' : 'bg-gray-200 text-brand-gray-light' }}">
                        {{ $seats }}
                    </div>
                @endif

                {{-- ===== Программа ===== --}}
                @if (trim(strip_tags($tour->content ?? '', '<img>')) !== '')
                    <div class="mb-10">
                        <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Программа</h2>
                        <div class="text prose max-w-none space-y-4 text-lg">{!! $tour->content !!}</div>
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
                    @if ($tour->acceptsServiceRequests())
                        <h2 class="section-title a-font text-[#2E325C] md:text-3xl text-2xl mb-4">Идём с нами?</h2>
                        <p class="text-brand-gray font-medium md:text-lg text-sm mb-6">
                            Оставьте заявку — расскажем о подготовке, снаряжении и оплате.
                        </p>
                        <x-service-request-button
                            :type="$type"
                            :subject="$tour"
                            lock-dates
                            :preset="[
                                'date_start' => $tour->date_start?->toDateString(),
                                'date_end' => ($tour->date_end ?? $tour->date_start)?->toDateString(),
                            ]"
                            label="Записаться в поход"
                            heading="Заявка на участие" />
                    @else
                        <h2 class="section-title a-font text-[#2E325C] md:text-3xl text-2xl mb-4">
                            {{ $tour->isPast() ? 'Этот поход уже прошёл' : 'Мест на этот поход не осталось' }}
                        </h2>
                        <p class="text-brand-gray font-medium md:text-lg text-sm mb-6">
                            Посмотрите ближайшие походы — расписание обновляется.
                        </p>
                        <a href="{{ route('services.tours') }}"
                           class="inline-block bg-[#2D92CE] text-white py-3 px-8 hover:bg-[#0074CC] transition-colors md:text-lg text-sm font-semibold">
                            Ближайшие походы →
                        </a>
                    @endif
                </div>

                {{-- ===== Другие походы ===== --}}
                @if ($others->isNotEmpty())
                    <div class="mt-12">
                        <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Другие походы</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                            @foreach ($others as $other)
                                <x-tour-card :tour="$other" />
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </section>
    </main>

    <x-feedback-section></x-feedback-section>
</x-public-layout>
