<x-public-layout title="Новости парусного мира — события, регаты и яхтинг" description="Обзоры новостей парусного спорта из профильных российских и зарубежных источников со ссылками на оригинальные публикации">
<x-breadcrumbs_page title="Новости парусного мира">
</x-breadcrumbs_page>

<main class="main">
    <section class="md:py-12 py-4">
        <div class="container mx-auto">
            <h1 class="section-title a-font text-3xl md:text-5xl mb-4">Новости парусного мира</h1>
            <p class="text-brand-gray font-medium md:text-lg max-w-3xl">
                Обзоры событий, регат и важных публикаций о парусном спорте. У каждого материала указан источник и доступна ссылка на оригинал.
            </p>
        </div>
    </section>

    <section class="md:pb-12 pb-4">
        <div class="container mx-auto">
            @if ($worldNews->isEmpty())
                <p class="text-center text-xl text-brand-gray-light py-16">Публикаций пока нет.</p>
            @else
                <div class="grid md:grid-cols-3 gap-6">
                    @foreach ($worldNews as $newsItem)
                        <x-world-news-card :news="$newsItem" />
                    @endforeach
                </div>

                @if ($worldNews->hasPages())
                    <div class="py-6">
                        {{ $worldNews->links() }}
                    </div>
                @endif
            @endif
        </div>
    </section>
</main>
</x-public-layout>
