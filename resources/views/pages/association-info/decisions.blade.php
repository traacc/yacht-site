<x-public-layout>
<x-breadcrumbs_page title="Решения общего собрания">
</x-breadcrumbs_page>
<x-hero-section title="Решения общего собрания"
desc="Официальные решения, принятые участниками Ассоциации CarterPro в рамках общего собрания." 
bgImage="{{ asset('images/bg/decisions.png') }}"
>
    
</x-hero-section>
{{-- ===== Документы ===== --}}

<section class="py-10"
    x-data="pagination()"
    x-init="init()"
>
    <div class="container mx-auto pdf-list">
        <div class="flex justify-between mb-8">
            <h2 class="section-title a-font">Документы</h2>
            <div class="calendar-icon">
                <select class="border-[#C6C6C6] focus:outline-hidden focus:ring-2 text-[#2E325C] pl-5 min-w-[100px]" name="year" id="">
                    <option value="2026">2026</option>
                </select>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <template x-for="doc in currentDocs" :key="doc.id">
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

        <div class="pagination flex justify-center items-center mt-10 gap-1.5" x-show="totalPages > 1">
            <button
                class="w-10 h-10 flex items-center justify-center rounded-lg text-lg text-[#2E325C] font-medium disabled:opacity-30 disabled:cursor-not-allowed hover:bg-gray-100 transition-colors cursor-pointer"
                @click.prevent="prevPage"
                :disabled="currentPage === 1"
            >
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <path d="M12.5 15L7.5 10L12.5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>

            <template x-for="(item, index) in pages" :key="index">
                <div>
                    <button
                        x-show="item.type === 'page'"
                        x-text="item.label"
                        @click.prevent="goToPage(item.value)"
                        class="w-10 h-10 text-lg font-medium transition-colors cursor-pointer"
                        :class="item.value === currentPage
                            ? 'border-b-[#2D92CE] border-b text-[#2D92CE]'
                            : 'text-brand-gray-light '"
                    ></button>
                    <span x-show="item.type === 'ellipsis'" class="w-10 h-10 flex items-center justify-center text-lg text-[#2E325C] select-none">...</span>
                </div>
            </template>

            <button
                class="w-10 h-10 flex items-center justify-center rounded-lg text-lg text-[#2E325C] font-medium disabled:opacity-30 disabled:cursor-not-allowed hover:bg-gray-100 transition-colors cursor-pointer"
                @click.prevent="nextPage"
                :disabled="currentPage === totalPages"
            >
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <path d="M7.5 15L12.5 10L7.5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
    </div>
</section>

