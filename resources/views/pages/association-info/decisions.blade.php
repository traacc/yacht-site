<x-public-layout>
<x-breadcrumbs_page title="Решения общего собрания">
</x-breadcrumbs_page>
<x-hero-section title="Решения общего собрания"
desc="Официальные решения, принятые участниками Ассоциации CarterPro в рамках общего собрания." 
bgImage="{{ asset('images/bg/decisions.webp') }}"
>
    
</x-hero-section>
{{-- ===== Документы ===== --}}
<section class="py-10">
    <div class="container mx-auto pdf-list">
        <h2 class="section-title a-font mb-8">Документы</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @forelse ($documents as $document)
                <div class="bg-[#F8F8F8] flex gap-4 hover:shadow-md transition-shadow cursor-pointer p-4">
                    <div class="max-w-10 md:max-w-16">
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

<section class="py-10 bg-white px-4 sm:px-6 lg:px-8">
<div class="container mx-auto bg-[#F8F8F8] flex flex-col md:flex-row gap-10 items-center">
    <div class="info px-4 sm:px-6 lg:px-8">
        <h2 class="section-title a-font text-[#2E325C] text-2xl md:text-5xl mb-8">О решениях общего собрания</h2>
        <p class="text-brand-gray font-medium text-sm md:text-lg mb-4">Решения общего собрания принимаются участниками Ассоциации и регулируют ключевые вопросы её деятельности, включая проведение соревнований, изменения в регламенте и развитие класса Carter 30.</p>
        <p class="text-brand-gray font-medium text-sm md:text-lg">Все решения фиксируются в протоколах и являются обязательными для исполнения участниками Ассоциации.</p>
    </div>
    <div class="pic max-w-[720px] shrink-0">
        <img class="w-full" src="{{ asset('images/decisions.webp') }}" alt="">
    </div>
</div>
</section>
<x-feedback-section>
    
</x-feedback-section>
</x-public-layout>