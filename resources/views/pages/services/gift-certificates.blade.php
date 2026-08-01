<x-public-layout
    title="Подарочные сертификаты — Yacht Association"
    description="Подарочный сертификат на выход в море, обучение судовождению или аренду яхты: каталог, номиналы и оформление заказа.">

    <x-breadcrumbs_page title="Подарочные сертификаты"></x-breadcrumbs_page>

    <x-hero-section
        title="Подарочные сертификаты"
        desc="Подарок на выход в море, обучение или аренду яхты"
        bgImage="{{ $heroImage ?? asset('images/bg/charter.webp') }}"></x-hero-section>

    <section class="py-10 px-4 sm:px-6 lg:px-8">
        <div class="container mx-auto">
            @if (trim(strip_tags($intro, '<img>')) !== '')
                <div class="prose max-w-none text-brand-gray font-medium mb-10">{!! $intro !!}</div>
            @endif

            {{-- ===== Каталог ===== --}}
            <div class="mb-12">
                <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Каталог сертификатов</h2>

                @if ($certificates->isNotEmpty())
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach ($certificates as $certificate)
                            <x-gift-certificate-card :certificate="$certificate" :type="$type" />
                        @endforeach
                    </div>
                @else
                    <p class="text-center text-brand-gray-light py-8">
                        Каталог сертификатов готовится. Оставьте заявку — подберём подарок под ваш случай.
                    </p>
                @endif
            </div>

            {{-- ===== Как это работает ===== --}}
            @if (count($steps) > 0)
                <div class="mb-12">
                    <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Как это работает</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        @foreach ($steps as $step)
                            <div class="border-l-2 border-[#2D92CE] pl-4">
                                <h3 class="a-font text-xl mb-2 text-[#2E325C]">{{ $step['title'] }}</h3>
                                @if (! empty($step['text']))
                                    <p class="text-brand-gray-light text-sm">{{ $step['text'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if (trim((string) $note) !== '')
                        <p class="text-sm text-brand-gray-light mt-4">{{ $note }}</p>
                    @endif
                </div>
            @endif

            {{-- Заявка без привязки к сертификату: «не знаю, что выбрать». --}}
            <div class="mb-12">
                <x-service-request-button
                    :type="$type"
                    label="Подобрать сертификат"
                    heading="Заявка на подарочный сертификат" />
            </div>

            {{-- ===== Галерея ===== --}}
            @if (count($gallery) > 0)
                <div class="mb-12">
                    <x-photo-gallery :photos="$gallery" title="Что дарим" />
                </div>
            @endif

            {{-- ===== Другие услуги ===== --}}
            <div>
                <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Другие услуги</h2>
                <x-service-cards :current="$type" />
            </div>
        </div>
    </section>

    <x-feedback-section></x-feedback-section>
</x-public-layout>
