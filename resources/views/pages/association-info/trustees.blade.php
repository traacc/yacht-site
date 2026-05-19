<x-public-layout>
<x-breadcrumbs_page title="Попечительский совет Ассоциации">
</x-breadcrumbs_page>
<x-hero-section title="Попечительский совет Ассоциации"
desc="Попечительский совет объединяет представителей сообщества и партнёров, оказывающих поддержку развитию Ассоциации и парусного спорта." 
bgImage="{{ asset('images/bg/trustees.png') }}"
>
    
</x-hero-section>
{{-- ===== Попечительский совет ===== --}}
<main class="main pb-12" x-data="{ open: false }">
    <section class="py-10">
        <div class="max-w-(--breakpoint-2xl) mx-auto">
            <h2 class="section-title a-font mb-8">Попечительский совет</h2>
            <div class="list grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <div class="card bg-[#F8F8F8]">
                    <img src="{{ asset('images/trustees/person_1.png') }}" alt="">
                    <div class="info p-4">
                        <h4 class="text-[#2E325C] font-semibold text-xl mb-4">Алексей Смирнов</h4>
                        <div class="text-lg text-brand-gray mb-4 h-14">Председатель попечительского совета</div>
                        <a @click.prevent="open = true" class="text-lg font-semibold" href="#">Подробнее →</a>
                    </div>
                </div>
                <div class="card bg-[#F8F8F8]">
                    <img src="{{ asset('images/trustees/person_2.png') }}" alt="">
                    <div class="info p-4">
                        <h4 class="text-[#2E325C] font-semibold text-xl mb-4">Дмитрий Воронов</h4>
                        <div class="text-lg text-brand-gray mb-4 h-14">Член попечительского совета</div>
                        <a @click.prevent="open = true" class="text-lg font-semibold" href="#">Подробнее →</a>
                    </div>
                </div>
                <div class="card bg-[#F8F8F8]">
                    <img src="{{ asset('images/trustees/person_3.png') }}" alt="">
                    <div class="info p-4">
                        <h4 class="text-[#2E325C] font-semibold text-xl mb-4">Максим Жолудов</h4>
                        <div class="text-lg text-brand-gray mb-4 h-14">Член попечительского совета</div>
                        <a @click.prevent="open = true" class="text-lg font-semibold" href="#">Подробнее →</a>
                    </div>
                </div>
                <div class="card bg-[#F8F8F8]">
                    <img src="{{ asset('images/trustees/person_4.png') }}" alt="">
                    <div class="info p-4">
                        <h4 class="text-[#2E325C] font-semibold text-xl mb-4">Сергей Морозов</h4>
                        <div class="text-lg text-brand-gray mb-4 h-14">Член попечительского совета</div>
                        <a @click.prevent="open = true" class="text-lg font-semibold" href="#">Подробнее →</a>
                    </div>
                </div>
                <div>
                </div>
                <div class="card bg-[#F8F8F8]">
                    <img src="{{ asset('images/trustees/person_5.png') }}" alt="">
                    <div class="info p-4">
                        <h4 class="text-[#2E325C] font-semibold text-xl mb-4">Анна Капитонова</h4>
                        <div class="text-lg text-brand-gray mb-4 h-14">Член попечительского совета</div>
                        <a @click.prevent="open = true" class="text-lg font-semibold" href="#">Подробнее →</a>
                    </div>
                </div>
                <div class="card bg-[#F8F8F8]">
                    <img src="{{ asset('images/trustees/person_6.png') }}" alt="">
                    <div class="info p-4">
                        <h4 class="text-[#2E325C] font-semibold text-xl mb-4">Игорь Чупров</h4>
                        <div class="text-lg text-brand-gray mb-4 h-14">Член попечительского совета</div>
                        <a @click.prevent="open = true" class="text-lg font-semibold" href="#">Подробнее →</a>
                    </div>
                </div>
            </div>

        </div>
    </section>
<div x-show="open" 
        x-cloak 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
    <div class="flex relative p-6 max-w-[1000px] bg-white gap-6"
        @click.away="open = false" 
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
    >
        <div class="photo max-w-1/2 shrink-0">
            <img class="max-w-full" src="{{ asset('images/management/person_1.png') }}" alt="">
        </div>
        <div class="info">
            <div class="info__header flex justify-between items-start mb-4">
                <h4 class="a-font text-3xl text-[#2E325C]">Игорь Скалин</h4>
                <div class="close">
                    <button @click="open = false" class="text-2xl font-bold">{!! file_get_contents(public_path('images/icons/close.svg')) !!}</button>
                </div>
            </div>
            
            <div class="text-lg font-semibold text-[#2E325C] mb-4">Президент Ассоциации</div>
            <h5 class="font-semibold text-[#2E325C] mb-6">О руководителе</h5>
            <p class="text-brand-gray mb-6">Игорь Скалин имеет опыт участия в парусных соревнованиях и организационной работы в спортивных проектах. В Ассоциации отвечает за общее направление развития, коммуникацию с участниками и формирование устойчивой структуры сезона.</p>
            <h5 class="font-semibold text-[#2E325C] mb-6">Зоны ответственности в Ассоциации</h5>
            <ul class="text-brand-gray list-disc pl-6 space-y-3.5">
                <li>стратегическое развитие Ассоциации яхт</li>
                <li>координация календаря регат</li>
                <li>взаимодействие с партнёрами и спонсорами</li>
                <li>утверждение ключевых организационных решений</li>
                <li>развитие сообщества владельцев и экипажей</li>
                <li>контроль работы руководящего состава</li>
            </ul>
        </div>
    </div>

    </div>
</main>


<x-feedback-section>
    
</x-feedback-section>
</x-public-layout>