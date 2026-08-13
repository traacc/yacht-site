<x-public-layout
    :title="$type->label() . ' — Yacht Association'"
    :description="$type->seoDescription()">

    <x-breadcrumbs_page :title="$type->label()"></x-breadcrumbs_page>

    <x-hero-section
        :title="$type->label()"
        :desc="$type->tagline()"
        bgImage="{{ $heroImage ?? asset('images/bg/charter.webp') }}"></x-hero-section>

    <section class="py-10 px-4 sm:px-6 lg:px-8">
        <div class="container mx-auto">
            @if (trim(strip_tags($intro, '<img>')) !== '')
                <div class="prose max-w-none text-brand-gray font-medium mb-10">{!! $intro !!}</div>
            @endif

            {{-- ===== Ближайшие регаты ===== --}}
            <div class="mb-12">
                <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Ближайшие регаты</h2>

                @if ($upcoming->isNotEmpty())
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach ($upcoming as $regatta)
                            <x-foreign-regatta-card :regatta="$regatta" />
                        @endforeach
                    </div>
                @else
                    <p class="text-center text-brand-gray-light py-8">
                        Календарь зарубежных регат на сезон готовится. Оставьте заявку — сообщим, когда откроем набор.
                    </p>
                @endif
            </div>

            {{-- ===== Что входит в участие ===== --}}
            @if (count($included) > 0)
                <div class="mb-12">
                    <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Что входит в участие</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        @foreach ($included as $item)
                            <div class="border-l-2 border-[#2D92CE] pl-4">
                                <h3 class="a-font text-xl mb-2 text-[#2E325C]">{{ $item['title'] }}</h3>
                                @if (! empty($item['text']))
                                    <p class="text-brand-gray-light text-sm">{{ $item['text'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if (trim((string) $note) !== '')
                        <p class="text-sm text-brand-gray-light mt-4">{{ $note }}</p>
                    @endif
                </div>
            @endif

            {{-- Заявка без привязки к конкретной регате: «хочу за рубеж, ориентировочно в мае». --}}
            <div class="mb-12">
                <x-service-request-button
                    :type="$type"
                    label="Подобрать регату"
                    heading="Заявка на участие в зарубежной регате" />
            </div>

            {{-- ===== Прошедшие регаты ===== --}}
            @if ($past->isNotEmpty())
                <div class="mb-12">
                    <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Прошедшие регаты</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach ($past as $regatta)
                            <x-foreign-regatta-card :regatta="$regatta" past />
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ===== Галерея ===== --}}
            @if (count($gallery) > 0)
                <div class="mb-12">
                    <x-photo-gallery :photos="$gallery" title="Как проходят зарубежные регаты" />
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
