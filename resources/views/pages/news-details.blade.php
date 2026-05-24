<x-public-layout>
<x-breadcrumbs_page :title="$news->title">
</x-breadcrumbs_page>
<main class="main">
    <section class="py-12">
        <div class="container mx-auto flex flex-col md:flex-row gap-12">
            <div class="content max-w-[902px]">
                <h2 class="section-title a-font text-5xl mb-4">{{ $news->title }}</h2>
                <p class="date text-brand-gray-light mb-4">{{ $news->published_at->isoFormat('D MMMM Y') }}</p>

                @if($news->cover_image_url)
                    <div class="img mb-4">
                        <img class="w-full" src="{{ asset('storage/' . $news->cover_image_url) }}" alt="{{ $news->title }}">
                    </div>
                @endif

                <div class="text space-y-4 text-lg">
                    {!! nl2br(e($news->content)) !!}
                </div>
            </div>

            {{-- Другие новости (сайдбар) --}}
            <div class="aside">
                <h3 class="section-title a-font text-lg md:text-3xl mb-4 text-center">Другие новости</h3>
                @if($otherNews->isNotEmpty())
                    <div class="col flex flex-col gap-8">
                        @foreach($otherNews as $other)
                            <div class="item flex">
                                <div class="overflow-hidden md:h-52 shrink-0 max-w-[200px]">
                                    <img
                                        class="w-full max-w-[150px] md:max-w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                        src="{{ $other->cover_image_url ? asset('storage/' . $other->cover_image_url) : asset('images/gallery.png') }}"
                                        alt="{{ $other->title }}"
                                    >
                                </div>
                                <div class="info p-2 bg-[#F8F8F8]">
                                    <h4 class="text-lg font-semibold mb-3">{{ $other->title }}</h4>
                                    <p class="mb-3 font-medium">{{ Str::limit(strip_tags($other->content), 30) }}</p>
                                    <div class="date mb-3 text-brand-gray-light">{{ $other->published_at->isoFormat('D MMMM Y') }}</div>
                                    <a href="{{ route('news-details', $other) }}" class="text-lg font-semibold hover:underline">Читать далее →</a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <a href="{{ route('news') }}" class="mx-auto mt-6 block text-lg font-semibold hover:underline text-center">Показать все →</a>
                @else
                    <p class="text-center text-brand-gray-light">Других новостей пока нет.</p>
                @endif
            </div>
        </div>
    </section>
</main>
</x-public-layout>
