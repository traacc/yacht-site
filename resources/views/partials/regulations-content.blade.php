{{--
    Тело страницы технического регламента.

    Один источник контента (настройки regulations.*) — две страницы:
    «Ассоциация → Технический регламент яхт» и «Carter 30 → Технический
    регламент класса». Отличаются они только хлебными крошками и hero-блоком,
    поэтому всё остальное живёт здесь.

    Ожидает: $documents, $before_note, $provisions.
--}}
{{-- ===== Документы регламента ===== --}}
<section class="py-10 px-4 md:px-2">
    <div class="container mx-auto pdf-list">
        <h2 class="section-title a-font mb-8">Документы регламента</h2>
        <div class="before_documents">{!! $before_note !!}</div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @forelse ($documents as $document)
                <div class="bg-[#F8F8F8] flex gap-4 hover:shadow-md transition-shadow cursor-pointer p-4 ">
                    <div class="max-w-16">
                        <img class="w-full" src="{{ asset('images/icons/pdf.png') }}" alt="">
                    </div>
                    <div class="">
                        <div class="text-[#2E325C] text-sm md:text-lg font-semibold mb-4">{{ $document['title'] }}</div>
                        <div class="text-brand-gray-light font-medium mb-4 text-xs md:text-base">{{ $document['desc'] }}</div>
                        @if ($document['file_url'])
                        <a href="{{ $document['file_url'] }}" class="text-[#2E325C] text-sm md:text-lg font-semibold flex gap-4 items-center"><img src="{{ asset('images/icons/download.svg') }}" alt=""> <span>Скачать PDF</span></a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="col-span-3 text-center text-brand-gray-light py-8">
                    Документы пока не загружены.
                </div>
            @endforelse
        </div>
    </div>
</section>

{{-- ===== Основные положения (редактируются в админке) ===== --}}
@if (trim(strip_tags($provisions ?? '')) !== '')
<section class="py-10 bg-white px-4 sm:px-6 lg:px-8 text-sm md:text-base">
    <div class="container mx-auto">
        <h2 class="section-title a-font text-[#2E325C] mb-8">Основные положения регламента</h2>

        <div class="prose max-w-none text-brand-gray font-medium">
            {!! $provisions !!}
        </div>
    </div>
</section>
@endif

<section class="py-10 bg-white">
<div class="container mx-auto bg-[#F8F8F8] flex flex-col md:flex-row gap-10 items-center">
    <div class="info px-4 sm:px-6 lg:px-8">
        <h2 class="section-title a-font text-[#2E325C] md:text-5xl text-3xl mb-8">Допуск яхты к соревнованиям</h2>
        <p class="text-brand-gray font-medium md:text-lg text-sm mb-8">Для участия в регате яхта должна быть зарегистрирована в базе Ассоциации и соответствовать техническому регламенту.</p>
        @guest
        <button @click.prevent="$dispatch('open-login-modal', { tab: 'register' })" class="mt-6 bg-[#2D92CE] text-white py-2 px-6 hover:bg-[#0074CC] transition-colors md:text-lg text-sm font-semibold w-full max-w-[300px]">
        Зарегистрировать яхту →
        </button>
        @else
        <a href="/user/yachts?action=create" @click="isRequestModalOpen=true" class="mt-6 bg-[#2D92CE] text-white py-2 px-6 hover:bg-[#0074CC] transition-colors md:text-lg text-sm font-semibold w-full max-w-[300px]">
        Зарегистрировать яхту →
        </a>
        @endguest
    </div>
    <div class="pic shrink-0">
        <img class="w-full" src="{{ asset('images/regulation.webp') }}" alt="">
    </div>
</div>
</section>
