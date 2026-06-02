<x-public-layout>
<x-breadcrumbs_page title="Соревнования сезона">
</x-breadcrumbs_page>
<x-hero-section title="Соревнования сезона"
desc="Регаты сезона CarterPro: даты, места проведения и карточки соревнований для подачи заявки на участие."
bgImage="{{ asset('images/bg/competitions.webp') }}"
>
</x-hero-section>

{{-- ===== ТАБЫ: РЕЗУЛЬТАТЫ | КАЛЕНДАРЬ И СПИСОК РЕГАТ ===== --}}
<div x-data="{ activeTab: 'calendar' }" class="container mx-auto">
    {{-- Навигация табов --}}
    <nav class="flex border-b border-[#EAEAEA] mb-8 mt-8" role="tablist">
            <button
            @click="activeTab = 'calendar'; $nextTick(() => window.dispatchEvent(new Event('resize')))"
            :class="activeTab === 'calendar'
                ? 'border-[#2D92CE] text-[#2D92CE]'
                : 'border-transparent text-[#2E325C] hover:text-[#2D92CE] hover:border-[#C6C6C6]'"
            class="px-6 py-3 text-lg font-semibold border-b-2 transition-colors duration-200 cursor-pointer"
            role="tab"
            :aria-selected="activeTab === 'calendar'"
        >
            Календарь регат
        </button>
        <button
            @click="activeTab = 'results'"
            :class="activeTab === 'results'
                ? 'border-[#2D92CE] text-[#2D92CE]'
                : 'border-transparent text-[#2E325C] hover:text-[#2D92CE] hover:border-[#C6C6C6]'"
            class="px-6 py-3 text-lg font-semibold border-b-2 transition-colors duration-200 cursor-pointer"
            role="tab"
            :aria-selected="activeTab === 'results'"
        >
            Результаты
        </button>
    </nav>

    {{-- Содержимое вкладки «Календарь регат» --}}
    <div x-show="activeTab === 'calendar'" role="tabpanel">
        <livewire:regattas-list />
        @livewire('regattas-calendar')
        
    </div>

    {{-- Содержимое вкладки «Результаты» --}}
    <div x-show="activeTab === 'results'" role="tabpanel">
        <x-regatta-results :regattas="$regattas"></x-regatta-results>
    </div>


</div>

<x-feedback-section>
    
</x-feedback-section>
</x-public-layout>