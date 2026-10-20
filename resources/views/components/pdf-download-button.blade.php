{{-- Кнопка скачивания PDF — в том же стиле, что «Скачать календарь». --}}
@props(['href', 'title' => null])

<a
    href="{{ $href }}"
    target="_blank"
    @if ($title) title="{{ $title }}" @endif
    {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 px-4 py-2 bg-[#2D92CE] text-white text-sm font-semibold transition-colors duration-200 shrink-0']) }}
>
    <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 3a1 1 0 0 1 1 1v9.586l2.293-2.293a1 1 0 0 1 1.414 1.414l-4 4a1 1 0 0 1-1.414 0l-4-4a1 1 0 1 1 1.414-1.414L11 13.586V4a1 1 0 0 1 1-1Z" fill="currentColor"/>
        <path d="M6 17a1 1 0 1 0-2 0v.6C4 19.482 5.518 21 7.4 21h9.2c1.882 0 3.4-1.518 3.4-3.4V17a1 1 0 1 0-2 0v.6c0 .778-.622 1.4-1.4 1.4H7.4c-.778 0-1.4-.622-1.4-1.4V17Z" fill="currentColor"/>
    </svg>
    {{ $slot }}
</a>
