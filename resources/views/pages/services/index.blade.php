<x-public-layout
    title="Услуги — Yacht Association"
    :description="$seoDescription !== '' ? $seoDescription : 'Аренда яхт и флота, проведение мероприятий на воде, обучение судовождению — услуги ассоциации парусного спорта.'">

    <x-breadcrumbs_page title="Услуги"></x-breadcrumbs_page>

    <x-hero-section
        title="Услуги"
        desc="Аренда яхт и флота, мероприятия на воде и обучение судовождению"
        bgImage="{{ $heroImage ?? asset('images/bg/charter.webp') }}"></x-hero-section>

    <section class="py-10 px-4 sm:px-6 lg:px-8">
        <div class="container mx-auto">
            @if (trim(strip_tags($intro, '<img>')) !== '')
                <div class="prose max-w-none text-brand-gray font-medium mb-10">{!! $intro !!}</div>
            @endif

            <x-service-cards :services="$services" />
        </div>
    </section>

    <x-feedback-section></x-feedback-section>
</x-public-layout>
