<x-public-layout>
<x-breadcrumbs_page title="Руководство Ассоциации">
</x-breadcrumbs_page>
<x-hero-section title="Руководство Ассоциации"
desc="Команда, отвечающая за развитие Ассоциации, организацию соревнований и управление деятельностью сообщества." 
bgImage="{{ asset('images/bg/management.png') }}"
>
    
</x-hero-section>
{{-- ===== Руководители ===== --}}
<main class="main pb-12 px-4 md:px-2" x-data="{
    open: false,
    selectedPerson: null,
    people: [
        {
            id: 1,
            name: 'Игорь Скалин',
            position: 'Президент Ассоциации',
            image: 'person_1.png',
            description: 'Игорь Скалин имеет опыт участия в парусных соревнованиях и организационной работы в спортивных проектах. В Ассоциации отвечает за общее направление развития, коммуникацию с участниками и формирование устойчивой структуры сезона.',
            responsibilities: [
                'стратегическое развитие Ассоциации яхт',
                'координация календаря регат',
                'взаимодействие с партнёрами и спонсорами',
                'утверждение ключевых организационных решений',
                'развитие сообщества владельцев и экипажей',
                'контроль работы руководящего состава'
            ]
        },
        {
            id: 2,
            name: 'Владимир Капитонов',
            position: 'Вице-президент',
            image: 'person_2.png',
            description: 'Владимир Капитонов обладает многолетним опытом управления спортивными проектами и развития парусного спорта. В Ассоциации отвечает за координацию работы комитетов и реализацию стратегических инициатив.',
            responsibilities: [
                'курирование работы комитетов и рабочих групп',
                'координация взаимодействия между регионами',
                'организация партнёрских программ',
                'контроль исполнения стратегического плана',
                'содействие развитию молодёжного парусного спорта'
            ]
        },
        {
            id: 3,
            name: 'Дмитрий Леонтьев',
            position: 'Технический директор',
            image: 'person_3.png',
            description: 'Дмитрий Леонтьев имеет профессиональный опыт в управлении техническими проектами и организации спортивной инфраструктуры. В Ассоциации отвечает за техническое обеспечение соревнований и развитие материально-технической базы.',
            responsibilities: [
                'техническое обеспечение регат и соревнований',
                'развитие инфраструктуры яхт-клуба',
                'контроль состояния флота и оборудования',
                'внедрение технологических решений в управлении соревнованиями',
                'обеспечение безопасности проведения мероприятий'
            ]
        },
        {
            id: 4,
            name: 'Александр Пульков',
            position: 'Спортивный директор',
            image: 'person_4.png',
            description: 'Александр Пульков имеет большой опыт организации и проведения парусных регат различного уровня. В Ассоциации отвечает за спортивную составляющую сезона, подготовку и проведение соревнований.',
            responsibilities: [
                'формирование спортивного календаря сезона',
                'организация и проведение регат',
                'контроль соблюдения правил парусных гонок',
                'взаимодействие с гоночными комитетами',
                'развитие спортивных классов яхт',
                'подготовка и повышение квалификации судейского корпуса'
            ]
        },
        {
            id: 5,
            name: 'Анна Капитонова',
            position: 'Руководитель по работе с участниками',
            image: 'person_5.png',
            description: 'Анна Капитонова имеет опыт в организации мероприятий и коммуникации с участниками спортивных сообществ. В Ассоциации отвечает за привлечение новых участников и сопровождение команд на всех этапах сезона.',
            responsibilities: [
                'привлечение новых участников и команд',
                'сопровождение регистрации на регаты',
                'организация информационной поддержки участников',
                'обработка обратной связи и предложений',
                'создание комфортной среды для участников соревнований',
                'развитие программ лояльности и поощрений'
            ]
        },
        {
            id: 6,
            name: 'Андрей Чупров',
            position: 'Финансовый директор',
            image: 'person_6.png',
            description: 'Андрей Чупров обладает профессиональным опытом в финансовом управлении и бюджетировании проектов. В Ассоциации отвечает за финансовое планирование, контроль бюджета и обеспечение финансовой устойчивости организации.',
            responsibilities: [
                'финансовое планирование и бюджетирование',
                'контроль расходов и целевого использования средств',
                'формирование финансовой отчётности',
                'взаимодействие с партнёрами по финансовым вопросам',
                'оптимизация финансовых процессов Ассоциации',
                'обеспечение прозрачности финансовой деятельности'
            ]
        },
        {
            id: 7,
            name: 'Сергей Морозов',
            position: 'Исполнительный директор',
            image: 'person_7.png',
            description: 'Сергей Морозов имеет значительный опыт в операционном управлении и организации рабочих процессов. В Ассоциации отвечает за текущую деятельность, выполнение операционных задач и координацию работы исполнительных органов.',
            responsibilities: [
                'операционное управление деятельностью Ассоциации',
                'координация работы исполнительных органов',
                'контроль исполнения решений руководства',
                'организация документооборота и делопроизводства',
                'взаимодействие с государственными органами и организациями',
                'обеспечение бесперебойной работы административного аппарата'
            ]
        },
        {
            id: 8,
            name: 'Екатерина Воронова',
            position: 'Руководитель по коммуникациям',
            image: 'person_8.png',
            description: 'Екатерина Воронова имеет опыт в сфере PR и коммуникаций, а также организации информационного сопровождения проектов. В Ассоциации отвечает за внешние и внутренние коммуникации, PR и информационное продвижение.',
            responsibilities: [
                'PR-сопровождение деятельности Ассоциации',
                'ведение социальных сетей и сайта',
                'взаимодействие со СМИ и блогерами',
                'освещение регат и мероприятий',
                'создание и распространение информационных материалов',
                'развитие бренда Ассоциации и узнаваемости'
            ]
        }
    ]
}">
    <section class="py-10">
        <div class="max-w-(--breakpoint-2xl) mx-auto">
            <h2 class="section-title a-font mb-8">Руководители</h2>
            <div class="list grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">

                <template x-for="person in people" :key="person.id">
                    <div class="card bg-[#F8F8F8]">
                        <img :src="'{{ asset('images/management') }}/' + person.image" :alt="person.name">
                        <div class="info p-2 md:p-4">
                            <h4 class="text-[#2E325C] font-semibold md:text-xl text-sm mb-2 md:mb-4" x-text="person.name"></h4>
                            <div class="md:text-xl text-xs text-brand-gray mb-2 md:mb-4 md:h-14" x-text="person.position"></div>
                            <a @click.prevent="selectedPerson = person; open = true" class="md:text-xl text-sm font-semibold" href="#">Подробнее →</a>
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
                <img class="max-w-full" :src="'{{ asset('images/management') }}/' + (selectedPerson ? selectedPerson.image : '')" :alt="selectedPerson?.name">
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
                    <img class="max-w-full" :src="'{{ asset('images/management') }}/' + (selectedPerson ? selectedPerson.image : '')" :alt="selectedPerson?.name">
                </div>
                <h5 class="font-semibold text-[#2E325C] mt-4 md:mt-0 md:mb-6">О руководителе</h5>
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