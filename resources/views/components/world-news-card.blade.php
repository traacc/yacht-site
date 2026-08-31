@props([
    // App\Models\News
    'news',
])

@php
    $sourceUrl = (string) $news->external_url;
    $sourceHost = parse_url($sourceUrl, PHP_URL_HOST);
    $sourceHost = is_string($sourceHost) ? preg_replace('/^www\./', '', $sourceHost) : null;
    $sourcePublishedAt = $news->source_published_at ?? $news->published_at;
    $sourceDate = $sourcePublishedAt
        ? \Illuminate\Support\Carbon::parse($sourcePublishedAt)->translatedFormat('j F Y')
        : null;
@endphp

<article class="group flex flex-col bg-[#F8F8F8] overflow-hidden shadow-xs hover:shadow-md transition-shadow">
    <a href="{{ route('world-news-details', $news) }}" class="block overflow-hidden h-52 shrink-0">
        <img
            class="w-full h-52 object-cover transition-transform duration-500 group-hover:scale-105"
            style="object-position: {{ $news->cover_object_position ?? 'center' }}"
            src="{{ $news->cover_image_url ? Storage::url($news->cover_image_url) : asset('images/gallery.webp') }}"
            alt="{{ $news->title }}"
        >
    </a>

    <div class="p-4 flex flex-col grow">
        <div class="text-brand-gray-light text-sm mb-2">
            Источник: {{ $news->source_name }}@if ($sourceDate) · {{ $sourceDate }}@endif
        </div>

        <h3 class="text-[#2E325C] text-lg font-semibold mb-2">
            <a href="{{ route('world-news-details', $news) }}" class="hover:underline">{{ $news->title }}</a>
        </h3>

        <p class="text-brand-gray font-medium text-sm mb-4">
            {{ Str::limit(strip_tags($news->content), 140) }}
        </p>

        <div class="mt-auto flex flex-wrap gap-4 items-center">
            <a href="{{ route('world-news-details', $news) }}"
               class="text-[#2E325C] font-semibold text-sm hover:underline">Читать обзор →</a>

            <a href="{{ $sourceUrl }}" target="_blank" rel="noopener noreferrer"
               class="text-[#2D92CE] font-semibold text-sm hover:underline inline-flex gap-1 items-center">
                <span>{{ $sourceHost ? 'Оригинал: '.$sourceHost : 'Оригинал публикации' }}</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5h5v5m0-5L10 14M9 5H6a1 1 0 00-1 1v12a1 1 0 001 1h12a1 1 0 001-1v-3"/></svg>
            </a>
        </div>
    </div>
</article>
