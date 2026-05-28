{{-- ===== HERO СЕКЦИЯ ===== --}}
{{-- Alpine.js countdown component --}}
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
{{-- ===== БЛОК БЛИЖАЙШЕЙ РЕГАТЫ С ТАЙМЕРОМ ===== --}}
<div class="bg-white border-b border-gray-100 py-10">
    <div class="container mx-auto">
        @if($regatta)
        <div class="flex flex-col md:flex-row gap-8 items-center bg-[#F8F8F8] pb-6 md:pb-0">
            <div class="">
                <img src="{{ asset('storage/' . $regatta->background_image) }}"
                     alt="{{ $regatta->name }}" class=" w-full h-full object-cover">
            </div>
            <div class="flex-1 h-auto flex flex-col justify-between  gap-4 md:gap-5 px-3 md:px-0">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="bg-[#F2484226] text-[#F24842] font-bold px-2.5 py-1 uppercase text-xs">Ближайшая регата</span>
                    </div>
                    <h2 class="font-display text-brand-navy md:text-5xl text-3xl mt-2 a-font">{!! nl2br(e($regatta->name)) !!}</h2>
                    <div class="flex flex-col gap-3 md:gap-4 text-sm md:text-lg text-brand-gray mt-2 font-medium">
                        <span class="flex items-center gap-1.5">
                            <img src="{{ asset('images/icons/waves.svg') }}" alt="">
                            {{ $regatta->water_area }}
                        </span>
                        <span class="flex items-center gap-1.5">
                            {!!  file_get_contents(public_path('images/icons/weather.svg')) !!}
                            {{ $currentWeather }}
                        </span>
                    </div>
                    <p class="text-sm md:text-lg text-brand-gray mt-2">{{ Str::limit($regatta->short_description, 100) }}</p>
                    <a href="{{ route('competition-details', ['regatta' => $regatta]) }}" class="flex items-center gap-2 mt-3 text-[#2E325C] text-sm md:text-xl font-semibold hover:underline">Подробнее о регате {!! file_get_contents(public_path('images/icons/l-arrow-right.svg')) !!}</a>
                </div>

            </div>

            {{-- Таймер обратного отсчёта --}}
            <div class="h-full p-6">
                <div x-data="countdown('{{ $regatta->date_start->format('Y-m-d\TH:i:s') }}')" class="shrink-0 text-center bg-white p-6 md:py-20 h-full">
                    <p class="text-2xl md:text-4xl font-display text-[#2E325C] font-semibold mb-1 a-font">{{ $regatta->dateRange() }}</p>
                    <p class="text-sm md:text-lg mb-4 text-[#2E325C]">До начала регаты осталось</p>
                    <div class="flex items-start gap-3 bg-[#F8F8F8] p-2">
                        <div class="text-center border-r border-[#EAEAEA] pr-3">
                            <div class="text-2xl md:text-5xl countdown-digit text-[#2E325C] a-font" x-text="String(days).padStart(2,'0')">00</div>
                            <div class="text-brand-gray-light mt-3 pl-2 text-[10px] md:text-base">Дней</div>
                        </div>
                        <div class="text-center border-r border-[#EAEAEA] pr-3">
                            <div class="text-2xl md:text-5xl countdown-digit text-[#2E325C] a-font" x-text="String(hours).padStart(2,'0')">00</div>
                            <div class="text-brand-gray-light mt-3 pl-2 text-[10px] md:text-base">Часов</div>
                        </div>
                        <div class="text-center border-r border-[#EAEAEA] pr-3">
                            <div class="text-2xl md:text-5xl countdown-digit text-[#2E325C] a-font" x-text="String(minutes).padStart(2,'0')">00</div>
                            <div class="text-brand-gray-light mt-3 pl-2 text-[10px] md:text-base">Минут</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl md:text-5xl countdown-digit text-[#2E325C] a-font" x-text="String(seconds).padStart(2,'0')">00</div>
                            <div class="text-brand-gray-light mt-3 pl-1 text-[10px] md:text-base">Секунд</div>
                        </div>
                    </div>
                    <a href="{{ route('competition-details', ['regatta' => $regatta]) }}" class="mt-5 bg-[#2D92CE] text-white md:text-lg text-sm font-semibold py-2.5 px-6 transition-colors flex gap-2 items-center justify-center">
                        Подать заявку {!! file_get_contents(public_path('images/icons/l-arrow-right.svg')) !!}
                    </a>
                </div>
            </div>

        </div>
        @else
        <div class="text-center py-16 text-brand-gray-light text-lg">
            В данный момент нет запланированных регат
        </div>
        @endif
    </div>
</div>