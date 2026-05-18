<x-public-layout>
<x-breadcrumbs_page title="Технический регламент яхт">
</x-breadcrumbs_page>
<x-hero-section title="Технический регламент яхт"
desc="Правила и требования к яхтам класса Carter 30, участвующим в регатах Ассоциации." 
bgImage="{{ asset('images/bg/regulations.png') }}"
>
    
</x-hero-section>
{{-- ===== Документы регламента ===== --}}
<section class="py-10">
    <div class="max-w-(--breakpoint-2xl) mx-auto pdf-list">
        <h2 class="section-title a-font mb-8">Документы регламента</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-6">
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
                <div class="bg-[#F8F8F8] flex gap-4 hover:shadow-md transition-shadow cursor-pointer p-4">
                    <div class="max-w-16">
                        <img class="w-full" src="{{ asset('images/icons/pdf.png') }}" alt="">
                    </div>
                    <div class="">
                        <div class="text-[#2E325C] text-lg font-semibold mb-4" x-text='doc.title'></div>
                        <div class="text-brand-gray-light font-medium mb-4" x-text='doc.desc'></div>
                        <a x-bind:href="doc.path" class="text-[#2E325C] text-lg font-semibold flex gap-4 items-center"><img src="{{ asset('images/icons/download.svg') }}" alt=""> <span>Скачать PDF</span></a>
                    </div>
                </div>
            </template>
        </div>
    </div>
</section>
<section class="py-10 bg-white">
    <div class="max-w-(--breakpoint-2xl) mx-auto">
        <h2 class="section-title a-font text-[#2E325C] mb-8">Основные положения регламента</h2>

        <div class="text-brand-gray font-medium">
            <div class="mb-8">
                <h4 class="a-font text-2xl text-[#2E325C] mb-4">01. Общие принципы</h4>
                <p>Ассоциация CarterPro осуществляет свою деятельность на основе открытости, честности и соблюдения установленных правил.</p>
            </div>
            <div class="mb-8">
                <h4 class="a-font text-2xl text-[#2E325C] mb-4">02. Развитие класса</h4>
                <p>Ассоциация способствует развитию класса яхт Carter 30, поддерживает участие команд в соревнованиях и популяризацию парусного спорта.</p>
            </div>
            <div class="mb-8">
                <h4 class="a-font text-2xl text-[#2E325C] mb-4">03. Организация соревнований</h4>
                <p>Все регаты проводятся в соответствии с утверждёнными регламентами и международными стандартами парусного спорта.</p>
            </div>
            <div class="mb-8">
                <h4 class="a-font text-2xl text-[#2E325C] mb-4">04. Взаимодействие участников</h4>
                <p>Ассоциация обеспечивает равные условия для всех участников и поддерживает конструктивное взаимодействие внутри сообщества.</p>
            </div>
            <div class="mb-8">
                <h4 class="a-font text-2xl text-[#2E325C] mb-4">05. Партнёрство</h4>
                <p>Управление Ассоциацией осуществляется:</p>
            </div>
            <div class="mb-8">
                <h4 class="a-font text-2xl text-[#2E325C] mb-4">06. Прозрачность деятельности</h4>
                <p>Ассоциация развивает партнёрские отношения и сотрудничество с организациями, поддерживающими парусный спорт.</p>
            </div>
            <div class="mb-8">
                <h4 class="a-font text-2xl text-[#2E325C] mb-4">07. Ответственность</h4>
                <p>Решения Ассоциации принимаются открыто и доводятся до сведения участников в установленном порядке.</p>
            </div>
            <div class="mb-8">
                <h4 class="a-font text-2xl text-[#2E325C] mb-4">08. Заключительные положения</h4>
                <p>Ассоциация и её участники обязаны соблюдать принятые правила<br> 
                    и нести ответственность за свои действия в рамках деятельности сообщества.</p>
            </div>
        </div>
    </div>
</section>
<section class="py-10 bg-white">
<div class="max-w-(--breakpoint-2xl) mx-auto bg-[#F8F8F8] flex flex-col md:flex-row gap-10 items-center">
    <div class="info px-4 sm:px-6 lg:px-8">
        <h2 class="section-title a-font text-[#2E325C] text-5xl mb-8">Допуск яхты к соревнованиям</h2>
        <p class="text-brand-gray font-medium text-lg mb-8">Для участия в регате яхта должна быть зарегистрирована в базе Ассоциации и соответствовать техническому регламенту.</p>
        <button class="mt-6 bg-[#2D92CE] text-white py-2 px-6 hover:bg-[#0074CC] transition-colors text-lg font-semibold">
        Подать заявку →
        </button>
    </div>
    <div class="pic">
        <img class="w-full" src="{{ asset('images/rules/rules_pic_1.png') }}" alt="">
    </div>
</div>
</section>
<x-feedback-section>
    
</x-feedback-section>
</x-public-layout>