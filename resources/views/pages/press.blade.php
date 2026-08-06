<x-public-layout title="Пресса о нас — публикации об ассоциации и Carter 30" description="Статьи сторонних изданий об ассоциации парусного спорта и соревнованиях класса Carter 30: ссылки на оригиналы и тексты публикаций">
<x-breadcrumbs_page title="Пресса о нас">
</x-breadcrumbs_page>

<main class="main">
    <section class="md:py-12 py-4">
        <div class="container mx-auto">
            <h1 class="section-title a-font text-3xl md:text-5xl mb-4">Пресса о нас</h1>
            <p class="text-brand-gray font-medium md:text-lg max-w-3xl">
                Публикации сторонних изданий об ассоциации и соревнованиях класса Carter 30.
            </p>
        </div>
    </section>

    <section class="md:pb-12 pb-4">
        <div class="container mx-auto">
            @if ($mentions->isEmpty())
                <p class="text-center text-xl text-brand-gray-light py-16">Публикаций пока нет.</p>
            @else
                <div class="grid md:grid-cols-3 gap-6">
                    @foreach ($mentions as $mention)
                        <x-press-card :mention="$mention" />
                    @endforeach
                </div>

                @if ($mentions->hasPages())
                    <div class="py-6">
                        {{ $mentions->links() }}
                    </div>
                @endif
            @endif
        </div>
    </section>
</main>
</x-public-layout>
