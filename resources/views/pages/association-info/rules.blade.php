<x-public-layout>
<x-breadcrumbs_page title="Правила вступления в Ассоциацию">
</x-breadcrumbs_page>
<x-hero-section title="Правила вступления в Ассоциацию"
desc="Порядок и условия вступления в Ассоциацию CarterPro для владельцев яхт и участников экипажей." 
bgImage="{{ asset('images/bg/rules.png') }}"
>
    
</x-hero-section>
{{-- ===== Кто может вступить ===== --}}
  <section class="py-10 bg-white">
    <div class="max-w-(--breakpoint-2xl) mx-auto bg-[#F8F8F8] flex flex-col md:flex-row gap-10 items-center">
        <div class="info px-4 sm:px-6 lg:px-8">
            <h2 class="section-title a-font text-[#2E325C] text-5xl mb-8">Кто может стать членом Ассоциации</h2>
            <p class="text-brand-gray font-medium text-lg mb-4">Членами Ассоциации могут быть:</p>
            <ul class="list-disc text-brand-gray pl-4 text-lg space-y-4 mb-8">
                <li>владельцы яхт класса Carter 30</li>
                <li>участники экипажей</li>
                <li>лица, заинтересованные в развитии класса</li>
            </ul>
            <button class="mt-6 bg-[#2D92CE] text-white py-2 px-6 hover:bg-[#0074CC] transition-colors text-lg font-semibold">
            Подать заявку →
            </button>
        </div>
        <div class="pic">
            <img class="w-full" src="{{ asset('images/rules/rules_pic_1.png') }}" alt="">
        </div>
    </div>
  </section>
  <section class="max-w-(--breakpoint-2xl) mx-auto mb-24">
    <h2 class="section-title a-font text-[#2E325C] text-5xl mb-8">Условия вступления</h2>
    <div class="flex gap-4">
        <div class="card bg-[#F8F8F8] p-6 text-center">
            <img src="{{ asset('images/rules/rules_1.svg') }}" class="m-auto mb-4" alt="">
            <p class="text-[#2E325C] text-lg">Соблюдение устава и регламентов</p>
        </div>
        <div class="card bg-[#F8F8F8] p-6 text-center">
            <img src="{{ asset('images/rules/rules_2.svg') }}" class="m-auto mb-4" alt="">
            <p class="text-[#2E325C] text-lg">Участие в деятельности Ассоциации</p>
        </div>
        <div class="card bg-[#F8F8F8] p-6 text-center">
            <img src="{{ asset('images/rules/rules_3.svg') }}" class="m-auto mb-4" alt="">
            <p class="text-[#2E325C] text-lg">Предоставление необходимых данных</p>
        </div>
        <div class="card bg-[#F8F8F8] p-6 text-center">
            <img src="{{ asset('images/rules/rules_4.svg') }}" class="m-auto mb-4" alt="">
            <p class="text-[#2E325C] text-lg">Подтверждение заявки</p>
        </div>
    </div>
  </section>
  <section class="max-w-(--breakpoint-2xl) mx-auto mb-24">
  <h2 class="section-title a-font text-[#2E325C] text-5xl mb-8">Порядок вступления</h2>
 
    <!-- Steps row -->
    <div class="flex items-start">
 
      <!-- Left stub line -->
      <div class="h-px w-32 bg-[#F24842] mt-12 shrink-0"></div>
 
      <!-- Step 1 -->
      <div class="flex flex-col items-center flex-1">
        <div class="w-24 h-24 rounded-full border border-[#F24842] flex items-center justify-center">
          <span class="text-[#F24842] font-medium text-5xl leading-none">1</span>
        </div>
        <p class="mt-4 text-center text-lg text-[#1e2a47] leading-snug">
          Подача заявки<br/>через сайт
        </p>
      </div>
 
      <!-- Connector -->
      <div class="h-px flex-1 bg-[#F24842] mt-12 min-w-[24px]"></div>
 
      <!-- Step 2 -->
      <div class="flex flex-col items-center flex-1">
        <div class="w-24 h-24 rounded-full border border-[#F24842] flex items-center justify-center">
          <span class="text-[#F24842] font-medium text-5xl leading-none">2</span>
        </div>
        <p class="mt-4 text-center text-lg text-[#1e2a47] leading-snug">
          Проверка данных
        </p>
      </div>
 
      <!-- Connector -->
      <div class="h-px flex-1 bg-[#F24842] mt-12 min-w-[24px]"></div>
 
      <!-- Step 3 -->
      <div class="flex flex-col items-center flex-1">
        <div class="w-24 h-24 rounded-full border border-[#F24842] flex items-center justify-center">
          <span class="text-[#F24842] font-medium text-5xl leading-none">3</span>
        </div>
        <p class="mt-4 text-center text-lg text-[#1e2a47] leading-snug">
          Подтверждение<br/>участия
        </p>
      </div>
 
      <!-- Connector -->
      <div class="h-px flex-1 bg-[#F24842] mt-12 min-w-[24px]"></div>
 
      <!-- Step 4 -->
      <div class="flex flex-col items-center flex-1">
        <div class="w-24 h-24 rounded-full border border-[#F24842] flex items-center justify-center">
          <span class="text-[#F24842] font-medium text-5xl leading-none">4</span>
        </div>
        <p class="mt-4 text-center text-lg text-[#1e2a47] leading-snug">
          Включение<br/>в состав Ассоциации
        </p>
      </div>
 
      <!-- Right stub line -->
      <div class="h-px w-32 bg-[#F24842] mt-12 shrink-0"></div>
 
    </div>
  </section>
  <section style="background-image: url('{{ asset('images/rules/rules_want.png') }}')" class="max-w-(--breakpoint-2xl) mx-auto bg-cover bg-center py-20 mt-10 flex flex-col items-center text-center mb-8">
    <h2 class="a-font text-white text-5xl max-w-4xl">Хотите присоединиться к Ассоциации и принимать участие в её деятельности?</h2>
    <button class="mt-6 bg-white text-[#2E325C] py-2 px-9 hover:bg-[#E8E8E8] transition-colors text-lg font-semibold">
        Подать заявку  →
    </button>
  </section>
<x-feedback-section>
    
</x-feedback-section>
</x-public-layout>