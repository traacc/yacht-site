<x-public-layout>
<x-breadcrumbs_page title="Руководство Ассоциации">
</x-breadcrumbs_page>
<x-hero-section title="Руководство Ассоциации"
desc="Команда, отвечающая за развитие Ассоциации, организацию соревнований и управление деятельностью сообщества." 
bgImage="{{ asset('images/bg/management.webp') }}"
>
    
</x-hero-section>
{{-- ===== Руководители ===== --}}
@php
    // Преобразуем в JSON для Alpine.js, добавляя id по индексу
    $peopleJson = collect($members)
        ->map(fn (array $m, int $i) => array_merge($m, ['id' => $i + 1]))
        ->values()
        ->toJson();
@endphp
<main class="main pb-12 px-4 md:px-2" x-data="{
    open: false,
    selectedPerson: null,
    people: {{ $peopleJson }}
}">
    <section class="py-10">
        <div class="container mx-auto">
            <h2 class="section-title a-font mb-8">Руководители</h2>
            <div class="list grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">

                <template x-for="person in people" :key="person.id">
                    <div class="card bg-[#F8F8F8]">
                        <img :src="person.image || '{{ asset('images/icons/avatar-default.svg') }}'" :alt="person.name">
                        <div class="info p-2 md:p-4">
                            <h4 class="text-[#2E325C] font-semibold md:text-xl text-sm mb-2 md:mb-4" x-text="person.name"></h4>
                            <div class="md:text-xl text-xs text-brand-gray mb-2 md:mb-4 md:h-14" x-text="person.position"></div>
                            <a @click.prevent="selectedPerson = person; open = true" class="md:text-xl text-sm font-semibold flex items-center gap-2" href="#">Подробнее {!! file_get_contents(public_path('images/icons/l-arrow-right.svg')) !!}</a>
                        </div>
                    </div>
                </template>

            </div>

        </div>
    </section>

    <div x-show="open" 
            x-cloak 
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 person-modal">
        <div class="md:flex relative p-6 max-w-[1000px] overflow-auto max-h-[95dvh] bg-white gap-6"
            @click.away="open = false" 
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
        >
            <div class="photo hidden md:block max-w-1/2 shrink-0">
                <img class="max-w-full h-full object-cover" :src="selectedPerson?.image || '{{ asset('images/icons/avatar-default.svg') }}'" :alt="selectedPerson?.name">
            </div>
            <div class="info">
                <div class="info__header flex justify-between items-start md:mb-4">
                    <h4 class="a-font text-2xl md:text-3xl text-[#2E325C]" x-text="selectedPerson?.name"></h4>
                    <div class="close">
                        <button @click="open = false" class="text-2xl font-bold">{!! file_get_contents(public_path('images/icons/close.svg')) !!}</button>
                    </div>
                </div>
                <div class="md:text-lg text-sm font-semibold text-[#2E325C] mb-4" x-text="selectedPerson?.position"></div>
                <div class="photo photo-mobile md:hidden">
                    <img class="max-w-full" :src="selectedPerson?.image || '{{ asset('images/icons/avatar-default.svg') }}'" :alt="selectedPerson?.name">
                </div>
                <h5 class="font-semibold text-[#2E325C] mt-4 md:mt-0 md:mb-6">О руководителе</h5>
                <p class="text-brand-gray mb-6" x-html="selectedPerson?.description"></p>
                <h5 class="font-semibold text-[#2E325C] mb-6 hidden">Зоны ответственности в Ассоциации</h5>
                <ul class="text-brand-gray list-disc pl-6 space-y-3.5 hidden">
                    <template x-for="responsibility in selectedPerson?.responsibilities" :key="responsibility">
                        <li x-text="responsibility"></li>
                    </template>
                </ul>
            </div>
        </div>

    </div>
</main>


<x-feedback-section>
    
</x-feedback-section>
</x-public-layout>