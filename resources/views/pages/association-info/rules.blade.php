<x-public-layout>
<x-breadcrumbs_page title="Правила вступления в Ассоциацию">
</x-breadcrumbs_page>
<x-hero-section title="Правила вступления в Ассоциацию"
desc="Порядок и условия вступления в Ассоциацию CarterPro для владельцев яхт и участников экипажей." 
bgImage="{{ asset('images/bg/rules.webp') }}"
>
    
</x-hero-section>
<main class="main" x-data>
  <section class="md:py-10 py-6 bg-white">
    <div class="container mx-auto bg-[#F8F8F8] flex flex-col md:flex-row gap-10 items-center">
        <div class="info px-4 sm:px-6 lg:px-8">
            <h2 class="section-title a-font text-[#2E325C] text-5xl mb-4 md:mb-8">Кто может стать членом Ассоциации</h2>
            <p class="text-brand-gray font-medium text-lg mb-2 md:mb-4">Членами Ассоциации могут быть:</p>
            <ul class="list-disc text-brand-gray pl-4 text-lg space-y-4 mb-2 md:mb-8">
                <li>владельцы яхт класса Carter 30</li>
                <li>участники экипажей</li>
                <li>лица, заинтересованные в развитии класса</li>
            </ul>
            <button @click="$dispatch('open-login-modal', { tab: 'register' })" class="mt-6 bg-[#2D92CE] text-white cursor-pointer py-2 px-6 hover:bg-[#0074CC] w-full md:max-w-[300px] transition-colors text-lg font-semibold flex items-center gap-2 justify-center">
            Зарегистрироватся {!! file_get_contents(public_path('images/icons/l-arrow-right.svg')) !!}
            </button>
        </div>
        <div class="pic">
            <img class="w-full" src="{{ asset('images/rules/rules_pic_1.png') }}" alt="">
        </div>
    </div>
  </section>
  <section class="container mx-auto md:mb-24 mb-8">
    <h2 class="section-title a-font text-[#2E325C] text-5xl mb-8">Условия членства в Ассоциации</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="card bg-[#F8F8F8] md:p-6 p-3 text-center">
            <img src="{{ asset('images/rules/rules_1.svg') }}" class="m-auto max-w-12 md:max-w-full mb-4" alt="">
            <p class="text-[#2E325C] md:text-lg text-sm">Соблюдение устава и регламентов</p>
        </div>
        <div class="card bg-[#F8F8F8] md:p-6 p-3 text-center">
            <img src="{{ asset('images/rules/rules_2.svg') }}" class="m-auto max-w-12 md:max-w-full mb-4" alt="">
            <p class="text-[#2E325C] md:text-lg text-sm">Участие в деятельности Ассоциации</p>
        </div>
        <div class="card bg-[#F8F8F8] md:p-6 p-3 text-center">
            <img src="{{ asset('images/rules/rules_3.svg') }}" class="m-auto max-w-12 md:max-w-full mb-4" alt="">
            <p class="text-[#2E325C] md:text-lg text-sm">Предоставление необходимых данных</p>
        </div>
        <div class="card bg-[#F8F8F8] md:p-6 p-3 text-center">
            <img src="{{ asset('images/rules/rules_4.svg') }}" class="m-auto max-w-12 md:max-w-full mb-4" alt="">
            <p class="text-[#2E325C] md:text-lg text-sm">Подтверждение заявки</p>
        </div>
    </div>
  </section>
  <section class="hidden container mx-auto md:mb-24 mb-8">
  
  <h2 class="section-title a-font text-[#2E325C] text-5xl mb-8">Порядок вступления</h2>
 
    <!-- Steps row -->
    <div class="md:flex items-start flex-col md:flex-row">
 
      <!-- Left stub line -->
      <div class="h-px w-32 hidden md:block bg-[#F24842] mt-12 shrink-0"></div>
 
      <!-- Step 1 -->
      <div class="flex md:flex-col items-center flex-1 gap-x-2">
        <div class="md:size-24 size-16 rounded-full border border-[#F24842] flex items-center justify-center">
          <span class="text-[#F24842] font-medium text-5xl leading-none a-font">1</span>
        </div>
        <p class="md:mt-4 md:text-center md:text-lg text-[#1e2a47] leading-snug">
          Подача заявки<br/>через сайт
        </p>
      </div>
 
      <!-- Connector -->
      <div class="flex-1 bg-[#F24842] md:mt-12 md:h-px md:min-w-[24px] h-8 w-px ml-8 md:ml-0"></div>
 
      <!-- Step 2 -->
      <div class="flex md:flex-col items-center flex-1 gap-x-2">
        <div class="md:size-24 size-16 rounded-full border border-[#F24842] flex items-center justify-center">
          <span class="text-[#F24842] font-medium text-5xl leading-none a-font">2</span>
        </div>
        <p class="md:mt-4 md:text-center md:text-lg text-[#1e2a47] leading-snug">
          Проверка данных
        </p>
      </div>
 
      <!-- Connector -->
      <div class="flex-1 bg-[#F24842] md:mt-12 md:h-px md:min-w-[24px] h-8 w-px ml-8 md:ml-0"></div>
 
      <!-- Step 3 -->
      <div class="flex md:flex-col items-center flex-1 gap-x-2">
        <div class="md:size-24 size-16 rounded-full border border-[#F24842] flex items-center justify-center">
          <span class="text-[#F24842] font-medium text-5xl leading-none a-font">3</span>
        </div>
        <p class="md:mt-4 md:text-center md:text-lg text-[#1e2a47] leading-snug">
          Подтверждение<br/>участия
        </p>
      </div>
 
      <!-- Connector -->
      <div class="flex-1 bg-[#F24842] md:mt-12 md:h-px md:min-w-[24px] h-8 w-px ml-8 md:ml-0"></div>
 
      <!-- Step 4 -->
      <div class="flex md:flex-col items-center flex-1 gap-x-2">
        <div class="md:size-24 size-16 rounded-full border border-[#F24842] flex items-center justify-center">
          <span class="text-[#F24842] font-medium text-5xl leading-none a-font">4</span>
        </div>
        <p class="md:mt-4 md:text-center md:text-lg text-[#1e2a47] leading-snug whitespace-nowrap">
          Включение<br/>в состав Ассоциации
        </p>
      </div>
 
      <!-- Right stub line -->
      <div class="h-px w-32 hidden md:block bg-[#F24842] mt-12 shrink-0"></div>
 
    </div>
  </section>

  <section class="container mx-auto md:mb-24 mb-8">
  
    <h2 class="section-title a-font text-[#2E325C] text-5xl mb-8">Порядок вступления</h2>
    <div class="order text-base md:text-lg">
      <ol class="list-decimal [&_a]:text-[#2D92CE] [&_a]:underline pl-6 space-y-5">
        <li>Для участия в соревнованиях Carter Pro необходимо зарегистрироваться <a @click="$dispatch('open-login-modal', { tab: 'register' })" href="#">тут</a> в качестве члена Ассоциации</li>
        <li>Членом Ассоциации может стать любой человек, желающий войти в сообщество яхтсменов</li>
        <li>Все члены Ассоциации имеют право принимать участие в обсуждениях любых вопросов на официальных ресурсах Ассоциации, <a href="https://vk.com/carter_pro">Вконтакте</a> или <a href="https://t.me/a_carterpro">Телеграмм</a></li>
        <li>Правом голоса на общем собрании членов Ассоциации обладают только владельцы <a href="/yachts">зарегистрированных</a> в Ассоциации яхт проекта Carter 30 (одна яхта – один голос)</li>
      </ol>
    </div>

  </section>
  
  <section style="background-image: url('{{ asset('images/rules/rules_want.png') }}')" class="container mx-auto bg-cover bg-center py-20 mt-10 flex flex-col items-center text-center mb-8">
    <h2 class="a-font text-white text-3xl md:text-5xl max-w-4xl">Хотите присоединиться к Ассоциации и принимать участие в её деятельности?</h2>
    <button @click="$dispatch('open-login-modal', { tab: 'register' })" class="mt-6 bg-white cursor-pointer text-[#2E325C] py-2 px-9 hover:bg-[#E8E8E8] transition-colors text-lg font-semibold w-full md:w-auto ms flex items-center justify-center gap-2">
        Зарегистрироватся  {!! file_get_contents(public_path('images/icons/l-arrow-right.svg')) !!}
    </button>
  </section>
</main>
{{-- ===== Кто может вступить ===== --}}

<x-feedback-section>
    
</x-feedback-section>
</x-public-layout>