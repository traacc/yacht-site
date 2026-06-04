<section class="relative h-[480px] md:h-[788px]">
    <!--<video autoplay muted playsinline loop src="{{ '/videos/hero_video_2.mp4' }}"  class="absolute inset-0 w-full h-full object-cover"></video>-->
    <img class="absolute inset-0 object-[29%_50%] lg:object-[50%_50%] lg:top-0 w-full h-full object-cover" src="{{ asset('/images/bg/bg_hero.webp') }}" alt="">

    <div class="hero-overlay absolute inset-0"></div>

    @if($regatta)
    <div class="container mx-auto relative mt-4">
        {{-- Карточка ближайшей регаты --}}
        <div class="md:absolute md:top-6 md:left-8 mx-4 md:mx-0 mt-6 bg-[#00000080] backdrop-blur-xs p-3 md:p-4  md:w-auto md:max-w-2xl shadow-2xl">
            <div class="flex items-center gap-2 mb-1 md:mb-3">
                <span class="rounded-full bg-[#F24842] text-center text-white flex justify-center items-center shrink-0 aspect-square size-6 p-1 md:p-0 md:size-11">
                    {!!  file_get_contents(public_path('images/icons/calendar.svg')) !!}
                </span>
                <span class="text-white text-lg md:text-2xl font-bold pb-0.5 md:pb-2 pt-1">
                    Ближайшая регата
                </span>
            </div>
            <div class="grid justify-between items-center gap-2 md:gap-4 grid-cols-1 md:grid-cols-[380px_300px]">
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
                               class="inline-flex items-center gap-1  transition-colors  underline underline-offset-2">
                                {{ $regatta->water_area }}
                            </a>
                        @else
                        {{ $regatta->water_area }}
                        @endif
                    </div>
                    <div class="">
                        <div class="lg:text-sm text-xs">Погода сейчас</div>
                        <div class="flex items-center gap-2">
                            
                            {!!  file_get_contents(public_path('images/icons/weather.svg')) !!}
                            {{ $currentWeather }}
                        </div>
                    </div>


                </div>
                @if($hasDocuments)
                <a href="{{ route('regatta.documents.download', $regatta) }}"
                    class="text-white text-l font-semibold hover:underline items-center gap-4 flex mt-6">
                    <x-icon-2 name="download" /> Скачать документы регаты
                </a>
                @endif
                <button @click="$dispatch('open-join-regatta-modal', { regattaId: '{{ $regatta->id }}' })"
                        class="block w-full text-center bg-white font-semibold py-2.5 transition-colors cursor-pointer">
                    Заявка →
                </button>
            </div>


        </div>
    </div>
    @endif
</section>