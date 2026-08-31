<x-public-layout title="Ремонт и модернизация яхт Carter 30" description="Ремонт и модернизация яхт класса Carter 30: чертежи, документы и кейсы выполненных проектов">
<x-breadcrumbs_page title="Ремонт и модернизация">
</x-breadcrumbs_page>
<x-hero-section title="Ремонт и модернизация"
desc="Работы по корпусу, палубе, рангоуту и оборудованию яхт класса Carter 30."
bgImage="{{ asset('images/bg/regulations.webp') }}"
>

</x-hero-section>

{{-- ===== Описание раздела ===== --}}
@if (trim(strip_tags($intro, '<img>')) !== '')
<section class="py-10 px-4 sm:px-6 lg:px-8">
    <div class="container mx-auto">
        <div class="prose max-w-none text-brand-gray font-medium">
            {!! $intro !!}
        </div>
    </div>
</section>
@endif

{{-- ===== Чертежи и документы ===== --}}
@if (count($documents) > 0)
<section class="py-10 px-4 md:px-2">
    <div class="container mx-auto pdf-list">
        <h2 class="section-title a-font mb-8">Чертежи и документы</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach ($documents as $document)
                <div class="bg-[#F8F8F8] flex gap-4 hover:shadow-md transition-shadow p-4">
                    <div class="max-w-16">
                        <img class="w-full" src="{{ asset('images/icons/pdf.png') }}" alt="">
                    </div>
                    <div>
                        <div class="text-[#2E325C] text-sm md:text-lg font-semibold mb-4">{{ $document['title'] }}</div>
                        <div class="text-brand-gray-light font-medium mb-4 text-xs md:text-base">{{ $document['desc'] }}</div>
                        @if ($document['file_url'])
                        <a href="{{ $document['file_url'] }}" class="text-[#2E325C] text-sm md:text-lg font-semibold flex gap-4 items-center"><img src="{{ asset('images/icons/download.svg') }}" alt=""> <span>Скачать</span></a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ===== Кейсы ===== --}}
<section class="py-10 px-4 sm:px-6 lg:px-8">
    <div class="container mx-auto">
        <h2 class="section-title a-font mb-8">Выполненные проекты</h2>

        @if ($cases->isNotEmpty())
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($cases as $case)
                    @php($cover = $case->getFirstMedia('cover'))
                    <a href="{{ route('carter30.repair-case', $case) }}" class="group block bg-[#F8F8F8] overflow-hidden hover:shadow-md transition-shadow">
                        <div class="overflow-hidden h-52">
                            @if ($cover)
                                <x-responsive-picture :media="$cover"
                                    :alt="$case->title"
                                    img-class="w-full h-52 object-cover transition-transform duration-500 group-hover:scale-105" />
                            @else
                                <img class="w-full h-52 object-cover" src="{{ asset('images/gallery.webp') }}" alt="{{ $case->title }}">
                            @endif
                        </div>
                        <div class="p-4">
                            <h3 class="text-[#2E325C] text-lg font-semibold mb-2">{{ $case->title }}</h3>
                            @if ($case->yacht)
                                <div class="text-brand-gray-light text-sm mb-2">Яхта: {{ $case->yacht->name }}</div>
                            @endif
                            @if ($case->summary)
                                <p class="text-brand-gray font-medium text-sm mb-3">{{ Str::limit($case->summary, 140) }}</p>
                            @endif
                            <span class="text-[#2D92CE] font-semibold text-sm">Подробнее →</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <div class="text-center text-brand-gray-light py-8">
                Кейсы готовятся к публикации.
            </div>
        @endif
    </div>
</section>

{{-- ===== Кнопка заявки ===== --}}
<section class="py-10 bg-white px-4 sm:px-6 lg:px-8">
    <div class="container mx-auto bg-[#F8F8F8] p-8 text-center">
        <h2 class="section-title a-font text-[#2E325C] md:text-4xl text-2xl mb-4">Нужен ремонт или модернизация?</h2>
        <p class="text-brand-gray font-medium md:text-lg text-sm mb-6">Оставьте заявку — мы свяжемся с вами и обсудим объём работ.</p>
        <x-repair-request-button />
    </div>
</section>

<x-feedback-section>

</x-feedback-section>
</x-public-layout>
