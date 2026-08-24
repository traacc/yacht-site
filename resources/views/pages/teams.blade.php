<x-public-layout title="Яхтенные команды и экипажи - каталог участников" description="Реестр парусных команд: составы, опыт, достижения и поиск парнтеров. Создавайте экипажи и готовьтесь к регатам вместе">
<x-breadcrumbs_page title="Команды Ассоциации">
</x-breadcrumbs_page>
<x-hero-section title="Команды Ассоциации"
    desc="Зарегистрированные команды класса Carter 30, участвующие в регатах сезона."
    bgImage="{{ asset('images/bg/teams.webp') }}"
>
</x-hero-section>

<main class="main">
    <livewire:teams-list />

    @if($documents !== [])
    <section class="container mx-auto pb-12">
        <h3 class="text-2xl font-semibold text-[#2E325C] mb-6">Документы</h3>
        <x-document-list :documents="$documents" />
    </section>
    @endif
</main>

<x-feedback-section>
</x-feedback-section>
</x-public-layout>
