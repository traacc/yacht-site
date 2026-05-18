<x-public-layout>
<x-breadcrumbs_page title="Решения общего собрания">
</x-breadcrumbs_page>
<x-hero-section title="Решения общего собрания"
desc="Официальные решения, принятые участниками Ассоциации CarterPro в рамках общего собрания." 
bgImage="{{ asset('images/bg/decisions.png') }}"
>
    
</x-hero-section>
{{-- ===== Документы ===== --}}
<style>

</style>
<section class="py-10">
    <div class="max-w-(--breakpoint-2xl) mx-auto px-4 sm:px-6 lg:px-8 pdf-list">
        <div class="flex justify-between mb-8">
            <h2 class="section-title a-font">Документы</h2>
            <div class="calendar-icon">
                <select class="border-[#C6C6C6] focus:outline-hidden focus:ring-2 text-[#2E325C] pl-5 w-[100px]" name="year" id="">
                    <option value="2026">2026</option>
                </select>
            </div>

        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-3 gap-6">
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
        <div class="pagination flex justify-center mt-10 gap-2">
            <button class="back"></button>
            <button class="px-4 py-2 text-lg text-[#2D92CE] font-medium border-b border-[#2D92CE]">1</button>
            <button class="px-4 py-2 text-lg text-[#2E325C]">2</button>
            <button class="px-4 py-2 text-lg text-[#2E325C]">3</button>
            <button class="px-4 py-2 text-lg text-[#2E325C]">4</button>
            <button class="px-4 py-2 text-lg text-[#2E325C]">5</button>
            <button class="px-4 py-2 text-lg text-[#2E325C]">...</button>
            <button class="px-4 py-2 text-lg text-[#2E325C]">50</button>
            <button class="next"></button>
        </div>
    </div>
</section>
<section class="py-10 bg-white">
<div class="max-w-(--breakpoint-2xl) mx-auto bg-[#F8F8F8] flex flex-col md:flex-row gap-10 items-center">
    <div class="info px-4 sm:px-6 lg:px-8">
        <h2 class="section-title a-font text-[#2E325C] text-5xl mb-8">О решениях общего собрания</h2>
        <p class="text-brand-gray font-medium text-lg mb-4">Решения общего собрания принимаются участниками Ассоциации и регулируют ключевые вопросы её деятельности, включая проведение соревнований, изменения в регламенте и развитие класса Carter 30.</p>
        <p class="text-brand-gray font-medium text-lg">Все решения фиксируются в протоколах и являются обязательными для исполнения участниками Ассоциации.</p>
    </div>
    <div class="pic max-w-[720px] shrink-0">
        <img class="w-full" src="{{ asset('images/rules/rules_pic_1.png') }}" alt="">
    </div>
</div>
</section>
<x-feedback-section>
    
</x-feedback-section>
</x-public-layout>