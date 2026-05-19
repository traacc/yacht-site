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
    <div class="max-w-(--breakpoint-2xl) mx-auto px-4 sm:px-6 lg:px-8">
        @if($regatta)
        <div class="flex flex-col md:flex-row gap-8 items-center bg-[#F8F8F8]">
            <div class="md:max-w-96 shrink-0">
                <img src="{{ $regatta->background_image ? asset('storage/' . $regatta->background_image) : asset('images/regatas/reg_preview.png') }}"
                     alt="{{ $regatta->name }}" class=" w-full h-full object-cover">
            </div>
            <div class="flex-1 h-auto flex flex-col justify-between gap-5 px-3 md:px-0">
                <div class="flex items-center gap-2 mb-1">
                    <span class="bg-[#F2484226] text-[#F24842] font-bold px-2.5 py-1 uppercase text-xs">Ближайшая регата</span>
                </div>
                <h2 class="font-display text-brand-navy md:text-5xl text-3xl mt-2 a-font">{{ $regatta->name }}</h2>
                <div class="flex flex-col gap-3 md:gap-4 text-sm md:text-lg text-brand-gray mt-2 font-medium">
                    <span class="flex items-center gap-1.5">
                        <img src="{{ asset('images/icons/marker.svg') }}" alt="">
                        {{ $regatta->location }}
                    </span>
                    <span class="flex items-center gap-1.5">
                        <img src="{{ asset('images/icons/waves.svg') }}" alt="">
                        {{ $regatta->water_area }}
                    </span>
                </div>
                <p class="text-sm md:text-lg text-brand-gray mt-2">{{ $regatta->description }}</p>
                <a href="{{ route('competition-details', ['regatta' => $regatta->id]) }}" class="inline-block mt-3 text-[#2E325C] text-sm md:text-xl font-semibold hover:underline">Подробнее о регате →</a>
            </div>

            {{-- Таймер обратного отсчёта --}}
            <div x-data="countdown('{{ $regatta->date_start->format('Y-m-d\TH:i:s') }}')" class="shrink-0 text-center bg-white px-6 py-16  mr-4">
                <p class="text-4xl font-display text-[#2E325C] font-semibold mb-1 a-font">{{ $regatta->dateRange() }}</p>
                <p class="text-lg mb-4 text-[#2E325C]">До начала регаты осталось</p>
                <div class="flex items-start gap-3 bg-[#F8F8F8] p-2">
                    <div class="text-center border-r border-[#EAEAEA] pr-3">
                        <div class="text-5xl countdown-digit text-[#2E325C] a-font" x-text="String(days).padStart(2,'0')">00</div>
                        <div class="text-brand-gray-light mt-3 pl-2">Дней</div>
                    </div>
                    <div class="text-center border-r border-[#EAEAEA] pr-3">
                        <div class="text-5xl countdown-digit text-[#2E325C] a-font" x-text="String(hours).padStart(2,'0')">00</div>
                        <div class="text-brand-gray-light mt-3 pl-2">Часов</div>
                    </div>
                    <div class="text-center border-r border-[#EAEAEA] pr-3">
                        <div class="text-5xl countdown-digit text-[#2E325C] a-font" x-text="String(minutes).padStart(2,'0')">00</div>
                        <div class="text-brand-gray-light mt-3 pl-2">Минут</div>
                    </div>
                    <div class="text-center">
                        <div class="text-5xl countdown-digit text-[#2E325C] a-font" x-text="String(seconds).padStart(2,'0')">00</div>
                        <div class="text-brand-gray-light mt-3 pl-1">Секунд</div>
                    </div>
                </div>
                <a href="{{ route('competition-details', ['regatta' => $regatta->id]) }}" class="mt-5 block bg-[#2D92CE] text-white text-lg font-semibold py-2.5 px-6 transition-colors">
                    Подать заявку
                </a>
            </div>
        </div>
        @else
        <div class="text-center py-16 text-brand-gray-light text-lg">
            В данный момент нет запланированных регат
        </div>
        @endif
    </div>
</div>