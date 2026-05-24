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
        @endphp

        <section class="md:py-12 py-4">

            {{-- Сетка новостей (5+) --}}
            @if($items->isNotEmpty())
                <div class="grid md:grid-cols-3 gap-6 container mx-auto">
                    @foreach($items as $item)
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
