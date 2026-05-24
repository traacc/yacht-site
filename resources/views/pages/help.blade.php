<x-public-layout>
<x-breadcrumbs_page title="Помощь">
</x-breadcrumbs_page>
<main x-data="{
    help_modal_open: false,
    activeCategory: 'electric',
    categories: {
        electric: {
            title: 'Электрика и механика на яхте',
            items: [
                { title: 'Проверка электросистем перед регатой', desc: 'Диагностика и обслуживание электрических систем яхты.', name: 'Игорь Скалин' },
                { title: 'Ремонт подвесных моторов', desc: 'Обслуживание и ремонт лодочных моторов.', name: 'Алексей Петров' }
            ]
        },
        construct: {
            title: 'Конструктив, отделка, косметика',
            items: [
                { title: 'Полировка корпуса', desc: 'Восстановление блеска и защита гелькоута.', name: 'Марина Иванова' }
            ]
        },
        rigging: {
            title: 'Такелажные работы',
            items: [
                { title: 'Замена стоячего такелажа', desc: 'Профессиональная замена вант и штагов.', name: 'Дмитрий Сидоров' }
            ]
        },
        sails: {
            title: 'Работа с парусами и парусные мастера',
            items: [
                { title: 'Ремонт парусов', desc: 'Ремонт разрывов и замена люверсов.', name: 'Елена Кузнецова' }
            ]
        }
    }
}" class="main">
    <section class="md:py-12 py-4 reggata-list">
        <div class="container mx-auto">
            <h2 class="section-title a-font text-5xl">Помощь</h2>
        </div>
    </section>
    <section class="flex md:flex-row flex-col gap-8 container mx-auto">
        <div class="p-4 bg-[#F8F8F8] max-w-[340px] w-full">
            <template x-for="(cat, key) in categories" :key="key">
                <div @click="activeCategory = key"
                     :class="activeCategory === key ? 'bg-white border-l-2 border-l-[#2D92CE]' : ''"
                     class="font-medium text-lg p-4 cursor-pointer hover:bg-white transition-colors"
                     x-text="cat.title"></div>
            </template>
        </div>
        <div class="mb-8 w-full">
            <h3 class="section-title a-font text-5xl mb-8" x-text="categories[activeCategory].title"></h3>
            <div class="searchbar flex flex-col md:flex-row gap-4 mb-6">
                <input class="w-full border-0 py-4 pl-12 bg-[#F8F8F8] focus:outline-hidden " type="text" placeholder="Поиск по объявлению">
            </div>
            <div class="help__list w-full">
                <template x-for="(item, index) in categories[activeCategory].items" :key="index">
                    <div class="help__item flex md:flex-row flex-col justify-between gap-6 bg-[#F8F8F8] p-4 mb-4">
                        <div class="pr-6 max-w-[620px]">
                            <h4 @click="help_modal_open = true" class="font-semibold text-[#2E325C] text-lg mb-4 cursor-pointer" x-text="item.title"></h4>
                            <p class="text-[#2E325C]" x-text="item.desc"></p>
                        </div>
                        <div class="pl-6 pr-6 border-l border-l-[#EAEAEA]">
                            <h3 class="text-lg font-semibold mb-4" x-text="item.name"></h3>
                            <ul class="space-y-2 text-sm">
                                <li class="flex items-center gap-2">
                                    {!! file_get_contents(public_path('images/icons/phone.svg')) !!}
                                    <span x-text="item.phone || '+7 (000) 000-00-00'"></span>
                                </li>
                                <li class="flex items-center gap-2">
                                    {!! file_get_contents(public_path('images/icons/mail.svg')) !!}
                                    <span x-text="item.email || 'contact@mail.ru'"></span>
                                </li>
                            </ul>
                            <button @click="help_modal_open = true" class="mt-6 bg-[#2D92CE] w-full text-white py-2 px-6 hover:bg-[#0074CC] transition-colors text-lg font-semibold">
                                Связаться
                            </button>
                        </div>
                    </div>
                </template>
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
                    <button @click="help_modal_open = false" class="text-2xl font-bold">{!! file_get_contents(public_path('images/icons/close.svg')) !!}</button>
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
                <h5 class=" a-font text-lg md:text-3xl mb-6">Примеры работ</h5>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    @foreach(range(1, 4) as $item)
                    <div class="card bg-[#F8F8F8]">
                        <img src="{{ asset('images/job.png') }}" alt="">
                    </div>
                    @endforeach
                </div>
            </div>
            <div class="bg-[#F8F8F8] p-3 md:p-4">
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
                        <a href="mailto:info@carter-pro.ru">info@carter-pro.ru</a>
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