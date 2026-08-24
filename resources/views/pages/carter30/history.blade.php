<x-public-layout title="История класса Carter 30" description="История проекта Carter 30: появление класса, польские «Телига-89» и «Телига-91», развитие флота и Ассоциации">
<x-breadcrumbs_page title="История">
</x-breadcrumbs_page>
<x-hero-section title="История класса Carter 30"
desc="Как появился проект, каким был флот и что привело к созданию Ассоциации класса."
bgImage="{{ asset('images/bg/regulations.webp') }}"
>

</x-hero-section>

<section class="py-10 px-4 sm:px-6 lg:px-8">
    <div class="container mx-auto">
        @if (trim(strip_tags($content, '<img>')) !== '')
            <div class="prose max-w-none text-brand-gray font-medium">
                {!! $content !!}
            </div>
        @else
            <div class="text-center text-brand-gray-light py-8">
                Материал готовится к публикации.
            </div>
        @endif
    </div>
</section>

@if($documents !== [])
<section class="py-10 px-4 sm:px-6 lg:px-8">
    <div class="container mx-auto">
        <h3 class="text-2xl font-semibold text-[#2E325C] mb-6">Документы</h3>
        <x-document-list :documents="$documents" />
    </div>
</section>
@endif

<x-feedback-section>

</x-feedback-section>
</x-public-layout>