<script>
    function pagination() {
        return {
            documents: [
                { id: 1,  title: 'Устав Ассоциации',                       desc: 'Актуальная редакция от 12 мая 2025',     path: '#' },
                { id: 2,  title: 'Правила участия',                         desc: 'Актуальная редакция от 12 мая 2025',     path: '#' },
                { id: 3,  title: 'Решения общего собрания',                 desc: 'Актуальная редакция от 10 апреля 2025',  path: '#' },
                { id: 4,  title: 'Положение о членских взносах',            desc: 'Утверждено 15 марта 2025',               path: '#' },
                { id: 5,  title: 'Кодекс этики участников',                 desc: 'Принят 20 февраля 2025',                 path: '#' },
                { id: 6,  title: 'Регламент соревнований 2025',             desc: 'Сезон 2025 года',                        path: '#' },
                { id: 7,  title: 'Протокол собрания № 1',                   desc: 'От 25 января 2025',                      path: '#' },
                { id: 8,  title: 'Протокол собрания № 2',                   desc: 'От 15 марта 2025',                       path: '#' },
                { id: 9,  title: 'Протокол собрания № 3',                   desc: 'От 10 мая 2025',                         path: '#' },
                { id: 10, title: 'Финансовый отчёт за 2024',               desc: 'Годовой отчёт',                          path: '#' },
                { id: 11, title: 'План развития на 2025-2026',              desc: 'Стратегический план',                    path: '#' },
                { id: 12, title: 'Изменения в регламенте',                  desc: 'Поправки от 1 апреля 2025',              path: '#' },
                { id: 13, title: 'Состав правления',                        desc: 'Обновлён 15 января 2025',                path: '#' },
                { id: 14, title: 'Положение о соревнованиях',               desc: 'Редакция от 1 марта 2025',               path: '#' },
                { id: 15, title: 'Заявка на участие',                       desc: 'Форма 2025 года',                        path: '#' },
                { id: 16, title: 'Правила безопасности',                    desc: 'Утверждены 20 декабря 2024',             path: '#' },
                { id: 17, title: 'Экологические стандарты',                 desc: 'Приняты 5 июня 2024',                    path: '#' },
                { id: 18, title: 'Положение о штрафах',                     desc: 'Действует с 1 января 2025',              path: '#' },
                { id: 19, title: 'Протокол собрания № 4',                   desc: 'От 20 июня 2025',                        path: '#' },
                { id: 20, title: 'Протокол собрания № 5',                   desc: 'От 5 сентября 2025',                     path: '#' },
                { id: 21, title: 'Бюджет на 2025 год',                      desc: 'Утверждён 15 января 2025',               path: '#' },
                { id: 22, title: 'Отчёт ревизионной комиссии',              desc: 'За 2024 год',                            path: '#' },
                { id: 23, title: 'Положение о спонсорах',                   desc: 'Утверждено 10 февраля 2025',             path: '#' },
                { id: 24, title: 'Календарь мероприятий 2025',              desc: 'План соревнований',                      path: '#' },
            ],
            perPage: 6,
            currentPage: 1,
            
            get totalPages() {
                return Math.ceil(this.documents.length / this.perPage);
            },

            get currentDocs() {
                const start = (this.currentPage - 1) * this.perPage;
                return this.documents.slice(start, start + this.perPage);
            },

            get pages() {
                const total = this.totalPages;
                const current = this.currentPage;
                const items = [];

                if (total <= 7) {
                    for (let i = 1; i <= total; i++) {
                        items.push({ type: 'page', label: i, value: i });
                    }
                    return items;
                }

                items.push({ type: 'page', label: 1, value: 1 });

                if (current > 3) {
                    items.push({ type: 'ellipsis' });
                }

                const start = Math.max(2, current - 1);
                const end = Math.min(total - 1, current + 1);
                for (let i = start; i <= end; i++) {
                    items.push({ type: 'page', label: i, value: i });
                }

                if (current < total - 2) {
                    items.push({ type: 'ellipsis' });
                }

                items.push({ type: 'page', label: total, value: total });

                return items;
            },

            goToPage(page) {
                if (page >= 1 && page <= this.totalPages) {
                    this.currentPage = page;
                    //window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            },

            prevPage() {
                if (this.currentPage > 1) {
                    this.currentPage--;
                    //window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            },

            nextPage() {
                if (this.currentPage < this.totalPages) {
                    this.currentPage++;
                    //window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            },

            init() {
                this.currentPage = 1;
            }
        };
    }
</script>

<section class="py-10 bg-white px-4 sm:px-6 lg:px-8">
<div class="container mx-auto bg-[#F8F8F8] flex flex-col md:flex-row gap-10 items-center">
    <div class="info px-4 sm:px-6 lg:px-8">
        <h2 class="section-title a-font text-[#2E325C] text-2xl md:text-5xl mb-8">О решениях общего собрания</h2>
        <p class="text-brand-gray font-medium text-sm md:text-lg mb-4">Решения общего собрания принимаются участниками Ассоциации и регулируют ключевые вопросы её деятельности, включая проведение соревнований, изменения в регламенте и развитие класса Carter 30.</p>
        <p class="text-brand-gray font-medium text-sm md:text-lg">Все решения фиксируются в протоколах и являются обязательными для исполнения участниками Ассоциации.</p>
    </div>
    <div class="pic max-w-[720px] shrink-0">
        <img class="w-full" src="{{ asset('images/decisions.png') }}" alt="">
    </div>
</div>
</section>
<x-feedback-section>
    
</x-feedback-section>
</x-public-layout>