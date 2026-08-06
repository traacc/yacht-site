@props([
    // App\Models\PressMention
    'mention',
])

{{--
    Карточка публикации в прессе.

    Один компонент на раздел «Пресса о нас», блок на главной и сайдбар страницы
    публикации. Обёртка — div, а не ссылка: в карточке две разные ссылки
    (наша страница с перепечаткой и оригинал в издании), вложить их в <a> нельзя.
--}}
@php
    $cover = $mention->getFirstMedia('cover');
    $host = $mention->sourceHost();
    $hasPage = $mention->hasContent();
@endphp

<article class="group flex flex-col bg-[#F8F8F8] overflow-hidden shadow-xs hover:shadow-md transition-shadow">
    <a href="{{ $hasPage ? $mention->publicUrl() : $mention->source_url }}"
       @unless ($hasPage) target="_blank" rel="noopener noreferrer" @endunless
       class="block overflow-hidden h-52 shrink-0">
        @if ($cover)
            <x-responsive-picture :media="$cover" :alt="$mention->title"
                img-class="w-full h-52 object-cover transition-transform duration-500 group-hover:scale-105" />
        @else
            <img class="w-full h-52 object-cover transition-transform duration-500 group-hover:scale-105"
                 src="{{ asset('images/gallery.png') }}" alt="{{ $mention->title }}">
        @endif
    </a>

    <div class="p-4 flex flex-col grow">
        <div class="text-brand-gray-light text-sm mb-2">
            {{ $mention->source_name }}@if ($mention->published_at) · {{ $mention->published_at->translatedFormat('j F Y') }}@endif
        </div>

        <h3 class="text-[#2E325C] text-lg font-semibold mb-2">
            <a href="{{ $hasPage ? $mention->publicUrl() : $mention->source_url }}"
               @unless ($hasPage) target="_blank" rel="noopener noreferrer" @endunless
               class="hover:underline">{{ $mention->title }}</a>
        </h3>

        @if ($mention->summary)
            <p class="text-brand-gray font-medium text-sm mb-3">{{ Str::limit($mention->summary, 140) }}</p>
        @endif

        <div class="mt-auto flex flex-wrap gap-4 items-center">
            @if ($hasPage)
                <a href="{{ $mention->publicUrl() }}" class="text-[#2E325C] font-semibold text-sm hover:underline">Читать статью →</a>
            @endif

            <a href="{{ $mention->source_url }}" target="_blank" rel="noopener noreferrer"
               class="text-[#2D92CE] font-semibold text-sm hover:underline inline-flex gap-1 items-center">
                <span>{{ $host ? 'Оригинал: '.$host : 'Оригинал' }}</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5h5v5m0-5L10 14M9 5H6a1 1 0 00-1 1v12a1 1 0 001 1h12a1 1 0 001-1v-3"/></svg>
            </a>
        </div>
    </div>
</article>
