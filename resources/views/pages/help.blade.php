<x-public-layout>
<x-breadcrumbs_page title="Помощь">
</x-breadcrumbs_page>
<main x-data="{help_modal_open: false}" class="main">
    <section class="py-12 reggata-list">
        <div class="max-w-(--breakpoint-2xl) mx-auto">
            <h2 class="section-title a-font text-5xl">Помощь</h2>
        </div>
    </section>
    <section class="flex gap-8 max-w-(--breakpoint-2xl) mx-auto">
        <div class="p-4 bg-[#F8F8F8] max-w-[340px]">
            <div class="bg-white border-l-2 font-medium text-lg border-l-[#2D92CE] p-4">Электрика и механика на яхте</div>
            <div class="font-medium text-lg  p-4">Конструктив, отделка, косметика</div>
            <div class="font-medium text-lg p-4">Такелажные работы</div>
            <div class="font-medium text-lg p-4">Паруса и парусные мастера</div>
        </div>
        <div class="mb-8">
            <h3 class="section-title a-font text-5xl mb-8">Электрика и механика на яхте</h3>
            <div class="searchbar flex flex-col md:flex-row gap-4 mb-6">
                <input class="w-full border-0 py-4 pl-12 bg-[#F8F8F8] focus:outline-hidden " type="text" placeholder="Поиск по объявлению">
            </div>
            <div class="help__list w-full">
                <div class="help__item flex gap-6 bg-[#F8F8F8] p-4 mb-4">
                    <div class="pr-6 max-w-[620px]">
                        <h4 class="font-semibold text-[#2E325C] text-lg mb-4">Проверка электросистем перед регатой</h4>
                        <p class="text-[#2E325C]">Диагностика и обслуживание электрических систем яхты перед тренировками и регатами. Проверка аккумуляторов, навигационного оборудования, освещения и бортовой сети. Подготовка яхты к безопасному выходу на воду.</p>
                    </div>
                    <div class="pl-6 border-l border-l-[#EAEAEA]">
                        <h3 class="text-lg font-semibold mb-4">Игорь Скалин</h3>
                        <ul class="space-y-5">
                            <li class="flex items-center gap-2">
                                {!! file_get_contents(public_path('images/icons/phone.svg')) !!}
                                +7 (000) 000-60-00
                            </li>
                            <li class="flex items-center gap-2">
                                {!! file_get_contents(public_path('images/icons/mail.svg')) !!}
                                contact@mail.ru
                            </li>
                            <li class="flex items-center gap-2">
                                {!! file_get_contents(public_path('images/icons/marker.svg')) !!}
                                Москва
                            </li>
                        </ul>
                        <button @click="help_modal_open = true" class="mt-6 bg-[#2D92CE] w-full text-white py-2 px-6 hover:bg-[#0074CC] transition-colors text-lg font-semibold">
                            Связаться
                        </button>
                    </div>
                </div>
                <div class="help__item flex gap-6 bg-[#F8F8F8] p-4">
                    <div class="pr-6 max-w-[620px]">
                        <h4 class="font-semibold text-[#2E325C] text-lg mb-4">Проверка электросистем перед регатой</h4>
                        <p class="text-[#2E325C]">Диагностика и обслуживание электрических систем яхты перед тренировками и регатами. Проверка аккумуляторов, навигационного оборудования, освещения и бортовой сети. Подготовка яхты к безопасному выходу на воду.</p>
                    </div>
                    <div class="pl-6 border-l border-l-[#EAEAEA]">
                        <h3 class="text-lg font-semibold mb-4">Игорь Скалин</h3>
                        <ul class="space-y-5">
                            <li class="flex items-center gap-2">
                                {!! file_get_contents(public_path('images/icons/phone.svg')) !!}
                                +7 (000) 000-60-00
                            </li>
                            <li class="flex items-center gap-2">
                                {!! file_get_contents(public_path('images/icons/mail.svg')) !!}
                                contact@mail.ru
                            </li>
                            <li class="flex items-center gap-2">
                                {!! file_get_contents(public_path('images/icons/marker.svg')) !!}
                                Москва
                            </li>
                        </ul>
                        <button @click="help_modal_open = true"  class="mt-6 bg-[#2D92CE] w-full text-white py-2 px-6 hover:bg-[#0074CC] transition-colors text-lg font-semibold">
                            Связаться
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <div x-show="help_modal_open" 
        x-cloak 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 team-modal">
        <!-- Модальное окно для подробной информации о команде -->
        <div @click.away="help_modal_open = false"  class="relative p-6 max-w-[800px] w-full max-h-[80vh] overflow-y-auto bg-white gap-6"
            
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
        >
            <div class="flex mb-6 justify-between items-center">
                <h3 class="text-3xl a-font max-w-[720px]">Проверка электросистем перед регатой</h3>
                <div class="close">
                    <button @click="yacht_modal_open = false" class="text-2xl font-bold">{!! file_get_contents(public_path('images/icons/close.svg')) !!}</button>
                </div>
            </div>
            <p class="mb-6">Проверка электросистем перед регатой</p>
            <p class="mb-4 font-semibold">Что входит в проверку</p>
            <ul class="list-disc pl-4 space-y-4 mb-6">
                <li>Проверка аккумуляторов</li>
                <li>Диагностика бортовой сети</li>
                <li>Проверка навигационного оборудования</li>
                <li>Освещение и электропроводка</li>
                <li>Проверка зарядных систем</li>
                <li>Поиск неисправностей</li>
            </ul>
            <div class="gallery mb-6">
                <h5 class=" a-font text-3xl mb-6">Примеры работ</h5>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    @foreach(range(1, 4) as $item)
                    <div class="card bg-[#F8F8F8]">
                        <img src="{{ asset('images/job.png') }}" alt="">
                    </div>
                    @endforeach
                </div>
            </div>
            <div class="">
                <h4 class="font-semibold text-lg mb-4">Информация о специалисте</h4>
                <p class="font-semibold text-lg mb-4">Игорь Скалин</p>
                <p class="font-medium mb-4">Электрик / механик яхт</p>
                <ul class="space-y-5">
                    <li class="flex items-center gap-2">
                        {!! file_get_contents(public_path('images/icons/phone.svg')) !!}
                        +7 (000) 000-60-00
                    </li>
                    <li class="flex items-center gap-2">
                        {!! file_get_contents(public_path('images/icons/mail.svg')) !!}
                        contact@mail.ru
                    </li>
                    <li class="flex items-center gap-2">
                        {!! file_get_contents(public_path('images/icons/marker.svg')) !!}
                        Москва
                    </li>
                </ul>
                <button class="mt-6 bg-[#2D92CE] w-full text-white py-2 px-6 hover:bg-[#0074CC] transition-colors text-lg font-semibold">
                    Связаться
                </button>
            </div>
        </div>
    </div>
</main>



<x-feedback-section>
    
</x-feedback-section>
</x-public-layout>