<div class="fi-wi-stats-overview-stat flex  relative overflow-hidden gap-4 items-center bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <!-- Большая иконка во всю высоту слева -->
    <div class="overflow-hidden max-w-8 shrink-0 {{ $iconColor }}">
        @svg($icon, 'w-10')
    </div>

    <!-- Контентная часть (Текст и Число) -->
    <div class="flex flex-col gap-y-1 pr-20 relative z-10">
        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
            {{ $label }}
        </span>
        <span class="text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">
            {{ $value }}
        </span>
    </div>


</div>