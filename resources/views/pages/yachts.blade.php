<x-public-layout>
<x-breadcrumbs_page title="Яхты Ассоциации">
</x-breadcrumbs_page>
<x-hero-section title="Яхты Ассоциации"
desc="Список яхт класса Carter 30, зарегистрированных в Ассоциации и участвующих в регатах сезона."
bgImage="{{ asset('images/bg/yachts.png') }}"
>
</x-hero-section>

<main x-data="{
    yacht_modal_open: false,
    selectedYacht: null,
    yachtsData: {{ $yachtsJson }},
    search: '',
    get filteredYachts() {
        return this.yachtsData.filter(yacht =>
            yacht.name.toLowerCase().includes(this.search.toLowerCase()) ||
            yacht.vfps_number.toLowerCase().includes(this.search.toLowerCase()) ||
            (yacht.owner_name && yacht.owner_name.toLowerCase().includes(this.search.toLowerCase()))
        );
    },
    openYachtModal(yacht) {
        this.selectedYacht = yacht;
        this.yacht_modal_open = true;
    }
}" class="main">
    <section class="py-12 reggata-list">
        <div class="max-w-(--breakpoint-2xl) mx-auto sm:px-6 lg:px-8">
            <div class="flex items-center flex-col md:flex-row justify-between mb-6">
                <h2 class="section-title a-font">Список яхт</h2>
                <a href="/user" class="bg-[#2D92CE] cursor-pointer text-white hover:bg-[#0074CC] py-2 px-4 transition-colors">Зарегистрировать яхту  →</a>
            </div>
            <div class="searchbar bg-[#F8F8F8] mb-2">
                <input x-model="search" class="w-full pl-8 bg-[#F8F8F8] border-none" type="text" placeholder="Поиск">
            </div>
            <div class="reggata-list__items">
                <table class="w-full text-left border-collapse responsive-table">
                    <thead>
                        <tr>
                            <th class="py-2 a-font text-center text-2xl">Название</th>
                            <th class="py-2 a-font text-center text-2xl">Парус №</th>
                            <th class="py-2 a-font text-center text-2xl">Владелец</th>
                            <th class="py-2 a-font text-center text-2xl">Балл ORC</th>
                            <th class="py-2 a-font text-center text-2xl">Сертификат ORC</th>
                            <th class="py-2 a-font text-center"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="yacht in filteredYachts" :key="yacht.id">
                        <tr class="border-t">
                            <td data-label="Название" class="py-2 text-center" x-text="yacht.name"></td>
                            <td data-label="Парус №" class="py-2 text-center" x-text="yacht.vfps_number"></td>
                            <td data-label="Владелец" class="py-2 text-center" x-text="yacht.owner_name || '—'"></td>
                            <td data-label="Балл ORC" class="py-2 text-center">—</td>
                            <td data-label="Сертификат ORC" class="py-2 text-center">
                                <template x-if="yacht.has_orc_cert">
                                    <span class="">Есть</span>
                                </template>
                                <template x-if="!yacht.has_orc_cert">
                                    <span class="">Нет</span>
                                </template>
                            </td>
                            <td class="py-2 text-center">
                                <a href="#" @click.prevent="openYachtModal(yacht)" class="text-[#2D92CE] font-semibold hover:underline">Подробнее</a>
                            </td>
                        </tr>
                        </template>
                        <template x-if="filteredYachts.length === 0">
                        <tr>
                            <td colspan="6" class="py-8 text-center text-gray-500">Яхты не найдены</td>
                        </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($yachts->hasPages())
                @php
                    $currentPage = $yachts->currentPage();
                    $lastPage = $yachts->lastPage();
                    $pages = [];

                    // Always show first page, last page, and pages around current
                    foreach (range(1, $lastPage) as $p) {
                        if ($p <= 1 || $p >= $lastPage || ($p >= $currentPage - 2 && $p <= $currentPage + 2)) {
                            $pages[] = $p;
                        }
                    }
                    $pages = array_unique($pages);
                    sort($pages);
                @endphp
                <div class="pagination flex justify-center mt-10 gap-2">
                    @if($yachts->onFirstPage())
                        <button class="back opacity-50 cursor-default"></button>
                    @else
                        <a href="{{ $yachts->previousPageUrl() }}" class="back"></a>
                    @endif

                    @php $prevPage = null; @endphp
                    @foreach($pages as $page)
                        @if($prevPage !== null && $page - $prevPage > 1)
                            <span class="px-4 py-2 text-lg text-[#2E325C]">...</span>
                        @endif
                        @if($page == $currentPage)
                            <button class="px-4 py-2 text-lg text-[#2D92CE] font-medium border-b border-[#2D92CE]">{{ $page }}</button>
                        @else
                            <a href="{{ $yachts->url($page) }}" class="px-4 py-2 text-lg text-[#2E325C]">{{ $page }}</a>
                        @endif
                        @php $prevPage = $page; @endphp
                    @endforeach

                    @if($yachts->hasMorePages())
                        <a href="{{ $yachts->nextPageUrl() }}" class="next"></a>
                    @else
                        <button class="next opacity-50 cursor-default"></button>
                    @endif
                </div>
            @endif
        </div>
    </section>

    {{-- Модальное окно для подробной информации о яхте --}}
    <div x-show="yacht_modal_open"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 team-modal">
        <div @click.away="yacht_modal_open = false" class="relative p-6 max-w-[1000px] max-h-[80vh] overflow-y-auto bg-white gap-6"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
        >
            <template x-if="selectedYacht">
            <div>
                <div class="bg-[#F8F8F8] p-6 mb-8">
                    <div class="flex mb-6 justify-between items-center">
                        <h3 class="text-3xl a-font" x-text="selectedYacht.name"></h3>
                        <div class="close">
                            <button @click="yacht_modal_open = false" class="text-2xl font-bold">{!! file_get_contents(public_path('images/icons/close.svg')) !!}</button>
                        </div>
                    </div>
                    <p class="mb-6" x-text="'Яхта «' + selectedYacht.name + '» зарегистрирована в Ассоциации Carter Pro и принимает участие в регатах сезона.'"></p>
                    <div class="flex gap-24 flex-col md:flex-row">
                        <table>
                            <tr>
                                <td class="font-semibold py-2 w-64">Парус №</td>
                                <td x-text="selectedYacht.vfps_number"></td>
                            </tr>
                            <tr>
                                <td class="font-semibold py-2 w-64">Класс:</td>
                                <td x-text="selectedYacht.class"></td>
                            </tr>
                            <tr>
                                <td class="font-semibold py-2 w-64">Участие в регатах:</td>
                                <td x-text="selectedYacht.participation_count + ' регат(ы)'"></td>
                            </tr>
                        </table>
                        <table>
                            <tr>
                                <td class="font-semibold py-2 w-64">Номер ГИМС:</td>
                                <td x-text="selectedYacht.reg_place"></td>
                            </tr>
                            <tr>
                                <td class="font-semibold py-2 w-64">Сертификат ORC:</td>
                                <td>
                                    <template x-if="selectedYacht.has_orc_cert">
                                        <span class="">Есть</span>
                                    </template>
                                    <template x-if="!selectedYacht.has_orc_cert">
                                        <span class="">Нет</span>
                                    </template>
                                </td>
                            </tr>
                            <tr>
                                <td class="font-semibold py-2 w-64">Балл ORC:</td>
                                <td>—</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="owner mb-8">
                    <h3 class="text-3xl a-font mb-6">Владелец яхты</h3>
                    <div class="flex flex-col md:flex-row gap-4 items-center bg-[#F8F8F8]">
                        <div class="owner__pic">
                            <img :src="selectedYacht.owner_photo" alt="">
                        </div>
                        <div class="owner__info">
                            <h3 class="text-2xl a-font mb-4" x-text="selectedYacht.owner_name"></h3>
                            <p class="mb-6" x-text="'Владелец яхты «' + selectedYacht.name + '»'"></p>
                            <ul class="space-y-3">
                                <li class="flex items-center gap-2">
                                    {!! file_get_contents(public_path('images/icons/phone.svg')) !!}
                                    <span x-text="selectedYacht.owner_phone"></span>
                                </li>
                                <li class="flex items-center gap-2">
                                    {!! file_get_contents(public_path('images/icons/mail.svg')) !!}
                                    <span x-text="selectedYacht.owner_email"></span>
                                </li>
                                <li class="flex items-center gap-2">
                                    {!! file_get_contents(public_path('images/icons/marker.svg')) !!}
                                    <span x-text="selectedYacht.owner_city"></span>
                                </li>
                            </ul>
                            <div class="mt-6"><span class="font-semibold">Яхта зарегистрирована:</span> <span x-text="selectedYacht.registered_at"></span></div>
                        </div>
                    </div>
                </div>

                <h3 class="text-3xl a-font mb-6">Параметры яхты</h3>
                <div class="overflow-y-auto relative custom-scroll mb-8">
                    <table class="w-full border-collapse bg-[#F8F8F8]">
                        <thead>
                            <tr class="text-2xl text-[#2E325C] border-b border-[#EAEAEA] sticky top-0 bg-[#F8F8F8]">
                                <th class="pt-2 pb-2 text-center font-medium a-font">Параметр</th>
                                <th class="pt-2 pb-2 text-center font-medium a-font">Значение</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y text-center font-medium">
                            <template x-for="(p, i) in selectedYacht.params" :key="i">
                                <tr class="hover:bg-white transition-colors border-b border-[#EAEAEA]">
                                    <td class="py-3" x-text="p.label"></td>
                                    <td class="py-3" x-text="p.value"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="mb-8" x-show="selectedYacht.documents.length > 0">
                    <h3 class="text-3xl a-font mb-6">Документы</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <template x-for="doc in selectedYacht.documents">
                            <!--<div class="bg-[#F8F8F8] flex gap-4 hover:shadow-md transition-shadow cursor-pointer p-4">
                                <div class="max-w-16">
                                    <img class="w-full" src="{{ asset('images/icons/pdf.png') }}" alt="">
                                </div>
                                <div>
                                    <div class="text-[#2E325C] text-lg font-semibold mb-4" x-text="doc.title"></div>
                                    <a :href="doc.url" class="text-[#2E325C] text-lg font-semibold flex gap-4 items-center">
                                        <img src="{{ asset('images/icons/download.svg') }}" alt="">
                                        <span>Скачать PDF</span>
                                    </a>
                                </div>
                            </div>-->
                            <div class="bg-[#F8F8F8] flex gap-4 hover:shadow-md transition-shadow cursor-pointer p-4">
                                <div class="max-w-10 md:max-w-16">
                                    <img class="w-full" src="{{ asset('images/icons/pdf.png') }}" alt="">
                                </div>
                                <div class="">
                                    <div class="text-[#2E325C] text-sm md:text-lg font-semibold mb-4" x-text='doc.title'></div>
                                    <div class="text-brand-gray-light font-medium mb-4 text-xs md:text-base" x-text='doc.desc'></div>
                                    <a x-bind:href="doc.path" class="text-[#2E325C] text-sm md:text-lg font-semibold flex gap-4 items-center"><img src="{{ asset('images/icons/download.svg') }}" alt=""> <span>Скачать PDF</span></a>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <script>
                    function participation_status(status) {
                        if (status === 'submitted') {
                            return '<div class="bg-[#A88C5833] px-3 py-1 text-[#A88C58] inline-block font-semibold max-w-[150px] w-full">Заявка подана</div>';
                        } else if (status === 'approved') {
                            return '<div class="bg-[#2D92CE33] px-3 py-1 text-[#2D92CE] inline-block font-semibold max-w-[150px] w-full">Участвует</div>';
                        } else if (status === 'completed' || status === 'finished') {
                            return '<div class="bg-[#15794933] px-3 py-1 text-[#157949] inline-block font-semibold max-w-[150px] w-full">Завершено</div>';
                        } else if (status === 'rejected') {
                            return '<div class="bg-[#F2484233] px-3 py-1 text-[#F24842] inline-block font-semibold max-w-[150px] w-full">Отклонена</div>';
                        } else {
                            return '<div class="bg-[#F8F8F8] px-3 py-1 text-[#666] inline-block font-semibold max-w-[150px] w-full">' + status + '</div>';
                        }
                    }
                </script>

                <div class="participation mb-8" x-show="selectedYacht.participation.length > 0">
                    <div class="participation-header flex items-center justify-between mb-6">
                        <h5 class="a-font text-3xl">Участие в регатах</h5>
                        <a href="#" class="text-[#2E325C] text-lg font-semibold gap-2 items-center hidden md:flex">
                            <img src="{{ asset('images/icons/download.svg') }}" alt="">
                            <span>Скачать историю участия</span>
                        </a>
                    </div>
                    <div class="overflow-y-auto max-h-[180px] relative custom-scroll responsive-table">
                        <table class="w-full border-collapse bg-[#F8F8F8]">
                            <thead>
                                <tr class="text-2xl text-[#2E325C] border-b border-[#EAEAEA] sticky top-0 bg-[#F8F8F8]">
                                    <th class="pt-2 pb-2 text-center font-medium a-font">Регата</th>
                                    <th class="pt-2 pb-2 text-center font-medium a-font">Дата</th>
                                    <th class="pt-2 pb-2 text-center font-medium a-font">Команда</th>
                                    <th class="pt-2 pb-2 text-center font-medium a-font">Статус</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-center font-medium">
                                <template x-for="(p, i) in selectedYacht.participation" :key="i">
                                    <tr class="hover:bg-white transition-colors border-b border-[#EAEAEA]">
                                        <td data-label="Регата" class="py-3" x-text="p.regatta"></td>
                                        <td data-label="Дата" class="py-3" x-text="p.date_event"></td>
                                        <td data-label="Команда" class="py-3" x-text="p.team"></td>
                                        <td data-label="Статус" class="py-3" x-html="participation_status(p.status)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        <a href="#" class="text-[#2E325C] text-lg font-semibold gap-2 block text-center items-center flex md:hidden">
                            <img src="{{ asset('images/icons/download.svg') }}" alt="">
                            <span>Скачать историю участия</span>
                        </a>
                    </div>
                </div>
            </div>
            </template>
        </div>
    </div>

</main>

<x-feedback-section>

</x-feedback-section>
</x-public-layout>
