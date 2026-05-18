<x-public-layout>
<x-breadcrumbs_page title="Соревнования сезона">
</x-breadcrumbs_page>
<x-hero-section title="Соревнования сезона"
desc="Регаты сезона CarterPro: даты, места проведения и карточки соревнований для подачи заявки на участие."
bgImage="{{ asset('images/bg/competitions.png') }}"
>
</x-hero-section>

{{-- ===== КАЛЕНДАРЬ РЕГАТ (Livewire) ===== --}}
@livewire('regattas-calendar')

{{-- ===== СПИСОК РЕГАТ (Livewire) ===== --}}
<livewire:regattas-list />

<x-feedback-section>
    
</x-feedback-section>
</x-public-layout>