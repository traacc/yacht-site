{{--
    Переключатель сезона для публичных страниц.

    Обычные ссылки с ?season=ГГГГ: выбранный сезон живёт в адресе, им можно
    поделиться, поисковик видит архив прошлых сезонов. При клике дописываем
    #якорь, чтобы не терять открытую вкладку страницы (например, #results).
--}}
@props([
    'years' => [],        // [2027 => '2027', 2026 => '2026'] — новые сверху
    'selected' => null,   // выбранный год
    'param' => 'season',
])

<div x-data="{ open: false }"
    @click.away="open = false"
    @keydown.escape.stop="open = false"
    {{ $attributes->merge(['class' => 'flex items-center gap-3']) }}>
    <span class="font-semibold text-[#2E325C] text-sm md:text-base">Сезон</span>
    <div class="relative w-28">
        <button type="button"
            @click="open = !open"
            aria-haspopup="listbox"
            :aria-expanded="open"
            class="relative w-full cursor-pointer bg-white py-2 pl-3 pr-8 text-left font-semibold text-[#2E325C] border border-[#2D92CE] text-sm md:text-base transition duration-150 ease-in-out">
            <span class="block truncate">{{ $selected ?? '—' }}</span>
            <span class="pointer-events-none absolute inset-y-0 pr-2 right-0 flex items-center">
                <svg class="h-3 w-3 text-transparent transition-transform duration-200 rotate-180" :class="{ 'rotate-360': open }" viewBox="0 0 14 8" fill="currentColor" aria-hidden="true">
                    <path d="M13 7L7 0.999999L1 7" stroke="#2D92CE" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
        </button>

        <ul x-show="open"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute right-0 z-10 mt-1 max-h-60 w-full overflow-auto bg-white py-1 shadow-lg border border-[#EAEAEA] md:text-base text-sm"
            role="listbox"
            style="display: none;">
            @foreach ($years as $year => $label)
                <li role="option" aria-selected="{{ (int) $year === (int) $selected ? 'true' : 'false' }}">
                    <a href="{{ request()->fullUrlWithQuery([$param => $year]) }}"
                        @click.prevent="window.location.href = $el.href + window.location.hash"
                        class="block py-2 px-3 hover:bg-[#2D92CE] hover:text-white transition-colors duration-100 {{ (int) $year === (int) $selected ? 'font-semibold text-[#2D92CE]' : 'text-gray-900' }}">
                        {{ $label }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</div>
