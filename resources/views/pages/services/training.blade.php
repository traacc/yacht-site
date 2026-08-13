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

            <div class="mb-12">
                <x-service-request-button
                    :type="$type"
                    label="Записаться на обучение"
                    heading="Заявка на обучение" />
            </div>

            {{-- ===== Программы ===== --}}
            @if (count($programs) > 0)
                <div class="mb-12">
                    <h2 class="section-title a-font text-2xl md:text-3xl mb-6">Программы обучения</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach ($programs as $program)
                            <div class="border border-[#C6C6C6] p-6 flex flex-col">
                                <h3 class="a-font text-xl mb-2 text-[#2E325C]">{{ $program['title'] }}</h3>

                                @if (! empty($program['desc']))
                                    <p class="text-brand-gray-light text-sm mb-4">{{ $program['desc'] }}</p>
                                @endif

                                <div class="mt-auto text-sm">
                                    @if (! empty($program['duration']))
                                        <p class="text-brand-gray-light">Длительность: {{ $program['duration'] }}</p>
                                    @endif
                                    @if (! empty($program['price']))
                                        <p class="font-semibold text-[#2D92CE]">{{ $program['price'] }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ===== Галерея ===== --}}
            @if (count($gallery) > 0)
                <div class="mb-12">
                    <x-photo-gallery :photos="$gallery" title="Как проходит обучение" />
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
