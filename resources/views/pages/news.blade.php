<x-public-layout>
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
            $featured = $items->first();
            $sideNews = $items->slice(1, 3);
            $gridNews = $items->slice(4);
        @endphp

        <section class="md:py-12 py-4">
            <div class="flex container mx-auto mb-6">
                <div class="row flex flex-col md:flex-row gap-6">
                    {{-- Главная новость --}}
                    <div class="col">
                        <div class="item">
                            <div class="img">
                                <img
                                    class="w-full h-full"
                                    src="{{ $featured->cover_image_url ? asset('storage/' . $featured->cover_image_url) : asset('images/gallery.png') }}"
                                    alt="{{ $featured->title }}"
                                >
                            </div>
                            <div class="info p-4 bg-[#F8F8F8]">
                                <h4 class="text-xl font-semibold mb-4">{{ $featured->title }}</h4>
                                <p class="mb-4 font-medium">{{ Str::limit(strip_tags($featured->content), 120) }}</p>
                                <div class="date mb-4 text-brand-gray-light">{{ $featured->published_at->isoFormat('D MMMM Y') }}</div>
                                <a href="{{ route('news-details', $featured) }}" class="text-lg font-semibold hover:underline">Читать далее →</a>
                            </div>
                        </div>
                    </div>

                    {{-- Боковые новости (2–4) --}}
                    @if($sideNews->isNotEmpty())
                        <div class="col flex flex-col gap-4">
                            @foreach($sideNews as $item)
                                <div class="item flex">
                                    <div class="img max-w-[300px]">
                                        <img
                                            class="w-full max-w-[300px] md:max-w-full h-full object-cover"
                                            src="{{ $item->cover_image_url ? asset('storage/' . $item->cover_image_url) : asset('images/gallery.png') }}"
                                            alt="{{ $item->title }}"
                                        >
                                    </div>
                                    <div class="info p-4 bg-[#F8F8F8] h-full">
                                        <h4 class="text-xl font-semibold mb-3">{{ $item->title }}</h4>
                                        <p class="mb-3 font-medium">{{ Str::limit(strip_tags($item->content), 40) }}</p>
                                        <div class="date mb-3 text-brand-gray-light">{{ $item->published_at->isoFormat('D MMMM Y') }}</div>
                                        <a href="{{ route('news-details', $item) }}" class="text-lg font-semibold hover:underline">Читать далее →</a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Сетка новостей (5+) --}}
            @if($gridNews->isNotEmpty())
                <div class="grid md:grid-cols-4 gap-6 container mx-auto">
                    @foreach($gridNews as $item)
                        <div class="item flex md:flex-col">
                            <div class="img max-w-[300px] md:max-w-full">
                                <img
                                    class="w-full max-w-[300px] md:max-w-full h-full object-cover"
                                    src="{{ $item->cover_image_url ? asset('storage/' . $item->cover_image_url) : asset('images/gallery.png') }}"
                                    alt="{{ $item->title }}"
                                >
                            </div>
                            <div class="info p-4 bg-[#F8F8F8]">
                                <h4 class="text-xl font-semibold mb-4">{{ $item->title }}</h4>
                                <p class="mb-4 font-medium">{{ Str::limit(strip_tags($item->content), 40) }}</p>
                                <div class="date mb-4 text-brand-gray-light">{{ $item->published_at->isoFormat('D MMMM Y') }}</div>
                                <a href="{{ route('news-details', $item) }}" class="text-lg font-semibold hover:underline">Читать далее →</a>
                            </div>
                        </div>
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
