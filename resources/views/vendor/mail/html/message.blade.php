@php
    // Бренд берём из config('mail.brand'), а не из APP_NAME: при незаполненной
    // переменной окружения письма уходили бы с логотипом и копирайтом Laravel.
    $brandName = config('mail.brand.name');
    $siteUrl = rtrim(config('app.url'), '/');
    $siteHost = preg_replace('#^www\.#', '', (string) parse_url($siteUrl, PHP_URL_HOST));
@endphp
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="$siteUrl">
{{ $brandName }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $brandName }}. Все права защищены.
[{{ $siteHost }}]({{ $siteUrl }})
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
