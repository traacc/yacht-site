<x-public-layout title="Техническая помощь по яхтам Carter 30" description="Справочник специалистов по обслуживанию и ремонту яхт класса Carter 30: электрика, механика, конструктив, отделка">
<x-breadcrumbs_page title="Техническая помощь">
</x-breadcrumbs_page>
<x-hero-section title="Техническая помощь"
desc="Специалисты по обслуживанию, ремонту и подготовке яхт класса Carter 30."
bgImage="{{ asset('images/bg/regulations.webp') }}"
>

</x-hero-section>

<main class="main py-10">
    @include('partials.help-directory')

    @if($documents !== [])
    <section class="container mx-auto pt-10">
        <h3 class="text-2xl font-semibold text-[#2E325C] mb-6">Документы</h3>
        <x-document-list :documents="$documents" />
    </section>
    @endif
</main>

<x-feedback-section>

</x-feedback-section>
</x-public-layout>
