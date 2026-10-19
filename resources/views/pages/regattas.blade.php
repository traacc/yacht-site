<x-public-layout title="Календарь регат и парусных соревнований - расписание" description="Актуальное расписание гонок: весенние, летние и осенние регаты. Условия участия, трассы и онлайн-регистрация экипажей.">
<x-breadcrumbs_page title="Соревнования сезона {{ $seasonYear }}">
</x-breadcrumbs_page>
<x-hero-section title="Соревнования сезона {{ $seasonYear }}"
desc="Регаты сезона CarterPro: даты, места проведения и карточки соревнований для подачи заявки на участие."
bgImage="{{ asset('images/bg/competitions.webp') }}"
>
</x-hero-section>

{{-- ===== ТАБЫ: РЕЗУЛЬТАТЫ | КАЛЕНДАРЬ И СПИСОК РЕГАТ ===== --}}
<div x-data="{ activeTab: window.location.hash === '#results' ? 'results' : 'calendar' }"
    x-on:switch-competitions-tab.window="activeTab = $event.detail.tab; history.replaceState(null, '', location.pathname + location.search + ($event.detail.tab === 'results' ? '#results' : '')); if ($event.detail.tab === 'calendar') $nextTick(() => window.dispatchEvent(new Event('resize')))"
    class="container mx-auto">
    {{-- Сезон — общий для всех вкладок; вкладка запоминается в #якоре, чтобы
         переключатель сезона её не сбрасывал. --}}
    <div class="flex justify-end mt-8">
        <x-season-switcher :years="$seasonYears" :selected="$seasonYear" />
    </div>

    {{-- Навигация табов --}}
    <nav class="flex flex-col sm:flex-row border-b border-[#EAEAEA] mb-8 mt-4" role="tablist">
            <button
            @click="activeTab = 'calendar'; history.replaceState(null, '', location.pathname + location.search); $nextTick(() => window.dispatchEvent(new Event('resize')))"
            :class="activeTab === 'calendar'
                ? 'border-[#2D92CE] text-[#2D92CE]'
                : 'border-transparent text-[#2E325C] hover:text-[#2D92CE] hover:border-[#C6C6C6]'"
            class="px-3 py-2 text-sm sm:px-6 sm:py-3 sm:text-lg font-semibold border-l-2 sm:border-l-0 sm:border-b-2 transition-colors duration-200 cursor-pointer text-center"
            role="tab"
            :aria-selected="activeTab === 'calendar'"
        >
            Календарь регат
        </button>
        <button
            @click="activeTab = 'results'; history.replaceState(null, '', '#results')"
            :class="activeTab === 'results'
                ? 'border-[#2D92CE] text-[#2D92CE]'
                : 'border-transparent text-[#2E325C] hover:text-[#2D92CE] hover:border-[#C6C6C6]'"
            class="px-3 py-2 text-sm sm:px-6 sm:py-3 sm:text-lg font-semibold border-l-2 sm:border-l-0 sm:border-b-2 transition-colors duration-200 cursor-pointer text-center"
            role="tab"
            :aria-selected="activeTab === 'results'"
        >
            Результаты
        </button>
        <a
            href="{{ route('series', ['season' => $seasonYear]) }}"
            class="px-3 py-2 text-sm sm:px-6 sm:py-3 sm:text-lg font-semibold border-l-2 sm:border-l-0 sm:border-b-2 border-transparent text-[#2E325C] hover:text-[#2D92CE] hover:border-[#C6C6C6] transition-colors duration-200 cursor-pointer text-center"
            role="tab"
        >
            Серии
        </a>
        <a
            href="{{ route('regatta-entries') }}"
            class="px-3 py-2 text-sm sm:px-6 sm:py-3 sm:text-lg font-semibold border-l-2 sm:border-l-0 sm:border-b-2 border-transparent text-[#2E325C] hover:text-[#2D92CE] hover:border-[#C6C6C6] transition-colors duration-200 cursor-pointer text-center"
            role="tab"
        >
            Заявки
        </a>
    </nav>

    {{-- Содержимое вкладки «Календарь регат» --}}
    <div x-show="activeTab === 'calendar'" role="tabpanel">
        <livewire:regattas-list :year="$seasonYear" />
        <x-regatta-series-list :series="$series" />
        <livewire:regattas-calendar :year="$seasonYear" :show-selector="false" />

    </div>



    {{-- Содержимое вкладки «Результаты» --}}
    <div x-show="activeTab === 'results'" role="tabpanel">
        <x-regatta-results :regattas="$regattas"></x-regatta-results>
    </div>


</div>

@if($documents !== [])
<section class="container mx-auto pb-12">
    <h3 class="text-2xl font-semibold text-[#2E325C] mb-6">Документы</h3>
    <x-document-list :documents="$documents" />
</section>
@endif

<x-feedback-section>
    
</x-feedback-section>
</x-public-layout>