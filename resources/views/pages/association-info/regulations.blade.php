<x-public-layout title="Технический регламент яхт - требования к судам CarterPro" description="Официальные нормы допуска: классы парусников, замеры, требования к оборудованию и безопасности для участия в регатах">
<x-breadcrumbs_page title="Технический регламент яхт">
</x-breadcrumbs_page>
<x-hero-section title="Технический регламент яхт"
desc="Правила и требования к яхтам класса Carter 30, участвующим в регатах Ассоциации." 
bgImage="{{ asset('images/bg/regulations.webp') }}"
>
    
</x-hero-section>
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
<section class="py-10 bg-white px-4 sm:px-6 lg:px-8 text-sm md:text-base">
    <!--<h4 class="a-font md:text-2xl text-[#2E325C] md:mb-6 mb-3"></h4>-->
    <div class="container mx-auto">
        <h2 class="section-title a-font text-[#2E325C] mb-8">Основные положения регламента</h2>

        <ol class="text-brand-gray font-medium list-decimal list-inside space-y-4">
            <li>К классу «Картер-30» относятся яхты, соответствующие чертежам проекта Д. Картера, польские яхты «Телига-89» и «Телига-91», а также яхты, построенные с использованием матриц этого проекта.</li>
            <li>Правила класса направлены на сохранение флота и поддержание сопоставимых технических характеристик яхт.</li>
            <li>Гонки класса проводятся по действительному времени прохождения дистанции, без применения гандикапа.</li>
            <li>К участию в зачете класса допускаются яхты, соответствующие требованиям правил и прошедшие установленную процедуру подтверждения соответствия.</li>
            <li>Правила устанавливают основные ограничения по корпусу, килю, рулевому устройству, рангоуту, такелажу, парусам, двигателю и оборудованию.</li>
            <li>Все изменения, влияющие на соответствие яхты классу, подлежат рассмотрению Техническим комитетом.</li>
            <li>Правила имеют открытый характер: все, что прямо не запрещено, допускается при соблюдении установленных ограничений.</li>
            <li>Соответствие яхт требованиям класса контролирует Технический комитет Ассоциации класса.</li>
            <li>Для участия в соревнованиях яхта должна иметь действующее мерительное свидетельство ORC и/или подтверждение соответствия классу.</li>
            <li>Ответственность за безопасность яхты, экипажа и исправность оборудования несет владелец яхты или капитан.</li>
        </ol>
    </div>
</section>
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
<x-feedback-section>
    
</x-feedback-section>
</x-public-layout>