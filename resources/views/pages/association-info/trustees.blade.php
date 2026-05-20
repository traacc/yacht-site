<x-public-layout>
<x-breadcrumbs_page title="Попечительский совет Ассоциации">
</x-breadcrumbs_page>
<x-hero-section title="Попечительский совет Ассоциации"
desc="Попечительский совет объединяет представителей сообщества и партнёров, оказывающих поддержку развитию Ассоциации и парусного спорта." 
bgImage="{{ asset('images/bg/trustees.png') }}"
>
    
</x-hero-section>
{{-- ===== Попечительский совет ===== --}}
<main class="main pb-12 px-4 md:px-2" x-data="{
    open: false,
    selectedPerson: null,
    people: [
        {
            id: 1,
            name: 'Алексей Смирнов',
            position: 'Председатель попечительского совета',
            image: 'person_1.png',
            description: 'Алексей Смирнов имеет многолетний опыт руководства и стратегического планирования. В попечительском совете отвечает за общее направление развития Ассоциации, привлечение партнёров и обеспечение ресурсной базы для реализации программ.',
            responsibilities: [
                'стратегическое развитие Ассоциации',
                'привлечение партнёров и спонсоров',
                'контроль целевого использования средств',
                'обеспечение ресурсной базы программ',
                'взаимодействие с руководством Ассоциации'
            ]
        },
        {
            id: 2,
            name: 'Дмитрий Воронов',
            position: 'Член попечительского совета',
            image: 'person_2.png',
            description: 'Дмитрий Воронов обладает опытом в управлении проектами и организации мероприятий. В попечительском совете участвует в развитии инфраструктуры и поддержке соревновательной деятельности Ассоциации.',
            responsibilities: [
                'развитие инфраструктуры яхт-клуба',
                'поддержка соревновательной деятельности',
                'участие в разработке программ развития',
                'содействие в организации мероприятий'
            ]
        },
        {
            id: 3,
            name: 'Максим Жолудов',
            position: 'Член попечительского совета',
            image: 'person_3.png',
            description: 'Максим Жолудов имеет значительный опыт в бизнесе и управлении. В попечительском совете способствует укреплению финансовой устойчивости Ассоциации и расширению партнёрской сети.',
            responsibilities: [
                'укрепление финансовой устойчивости',
                'расширение партнёрской сети',
                'консультирование по вопросам развития',
                'поддержка молодёжных программ'
            ]
        },
        {
            id: 4,
            name: 'Сергей Морозов',
            position: 'Член попечительского совета',
            image: 'person_4.png',
            description: 'Сергей Морозов обладает опытом в организации спортивных проектов и работе с сообществами. В попечительском совете участвует в развитии парусного спорта и поддержке инициатив участников.',
            responsibilities: [
                'развитие парусного спорта в регионе',
                'поддержка клубных инициатив',
                'организация социальных проектов',
                'взаимодействие с яхтенными сообществами'
            ]
        },
        {
            id: 5,
            name: 'Анна Капитонова',
            position: 'Член попечительского совета',
            image: 'person_5.png',
            description: 'Анна Капитонова имеет опыт в коммуникациях и организации культурно-массовых мероприятий. В попечительском совете отвечает за развитие имиджа Ассоциации и привлечение внимания к парусному спорту.',
            responsibilities: [
                'развитие имиджа Ассоциации',
                'организация публичных мероприятий',
                'привлечение внимания к парусному спорту',
                'взаимодействие со СМИ и общественностью'
            ]
        },
        {
            id: 6,
            name: 'Игорь Чупров',
            position: 'Член попечительского совета',
            image: 'person_6.png',
            description: 'Игорь Чупров обладает компетенциями в области права и финансов. В попечительском совете участвует в правовом сопровождении деятельности и контроле соблюдения уставных норм.',
            responsibilities: [
                'правовое сопровождение деятельности',
                'контроль соблюдения уставных норм',
                'аудит финансовой отчётности',
                'консультирование по нормативным вопросам'
            ]
        }
    ]
}">
    <section class="py-10">
        <div class="max-w-(--breakpoint-2xl) mx-auto">
            <h2 class="section-title a-font mb-8">Попечительский совет</h2>
            <div class="list grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">

                <template x-for="person in people" :key="person.id">
                    <div class="card bg-[#F8F8F8]">
                        <img :src="'{{ asset('images/trustees') }}/' + person.image" :alt="person.name">
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
                <img class="max-w-full h-full object-cover" :src="'{{ asset('images/trustees') }}/' + (selectedPerson ? selectedPerson.image : '')" :alt="selectedPerson?.name">
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
                    <img class="max-w-full" :src="'{{ asset('images/trustees') }}/' + (selectedPerson ? selectedPerson.image : '')" :alt="selectedPerson?.name">
                </div>
                <h5 class="font-semibold text-[#2E325C] mt-4 md:mt-0 md:mb-6">О попечителе</h5>
                <p class="text-brand-gray mb-6" x-text="selectedPerson?.description"></p>
                <h5 class="font-semibold text-[#2E325C] mb-6">Зоны ответственности в Ассоциации</h5>
                <ul class="text-brand-gray list-disc pl-6 space-y-3.5">
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