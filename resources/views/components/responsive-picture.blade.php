@props([
    'media',           // Spatie\MediaLibrary\MediaCollections\Models\Media
    'conversion' => null, // имя фолбэк-конверсии для <img> (по умолчанию оригинал)
    'alt' => '',
    'imgClass' => '',
])

@php
    $sources = \App\Support\ResponsiveMedia::urls($media, $conversion);
@endphp

<picture>
    @isset($sources['avif'])
        <source srcset="{{ $sources['avif'] }}" type="image/avif">
    @endisset
    @isset($sources['webp'])
        <source srcset="{{ $sources['webp'] }}" type="image/webp">
    @endisset
    <img src="{{ $sources['src'] }}" alt="{{ $alt }}" class="{{ $imgClass }}" {{ $attributes }}>
</picture>
