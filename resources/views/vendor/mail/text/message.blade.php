@php
    // Текстовая версия письма: логотипа нет, но бренд и адрес сайта должны
    // совпадать с HTML-вариантом — источник тот же, config('mail.brand').
    $brandName = config('mail.brand.name');
    $siteUrl = rtrim(config('app.url'), '/');
@endphp
<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="$siteUrl">
            {{ $brandName }}
        </x-mail::header>
    </x-slot:header>

    {{-- Body --}}
    {{ $slot }}

    {{-- Subcopy --}}
    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    {{-- Footer --}}
    <x-slot:footer>
        <x-mail::footer>
            © {{ date('Y') }} {{ $brandName }}. Все права защищены.
            {{ $siteUrl }}
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
