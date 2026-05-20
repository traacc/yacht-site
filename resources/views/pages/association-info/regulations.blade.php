<x-public-layout>
<x-breadcrumbs_page title="Технический регламент яхт">
</x-breadcrumbs_page>
<x-hero-section title="Технический регламент яхт"
desc="Правила и требования к яхтам класса Carter 30, участвующим в регатах Ассоциации." 
bgImage="{{ asset('images/bg/regulations.png') }}"
>
    
</x-hero-section>
{{-- ===== Документы регламента ===== --}}
<section class="py-10 px-4 md:px-2">
    <div class="max-w-(--breakpoint-2xl) mx-auto px-4 sm:px-6 lg:px-8 pdf-list">
        <h2 class="section-title a-font mb-8">Документы регламента</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <template x-data="
            {
                documents: [
                    {'title': 'Технический регламент яхт',
                     'desc': 'Актуальная редакция от 12 мая 2025',
                     'path': '#'
                    },
                    {'title': 'Приложение к регламенту',
                     'desc': 'Актуальная редакция от 12 мая 2025',
                     'path': '#'
                    },
                    {'title': 'Форма проверки яхты',
                     'desc': 'Актуальная редакция от 12 мая 2025',
                     'path': '#'
                    },
                    {'title': 'Технический регламент яхт',
                     'desc': 'Актуальная редакция от 12 мая 2025',
                     'path': '#'
                    },
                    {'title': 'Приложение к регламенту',
                     'desc': 'Актуальная редакция от 12 мая 2025',
                     'path': '#'
                    },
                    {'title': 'Форма проверки яхты',
                     'desc': 'Актуальная редакция от 12 мая 2025',
                     'path': '#'
                    },
                ]
            }
            " x-for="doc in documents">
                <div class="bg-[#F8F8F8] flex gap-4 hover:shadow-md transition-shadow cursor-pointer p-4 ">
                    <div class="max-w-16">
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
</section>
<section class="py-10 bg-white px-4 sm:px-6 lg:px-8 text-sm md:text-base">
    <div class="max-w-(--breakpoint-2xl) mx-auto">
        <h2 class="section-title a-font text-[#2E325C] mb-8">Основные положения регламента</h2>

        <div class="text-brand-gray font-medium">
            <div class="mb-8">
                <h4 class="a-font md:text-2xl text-[#2E325C] md:mb-6 mb-3">01. Общие требования</h4>
                <p>Яхты, участвующие в соревнованиях Ассоциации, должны соответствовать требованиям класса Carter 30 и действующим правилам допуска.</p>
            </div>
            <div class="mb-8">
                <h4 class="a-font md:text-2xl text-[#2E325C] md:mb-6 mb-3">02. Идентификация яхты</h4>
                <p>Для участия указывается название яхты, номер ВФПС, парусный номер и данные владельца или управляющего.</p>
            </div>
            <div class="mb-8">
                <h4 class="a-font md:text-2xl text-[#2E325C] md:mb-6 mb-3">03. Технические параметры</h4>
                <p>Регламент определяет допустимые параметры корпуса, такелажа, парусов и оборудования яхты.</p>
            </div>
            <div class="mb-8">
                <h4 class="a-font md:text-2xl text-[#2E325C] md:mb-6 mb-3">04. Сертификаты и документы</h4>
                <p>При наличии сертификата ORC данные яхты могут использоваться для подтверждения технических характеристик.</p>
            </div>
            <div class="mb-8">
                <h4 class="a-font md:text-2xl text-[#2E325C] md:mb-6 mb-3">05. Проверка перед соревнованиями</h4>
                <p>Перед участием в регате яхта может пройти проверку на соответствие требованиям регламента.</p>
            </div>
            <div class="mb-8">
                <h4 class="a-font md:text-2xl text-[#2E325C] md:mb-6 mb-3">06. Ответственность владельца</h4>
                <p>Владелец или управляющий яхты несёт ответственность за достоверность предоставленных данных и техническое состояние яхты.</p>
            </div>
        </div>
    </div>
</section>
<section class="py-10 bg-white px-4 sm:px-6 lg:px-8">
<div class="max-w-(--breakpoint-2xl) mx-auto bg-[#F8F8F8] flex flex-col md:flex-row gap-10 items-center">
    <div class="info px-4 sm:px-6 lg:px-8">
        <h2 class="section-title a-font text-[#2E325C] md:text-5xl text-3xl mb-8">Допуск яхты к соревнованиям</h2>
        <p class="text-brand-gray font-medium md:text-lg text-sm mb-8">Для участия в регате яхта должна быть зарегистрирована в базе Ассоциации и соответствовать техническому регламенту.</p>
        <button @click="isRequestModalOpen=true" class="mt-6 bg-[#2D92CE] text-white py-2 px-6 hover:bg-[#0074CC] transition-colors md:text-lg text-sm font-semibold w-full max-w-[300px]">
        Подать заявку →
        </button>
    </div>
    <div class="pic">
        <img class="w-full" src="{{ asset('images/regulation.png') }}" alt="">
    </div>
</div>
</section>
<x-feedback-section>
    
</x-feedback-section>
</x-public-layout>