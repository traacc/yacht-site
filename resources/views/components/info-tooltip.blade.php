{{--
    Всплывающая подсказка «?» — единый механизм для публичного сайта и Livewire
    (см. stage_3.md, п. 1.2). Открывается по наведению (десктоп) и по клику (мобильные).

    Использование: <x-info-tooltip text="Пояснение." /> либо со слотом для разметки:
    <x-info-tooltip>Пояснение с <strong>акцентом</strong>.</x-info-tooltip>

    position="top" — открывать подсказку вверх вместо вниз (по умолчанию вниз,
    чтобы не обрезалась в таблицах с overflow-x-auto — они клипают и по вертикали).
--}}
@props([
    'text' => null,
    'position' => 'bottom',
])

<span
    x-data="{ open: false }"
    @click.outside="open = false"
    @keydown.escape.window="open = false"
    {{ $attributes->class('relative inline-flex align-middle') }}
>
    <button
        type="button"
        @click="open = true"
        @mouseenter="open = true"
        @mouseleave="open = false"
        class="inline-flex items-center justify-center w-4 h-4 rounded-full border border-brand-gray-light text-brand-gray-light text-[10px] leading-none font-bold hover:border-brand-blue hover:text-brand-blue focus:outline-none focus:border-brand-blue focus:text-brand-blue cursor-help"
        aria-label="Подсказка"
    >?</button>

    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute z-40 left-1/2 -translate-x-1/2 w-60 max-w-[75vw] rounded-lg bg-brand-dark px-3 py-2 text-left text-xs font-normal normal-case leading-snug text-white shadow-lg {{ $position === 'top' ? 'bottom-full mb-2' : 'top-full mt-2' }}"
    >{{ $text ?? $slot }}</div>
</span>
