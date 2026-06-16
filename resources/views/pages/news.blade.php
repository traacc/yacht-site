<x-public-layout title="Новости парусного спорта и регат - актуальные события" description="Итоги гонок, анонсы регат, изменения в правилах, интервью с капитанами и события в мире российского парусного спорта">
<x-breadcrumbs_page title="Новости ассоциации">
</x-breadcrumbs_page>
<main class="main">
    <section class="md:py-12 py-4 reggata-list">
        <div class="container mx-auto">
            <div class="flex justify-between mb-6 flex-col md:flex-row">
                <h2 class="section-title a-font text-5xl">Новости ассоциации</h2>
            </div>
        </div>
    </section>

    @if($news->isEmpty())
        <section class="md:py-12 py-4">
            <div class="container mx-auto text-center py-16">
                <p class="text-xl text-brand-gray-light">Новостей пока нет.</p>
            </div>
        </section>
    @else
        @php
            $items    = $news->getCollection();
        @endphp

        <section class="md:py-12 py-4">

            {{-- Сетка новостей (5+) --}}
            @if($items->isNotEmpty())
                <div class="grid md:grid-cols-3 gap-6 container mx-auto">
                    @foreach($items as $item)
                        <article class="overflow-hidden shadow-xs hover:shadow-md transition-shadow group flex md:flex-col">
                            <div class="overflow-hidden md:h-52 shrink-0">
                                @if($item->cover_image_url)
                                <img src="{{ $item->cover_image_url ? Storage::url($item->cover_image_url) : asset('images/news/news_1.webp') }}"
                                    alt="{{ $item->title }}" class="w-full max-w-[150px] md:max-w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                @else
                                    <div class="w-full max-w-[150px] bg-gray-400 md:max-w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"></div>
                                @endif
                            </div>
                            <div class="md:p-4 p-2 bg-[#F8F8F8]">
                                <h3 class="font-semibold text-[#2E325C] text-sm md:text-lg mb-2 md:h-14">
                                    {{ $item->title }}
                                </h3>
                                <p class="font-medium text-brand-gray mb-3 text-xs md:text-base">{{ Str::limit(strip_tags($item->content), 60) }}</p>
                                <div class="mb-2 text-brand-gray-light text-xs md:text-base">{{ $item->published_at->translatedFormat('j F Y') }}</div>
                                <div class="">
                                    <a href="{{ route('news-details', $item) }}" class="text-[#2E325C] font-semibold hover:underline text-xs md:text-lg">Читать далее →</a>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Пагинация --}}
        @if($news->hasPages())
            <div class="container mx-auto py-6">
                {{ $news->links() }}
            </div>
        @endif
    @endif
</main>
</x-public-layout>
