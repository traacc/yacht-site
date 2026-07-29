@props(['url'])
{{--
    Шапка письма: логотип сайта со ссылкой на главную.

    Логотип растровый и подключается абсолютной ссылкой: SVG почтовые клиенты
    (Gmail, Outlook) не отображают, а относительные пути в письме не работают.
    Размеры заданы атрибутами и inline-стилями — Outlook игнорирует CSS-классы.
    Файл отрисован в 2× ширины, поэтому на retina логотип остаётся резким.
--}}
@php
    $brandName = config('mail.brand.name');
    $logoWidth = config('mail.brand.logo_width');
@endphp
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset(config('mail.brand.logo')) }}"
     alt="{{ $brandName }}"
     width="{{ $logoWidth }}"
     style="width: {{ $logoWidth }}px; max-width: 100%; height: auto; border: 0; display: block;">
</a>
</td>
</tr>
