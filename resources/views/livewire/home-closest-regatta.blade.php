<section x-data="{ windyModalOpen: false }" class="relative h-[480px] md:h-[708px] overflow-hidden">
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('countdown', (targetDate) => ({
            days: 0, hours: 0, minutes: 0, seconds: 0,
            init() {
                this.update();
                setInterval(() => this.update(), 1000);
            },
            update() {
                const now = new Date().getTime();
                const target = new Date(targetDate).getTime();
                const diff = Math.max(0, target - now);
                this.days = Math.floor(diff / (1000 * 60 * 60 * 24));
                this.hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                this.minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                this.seconds = Math.floor((diff % (1000 * 60)) / 1000);
            }
        }));
    });
</script>   
@if(($heroMedia ?? null) && $heroMedia['type'] === 'video')
    <video autoplay muted playsinline loop src="{{ $heroMedia['url'] }}" class="scale-[2.5] object-center absolute inset-0 w-full h-full object-cover"></video>
@elseif(($heroMedia ?? null) && $heroMedia['type'] === 'image')
    <img class="absolute inset-0 object-center w-full h-full object-cover" src="{{ $heroMedia['url'] }}" alt="">
@else
    <video autoplay muted playsinline loop src="{{ '/videos/hero_video_3.mp4' }}"  class="scale-[2.5] object-center absolute inset-0 w-full h-full object-cover"></video>
