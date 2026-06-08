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
<div class="bg-white border-b border-gray-100 py-10 md:hidden">
    <div class="container mx-auto">
        @if($regatta)
        <div class="flex flex-col md:flex-row gap-4 items-center bg-[#F8F8F8] md:pb-0">
            {{-- Таймер обратного отсчёта --}}
            <div class="h-full p-2">
                <div x-data="countdown('{{ $startDateTime }}')" class="shrink-0 text-center  p-3 md:py-20 h-full">
                    <p class="text-sm md:text-lg mb-4 font-medium text-[#2E325C]">До начала регаты осталось</p>
                    <div class="flex items-start bg-white p-2 ">
                        <div class="text-center border-r border-[#EAEAEA] px-5">
                            <div class="text-2xl md:text-5xl countdown-digit text-[#2E325C] a-font" x-text="String(days).padStart(2,'0')">00</div>
                            <div class="text-brand-gray-light mt-3  text-[10px] md:text-base">Дней</div>
                        </div>
                        <div class="text-center border-r border-[#EAEAEA] px-5">
                            <div class="text-2xl md:text-5xl countdown-digit text-[#2E325C] a-font" x-text="String(hours).padStart(2,'0')">00</div>
                            <div class="text-brand-gray-light mt-3  text-[10px] md:text-base">Часов</div>
                        </div>
                        <div class="text-center border-r border-[#EAEAEA] px-5">
                            <div class="text-2xl md:text-5xl countdown-digit text-[#2E325C] a-font" x-text="String(minutes).padStart(2,'0')">00</div>
                            <div class="text-brand-gray-light mt-3  text-[10px] md:text-base">Минут</div>
                        </div>
                        <div class="text-center px-5">
                            <div class="text-2xl md:text-5xl countdown-digit text-[#2E325C] a-font" x-text="String(seconds).padStart(2,'0')">00</div>
                            <div class="text-brand-gray-light mt-3 pl-1 text-[10px] md:text-base">Секунд</div>
                        </div>
                    </div>
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