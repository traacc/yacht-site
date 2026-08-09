@php
    $sourceUrl = (string) $news->external_url;
    $sourceHost = parse_url($sourceUrl, PHP_URL_HOST);
    $sourceHost = is_string($sourceHost) ? preg_replace('/^www\./', '', $sourceHost) : null;
    $sourcePublishedAt = $news->source_published_at ?? $news->published_at;
    $sourceDate = $sourcePublishedAt
        ? \Illuminate\Support\Carbon::parse($sourcePublishedAt)->translatedFormat('j F Y')
        : null;
@endphp

<x-public-layout :title="$news->title . ' — новости парусного мира'" :description="Str::limit(strip_tags($news->content), 160)">
<x-breadcrumbs_page :title="$news->title">
</x-breadcrumbs_page>

<main class="main">
    <section class="py-12">
        <div class="container mx-auto flex flex-col md:flex-row gap-12 justify-between">
            <article class="content max-w-[902px] min-w-0">
                <a href="{{ route('world-news') }}" class="text-brand-gray-light hover:underline">← Новости парусного мира</a>

                <h1 class="section-title a-font text-3xl md:text-5xl mb-4 mt-4">{{ $news->title }}</h1>

                <p class="text-brand-gray-light mb-4">
                    Источник:
                    <a href="{{ $sourceUrl }}" target="_blank" rel="noopener noreferrer" class="text-[#2D92CE] hover:underline">
                        {{ $news->source_name }}
                    </a>
                    @if ($sourceDate) · {{ $sourceDate }}@endif
                </p>

                @if ($news->cover_image_url)
                    <div class="mb-6">
                        <img class="w-full" style="object-position: {{ $news->cover_object_position ?? 'center' }}"
                             src="{{ Storage::url($news->cover_image_url) }}" alt="{{ $news->title }}">
                    </div>
                @endif

                <x-rich-content :content="$news->content" />

                <div class="mt-8 bg-[#F8F8F8] p-4 flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
                    <div class="text-brand-gray font-medium">
                        Обзор подготовлен по материалам издания «{{ $news->source_name }}».
                    </div>
                    <a href="{{ $sourceUrl }}" target="_blank" rel="noopener noreferrer"
                       class="text-[#2D92CE] font-semibold inline-flex gap-1 items-center hover:underline shrink-0">
                        <span>{{ $sourceHost ? 'Читать оригинал: '.$sourceHost : 'Читать оригинал' }}</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5h5v5m0-5L10 14M9 5H6a1 1 0 00-1 1v12a1 1 0 001 1h12a1 1 0 001-1v-3"/></svg>
                    </a>
                </div>
            </article>

            <aside class="max-w-full md:max-w-[490px] flex-1">
                <h2 class="section-title a-font text-lg md:text-3xl mb-4 text-center">Другие новости мира</h2>

                @if ($otherNews->isNotEmpty())
                    <div class="flex flex-col gap-6">
                        @foreach ($otherNews as $other)
                            <x-world-news-card :news="$other" />
                        @endforeach
                    </div>
                    <a href="{{ route('world-news') }}" class="mx-auto mt-6 block text-lg font-semibold hover:underline text-center">Показать все →</a>
                @else
                    <p class="text-center text-brand-gray-light">Других публикаций пока нет.</p>
                @endif
            </aside>
        </div>
    </section>
</main>
</x-public-layout>
