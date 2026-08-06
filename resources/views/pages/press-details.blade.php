@php
    $cover = $pressMention->getFirstMedia('cover');
    $host = $pressMention->sourceHost();
@endphp
<x-public-layout :title="$pressMention->title . ' — пресса о нас'" :description="Str::limit(strip_tags($pressMention->summary ?: $pressMention->content), 160)">
<x-breadcrumbs_page :title="$pressMention->title">
</x-breadcrumbs_page>

<main class="main">
    <section class="py-12">
        <div class="container mx-auto flex flex-col md:flex-row gap-12 justify-between">
            <div class="content max-w-[902px]">
                <a href="{{ route('press') }}" class="text-brand-gray-light hover:underline">← Пресса о нас</a>

                <h1 class="section-title a-font text-3xl md:text-5xl mb-4 mt-4">{{ $pressMention->title }}</h1>

                <p class="date text-brand-gray-light mb-4">
                    {{ $pressMention->source_name }}@if ($pressMention->published_at) · {{ $pressMention->published_at->translatedFormat('j F Y') }}@endif
                </p>

                @if ($cover)
                    <div class="img mb-4">
                        <x-responsive-picture :media="$cover" :alt="$pressMention->title" img-class="w-full" />
                    </div>
                @endif

                @if ($pressMention->summary)
                    <p class="text-brand-gray font-medium text-lg mb-6">{{ $pressMention->summary }}</p>
                @endif

                <x-rich-content :content="$pressMention->content" />

                {{-- Источник: материал не наш, ссылка на оригинал обязательна. --}}
                <div class="mt-8 bg-[#F8F8F8] p-4 flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
                    <div class="text-brand-gray font-medium">
                        Материал опубликован в издании «{{ $pressMention->source_name }}»
                    </div>
                    <a href="{{ $pressMention->source_url }}" target="_blank" rel="noopener noreferrer"
                       class="text-[#2D92CE] font-semibold inline-flex gap-1 items-center hover:underline shrink-0">
                        <span>{{ $host ? 'Читать в оригинале: '.$host : 'Читать в оригинале' }}</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5h5v5m0-5L10 14M9 5H6a1 1 0 00-1 1v12a1 1 0 001 1h12a1 1 0 001-1v-3"/></svg>
                    </a>
                </div>
            </div>

            {{-- Другие публикации (сайдбар) --}}
            <div class="aside max-w-full md:max-w-[490px] flex-1">
                <h2 class="section-title a-font text-lg md:text-3xl mb-4 text-center">Другие публикации</h2>

                @if ($otherMentions->isNotEmpty())
                    <div class="col flex flex-col gap-6">
                        @foreach ($otherMentions as $other)
                            <x-press-card :mention="$other" />
                        @endforeach
                    </div>
                    <a href="{{ route('press') }}" class="mx-auto mt-6 block text-lg font-semibold hover:underline text-center">Показать все →</a>
                @else
                    <p class="text-center text-brand-gray-light">Других публикаций пока нет.</p>
                @endif
            </div>
        </div>
    </section>
</main>
</x-public-layout>
