@if ($url)
    <a href="{{ $url }}" class="fi-wi-stats-overview-stat flex relative overflow-hidden gap-4 items-center bg-white p-6 cursor-pointer hover:bg-gray-50 transition-colors">
@else
    <div class="fi-wi-stats-overview-stat flex relative overflow-hidden gap-4 items-center bg-white p-6">
@endif
        <!-- Большая иконка во всю высоту слева -->
        <div class="overflow-hidden max-w-8 shrink-0 {{ $iconColor }}">
            @svg($icon, 'w-8')
        </div>

        <!-- Контентная часть (Текст и Число) -->
        <div class="flex flex-col gap-y-1 relative z-10">
            <span class="text-sm font-medium text-[#828282]">
                {{ $label }}
            </span>
            <span class="text-xl font-semibold tracking-tight text-gray-[#444444]">
                {{ $value }}
            </span>
        </div>
@if ($url)
    </a>
@else
    </div>
@endif