@endif
    <!--<img class="absolute inset-0 object-[29%_50%] lg:object-[50%_50%] lg:top-0 w-full h-full object-cover" src="{{ asset('/images/bg/bg_hero.webp') }}" alt="">-->

    <div class="hero-overlay absolute inset-0"></div>

    @if($regatta)
    <div class="container mx-auto relative mt-4">
        {{-- Карточка ближайшей регаты --}}
        <div class="mx-4 md:mx-0 pt-12 flex justify-between">
            <div class="relative bg-[#00000080] backdrop-blur-xs p-3 md:p-4 w-full md:w-auto md:max-w-2xl shadow-2xl">
                <a href="{{ route('competition-details', $regatta) }}"
                   class="absolute inset-0 z-0"
                   aria-label="Перейти на страницу регаты {{ $regatta->name }}"></a>
                <div class="relative z-10 flex items-center gap-2 mb-1 md:mb-3 pointer-events-none">
                    <span class="rounded-full bg-[#F24842] text-center text-white flex justify-center items-center shrink-0 aspect-square size-6 p-1 md:p-0 md:size-11">
                        {!!  file_get_contents(public_path('images/icons/calendar.svg')) !!}
                    </span>
                    <span class="text-white text-lg md:text-2xl font-bold pb-0.5 md:pb-2 pt-1">
                        Ближайшая регата
                    </span>
                </div>
                <div class="relative z-10 grid justify-between items-center gap-2 md:gap-4 grid-cols-1 md:grid-cols-[380px_300px] pointer-events-none">
                    <h3 class="font-display text-white text-2xl/7 mb-2 md:mb-0 md:text-4xl font-bold a-font">{!! nl2br(e($regatta->name)) !!}</h3>
                    <div class="flex flex-col gap-2 md:gap-0 mb-4 justify-between text-white font-medium text-sm md:text-lg md:row-span-2 h-full">
                        <div class="flex items-center gap-2">
                            {!!  file_get_contents(public_path('images/icons/calendar.svg')) !!}
                            {{ $regatta->dateRange() }}
                        </div>
                        <div class="flex items-center gap-2">
                            {!!  file_get_contents(public_path('images/icons/waves.svg')) !!}
                            @if($mapUrl)
                                <a href="{{ $mapUrl }}" target="_blank" rel="noopener noreferrer"
                                class="pointer-events-auto inline-flex items-center gap-1  transition-colors  underline underline-offset-2">
                                    {{ $regatta->water_area }}
                                </a>
                            @else
                            {{ $regatta->water_area }}
                            @endif
                        </div>
                        @if($lat && $lon)
                            @if($hasWeather)
                            <div class="">
                                <div class="flex pointer-events-auto items-center gap-2">
                                    <div class="lg:text-sm text-xs">Погода сейчас</div>
                                    <div @click="windyModalOpen = true" class="lg:text-sm text-xs underline cursor-pointer">Прогноз</div>
                                </div>
                                
                                <div class="pointer-events-auto flex items-center gap-2 cursor-pointer"
                                    @click="windyModalOpen = true"
                                    title="Смотреть прогноз погоды на Windy">
                                    {!!  file_get_contents(public_path('images/icons/weather.svg')) !!}
                                    {{ $currentWeather }}
                                    @if($wind)
                                        <span class="opacity-80">· {{ $wind }}</span>
                                    @endif
                                </div>
                            </div>
                            @else
                            <button type="button"
                                @click="windyModalOpen = true"
                                title="Смотреть прогноз погоды на Windy"
                                class="pointer-events-auto inline-flex items-center gap-2 cursor-pointer underline underline-offset-2 transition-colors">
                                {!!  file_get_contents(public_path('images/icons/weather.svg')) !!}
                                Погода
                            </button>
                            @endif
                        @else
                        <div class="">
                            <div class="lg:text-sm text-xs">Погода сейчас</div>
                            <div class="flex items-center gap-2">
                                {!!  file_get_contents(public_path('images/icons/weather.svg')) !!}
                                {{ $hasWeather ? $currentWeather : '—' }}
                                @if($hasWeather && $wind)
                                    <span class="opacity-80">· {{ $wind }}</span>
                                @endif
                            </div>
                        </div>
                        @endif


                    </div>
                    @if($hasDocuments)
                    <a href="{{ route('regatta.documents.download', $regatta) }}"
                        class="pointer-events-auto text-white text-l font-semibold hover:underline items-center gap-4 flex mt-6">
                        <x-icon-2 name="download" /> Скачать документы регаты
                    </a>
                    @endif
                </div>
            </div>
            <div class="hidden md:block">
                <div x-data="countdown('{{ $startDateTime }}')" class="shrink-0 flex flex-col justify-center text-center bg-[#00000080] p-6 h-full">
                    <p class="text-sm md:text-lg mb-4 text-white">До начала регаты осталось</p>
                    <div class="flex items-start gap-3 bg-[#F8F8F80D] p-2">
                        <div class="text-center border-r border-[#EAEAEA80] pr-3">
                            <div class="text-2xl md:text-5xl countdown-digit text-white a-font" x-text="String(days).padStart(2,'0')">00</div>
                            <div class="text-white mt-3 pl-2 text-[10px] md:text-base">Дней</div>
                        </div>
                        <div class="text-center border-r border-[#EAEAEA80] pr-3">
                            <div class="text-2xl md:text-5xl countdown-digit text-white a-font" x-text="String(hours).padStart(2,'0')">00</div>
                            <div class="text-white mt-3 pl-2 text-[10px] md:text-base">Часов</div>
                        </div>
                        <div class="text-center border-r border-[#EAEAEA80] pr-3">
                            <div class="text-2xl md:text-5xl countdown-digit text-white a-font" x-text="String(minutes).padStart(2,'0')">00</div>
                            <div class="text-white mt-3 pl-2 text-[10px] md:text-base">Минут</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl md:text-5xl countdown-digit text-white a-font" x-text="String(seconds).padStart(2,'0')">00</div>
                            <div class="text-white mt-3 pl-1 text-[10px] md:text-base">Секунд</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
    <button @click="$dispatch('open-join-regatta-modal', { regattaId: '{{ $regatta->id }}' })"
            class="pointer-events-auto relative z-20 max-w-3xs mx-auto mt-16 block w-full text-center bg-brand-blue text-2xl md:text-4xl text-white font-semibold py-2.5 transition-colors cursor-pointer">
        Заявка →
    </button>

    {{-- Модальное окно Windy --}}
    <div x-show="windyModalOpen"
         x-cloak
         @keydown.escape.window="windyModalOpen = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="windyModalOpen"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="windyModalOpen = false"
             class="fixed inset-0 bg-black/60 transition-opacity">
        </div>
        <div x-show="windyModalOpen"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             @click.away="windyModalOpen = false"
             class="relative w-full max-w-5xl bg-white shadow-xl z-10 overflow-hidden">
            <div class="flex items-center justify-between p-3 border-b">
                <span class="text-sm font-semibold text-[#2E325C]">Прогноз погоды — Windy.com</span>
                <button @click="windyModalOpen = false"
                        class="text-gray-400 hover:text-gray-600 text-2xl leading-none font-bold">&times;</button>
            </div>
            @if($lat && $lon)
            <div class="h-[186px] w-full">
                <template x-if="windyModalOpen">
                    <iframe
                        width="100%"
                        height="100%"
                        src="https://embed.windy.com/embed.html?type=forecast&location=coordinates&detail=true&detailLat={{ $lat }}&detailLon={{ $lon }}&metricTemp=°C&metricRain=mm&metricWind=m/s"
                        frameborder="0">
                    </iframe>
                </template>
            </div>
            @endif
        </div>
    </div>
</section>