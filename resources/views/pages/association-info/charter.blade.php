<x-public-layout>
    
<x-breadcrumbs_page title="Устав Ассоциации">
</x-breadcrumbs_page>
<x-hero-section title="Устав Ассоциации"
desc="Основной документ, регулирующий деятельность Ассоциации, права и обязанности участников, а также порядок проведения соревнований." 
bgImage="{{ asset('images/bg/charter.png') }}"
>
    
</x-hero-section>
{{-- ===== Документы ассоциации ===== --}}
<section class="py-10">
    <div class="container mx-auto pdf-list">
        <h2 class="section-title a-font mb-8">Документы Ассоциации</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <template x-data="
            {
                documents: [
                    {'title': 'Устав Ассоциации',
                     'desc': 'Актуальная редакция от 12 мая 2025',
                     'path': '#'
                    },
                    {'title': 'Правила участия',
                     'desc': 'Актуальная редакция от 12 мая 2025',
                     'path': '#'
                    },
                    {'title': 'Решения общего собрания',
                     'desc': 'Актуальная редакция от 12 мая 2025',
                     'path': '#'
                    },
                    {'title': 'Устав Ассоциации',
                     'desc': 'Актуальная редакция от 12 мая 2025',
                     'path': '#'
                    },
                    {'title': 'Правила участия',
                     'desc': 'Актуальная редакция от 12 мая 2025',
                     'path': '#'
                    },
                    {'title': 'Решения общего собрания',
                     'desc': 'Актуальная редакция от 12 мая 2025',
                     'path': '#'
                    },
                ]
            }
            " x-for="doc in documents">
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
</section>
<section class="py-10 bg-white">
    <div class="container mx-auto md:py-0">
        <h2 class="section-title a-font text-[#2E325C] mb-8">Основные положения устава</h2>

        <div class="text-brand-gray font-medium text-sm md:text-base">
            <div class="md:mb-8 mb-4">
                <h4 class="a-font md:text-2xl text-[#2E325C] md:mb-6 mb-3 text-lg">01. Общие положения</h4>
                <p>Ассоциация CarterPro является добровольным объединением владельцев и экипажей яхт класса Carter 30, созданным с целью развития парусного спорта и организации соревнований.</p>
            </div>
            <div class="md:mb-8 mb-4">
                <h4 class="a-font md:text-2xl text-[#2E325C] md:mb-6 mb-3 text-lg">02. Цели и задачи Ассоциации</h4>
                <ul class="list-disc pl-6 space-y-4">
                    <li>развитие класса Carter 30</li>
                    <li>организация и проведение регат</li>
                    <li>формирование сообщества участников</li>
                    <li>популяризация парусного спорта</li>
                </ul>
            </div>
            <div class="md:mb-8 mb-4">
                <h4 class="a-font md:text-2xl text-[#2E325C] md:mb-6 mb-3 text-lg">03. Членство в Ассоциации</h4>
                <p class="mb-4">Членами Ассоциации могут быть:</p>
                <ul class="list-disc pl-6 space-y-4">
                    <li>владельцы яхт</li>
                    <li>участники экипажей</li>
                    <li>иные заинтересованные лица</li>
                </ul>
            </div>
            <div class="md:mb-8 mb-4">
                <h4 class="a-font md:text-2xl text-[#2E325C] md:mb-6 mb-3 text-lg">04. Права и обязанности участников</h4>
                <p class="mb-4">Члены Ассоциации имеют право:</p>
                <ul class="list-disc pl-6 space-y-4 mb-6">
                    <li>участвовать в соревнованиях</li>
                    <li>получать информацию</li>
                    <li>участвовать в управлении</li>
                </ul>
                <p class="mb-4">Обязаны:</p>
                <ul class="list-disc pl-6 space-y-4">
                    <li>соблюдать регламент</li>
                    <li>выполнять решения Ассоциации</li>
                </ul>
            </div>
            <div class="md:mb-8 mb-4">
                <h4 class="a-font md:text-2xl text-[#2E325C] md:mb-6 mb-3 text-lg">05. Руководящие органы</h4>
                <p class="mb-4">Управление Ассоциацией осуществляется:</p>
                <ul class="list-disc pl-6 space-y-4">
                    <li>руководством</li>
                    <li>попечительским советом</li>
                    <li>общим собранием</li>
                </ul>
            </div>
            <div class="md:mb-8 mb-4">
                <h4 class="a-font md:text-2xl text-[#2E325C] md:mb-6 mb-3 text-lg">06. Организация соревнований</h4>
                <p>Ассоциация организует и проводит регаты в соответствии с установленными правилами и регламентами.</p>
            </div>
            <div class="mb-8">
                <h4 class="a-font md:text-2xl text-[#2E325C] md:mb-6 mb-3 text-lg">07. Финансовая деятельность</h4>
                <p class="mb-4">Финансирование осуществляется за счёт:</p>
                <ul class="list-disc pl-6 space-y-4">
                    <li>членских взносов</li>
                    <li>партнёрской поддержки</li>
                    <li>иных источников</li>
                </ul>
            </div>
            <div class="md:mb-8 mb-4">
                <h4 class="a-font md:text-2xl text-[#2E325C] md:mb-6 mb-3 text-lg">08. Заключительные положения</h4>
                <p>Настоящий устав вступает в силу с момента утверждения и действует до внесения изменений.</p>
            </div>
        </div>
    </div>
    

</section>
<x-feedback-section>
    
</x-feedback-section>
</x-public-layout>