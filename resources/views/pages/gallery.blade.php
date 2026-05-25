<x-public-layout>
<x-breadcrumbs_page title="Галерея">
</x-breadcrumbs_page>
<main x-data="{
    gallery_modal_open: false,
    lightbox_open: false,
    activeImage: '',
    lbImages: []
}" class="main">
    <section class="md:py-12 py-4 reggata-list px-2 2xl:px-0">
        <div class="container mx-auto">
            <div class="flex justify-between mb-6 flex-col md:flex-row">
                <h2 class="section-title a-font text-3xl md:text-5xl mb-4 md:mb-0">Галерея</h2>
                <div class="controls flex gap-4">
                    <div class="calendar-icon">
                        <select class="border-[#C6C6C6] focus:outline-hidden h-full focus:ring-2 text-[#2E325C] pl-5 min-w-[140px]" name="year" id="">
                            <option value="2026">2026</option>
                        </select>
                    </div>

                    <select name="team_filter" id="team_filter" class="team_filter">
                        <option value="">Все акватории</option>
                    </select>
                </div>
            </div>
        </div>
    </section>
    <section>
        <div class="container mx-auto pb-12 mb-4 border-b border-b-[#EAEAEA]">
            <h2 class="section-title a-font text-5xl">2026</h2>
            <div class=" grid md:grid-cols-3 gap-6 mt-6"
             x-data="{
                current: 0,
                images: [
                    '{{ asset('images/news/news_1.png') }}',
                    '{{ asset('images/news/news_1.png') }}',
                    '{{ asset('images/news/news_1.png') }}',
                    '{{ asset('images/news/news_1.png') }}',
                    '{{ asset('images/news/news_1.png') }}',
                    '{{ asset('images/news/news_1.png') }}',
                ]
            }"
            >
                <template x-for="img in images">
                    <div class="cursor-pointer group relative" @click="gallery_modal_open = true">
                        
                        <img :src="img" alt="" class="absolute w-full h-full object-cover z-10 transition-transform duration-500 group-hover:scale-105">
                        <div class="bg-[#2E325C] opacity-30 absolute z-40 w-full h-full transition-transform duration-500 group-hover:scale-105"></div>
                        <div class="info relative z-20 p-6 pt-56 text-white">
                            <h4 class="title a-font text-2xl mb-3">Кубок залива 2026</h4>
                            <p class="mb-3 flex gap-3">{!! file_get_contents(public_path('images/icons/calendar.svg')) !!} 29–30 сентября · Москва</p>
                            <p class="flex gap-3">{!! file_get_contents(public_path('images/icons/waves.svg')) !!} Пироговское водохранилище</p>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </section>
    <section>
        <div class="container mx-auto pb-12 mb-4 border-b">
            <h2 class="section-title a-font text-5xl">2025</h2>
            <div class=" grid md:grid-cols-3 gap-6 mt-6"
             x-data="{
                current: 0,
                images: [
                    '{{ asset('images/news/news_1.png') }}',
                    '{{ asset('images/news/news_1.png') }}',
                    '{{ asset('images/news/news_1.png') }}',
                    '{{ asset('images/news/news_1.png') }}',
                    '{{ asset('images/news/news_1.png') }}',
                    '{{ asset('images/news/news_1.png') }}',
                ]
            }"
            >
                <template x-for="img in images">
                    <div class="cursor-pointer group relative" @click="gallery_modal_open = true">
                        
                        <img :src="img" alt="" class="absolute w-full h-full object-cover z-10 transition-transform duration-500 group-hover:scale-105">
                        <div class="bg-[#2E325C] opacity-30 absolute z-40 w-full h-full transition-transform duration-500 group-hover:scale-105"></div>
                        <div class="info relative z-20 p-6 pt-56 text-white">
                            <h4 class="title a-font text-2xl mb-3">Кубок залива 2026</h4>
                            <p class="mb-3 flex gap-3">{!! file_get_contents(public_path('images/icons/calendar.svg')) !!} 29–30 сентября · Москва</p>
                            <p class="flex gap-3">{!! file_get_contents(public_path('images/icons/waves.svg')) !!} Пироговское водохранилище</p>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </section>
    <section>
        <div class="container mx-auto mb-16">
            <h2 class="section-title a-font text-5xl">2024</h2>
            <div 
            x-data="{
                current: 0,
                images: [
                    '{{ asset('images/news/news_1.png') }}',
                    '{{ asset('images/news/news_1.png') }}',
                    '{{ asset('images/news/news_1.png') }}',
                    '{{ asset('images/news/news_1.png') }}',
                    '{{ asset('images/news/news_1.png') }}',
                    '{{ asset('images/news/news_1.png') }}',
                ]
            }"
            class=" grid md:grid-cols-3 gap-6 mt-6">
                <template x-for="img in images">
                    <div class="cursor-pointer group relative" @click="gallery_modal_open = true">
                        
                        <img :src="img" alt="" class="absolute w-full h-full object-cover z-10 transition-transform duration-500 group-hover:scale-105">
                        <div class="bg-[#2E325C] opacity-30 absolute z-5 w-full h-full transition-transform duration-500 group-hover:scale-105"></div>
                        <div class="info relative z-20 p-6 pt-56 text-white">
                            <h4 class="title a-font text-2xl mb-3">Кубок залива 2026</h4>
                            <p class="mb-3 flex gap-3">{!! file_get_contents(public_path('images/icons/calendar.svg')) !!} 29–30 сентября · Москва</p>
                            <p class="flex gap-3">{!! file_get_contents(public_path('images/icons/waves.svg')) !!} Пироговское водохранилище</p>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </section>
    <div x-show="gallery_modal_open" 
        x-cloak 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 team-modal">
        <!-- Модальное окно для подробной информации о команде -->
        <div @click.away="gallery_modal_open = false"  class="relative p-6 max-w-[1200px] max-h-[80vh] overflow-y-auto bg-white gap-6"
            x-data="{
                activeTab: 'video',
                video: [
                    '{{ asset('images/news/news_2.png') }}',
                    '{{ asset('images/news/news_1.png') }}',
                    '{{ asset('images/news/news_2.png') }}',
                    '{{ asset('images/news/news_1.png') }}',
                    '{{ asset('images/news/news_2.png') }}',
                    '{{ asset('images/news/news_1.png') }}',
                ],
                images: [
                    '{{ asset('images/news/news_1.png') }}',
                    '{{ asset('images/news/news_2.png') }}',
                    '{{ asset('images/news/news_1.png') }}',
                    '{{ asset('images/news/news_2.png') }}',
                    '{{ asset('images/news/news_1.png') }}',
                    '{{ asset('images/news/news_1.png') }}',
                ]
            }"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
        >
            <div class="flex justify-between mb-4">
                <h3 class="text-3xl a-font">Кубок залива 2026</h3>
                <div class="close">
                    <button @click="gallery_modal_open = false" class="text-2xl font-bold">&times;</button>
                </div>
            </div>
            <p class="text-lg mb-6">
                23–25 мая · Пироговское водохранилище
            </p>
            <div class="flex gap-4 font-medium text-lg mb-6">
                <button @click="activeTab = 'video'" :class="activeTab === 'video' ? 'bg-[#2D92CE] text-white' : 'bg-[#F8F8F8] text-[#2E325C]'" class="p-4 text-center">Видео</button>
                <button @click="activeTab = 'photo'" :class="activeTab === 'photo' ? 'bg-[#2D92CE] text-white' : 'bg-[#F8F8F8] text-[#2E325C]'" class="p-4 text-center">Фотографии</button>
            </div>
            <div x-show="activeTab === 'video'">
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <template x-for="item in video">
                        <div class="card bg-[#F8F8F8]"  @click="lightbox_open = true; gallery_modal_open = false; activeImage = item; lbImages = video">
                            <img class="h-full object-cover" :src="item" alt="">
                        </div>
                    </template>
                </div>
            </div>
            <div x-show="activeTab === 'photo'">
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <template x-for="item in images">
                        <div class="card bg-[#F8F8F8]"  @click="lightbox_open = true; gallery_modal_open = false; activeImage = item; lbImages = images">
                            <img class="h-full object-cover" :src="item" alt="">
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
    <div x-show="lightbox_open"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
        x-data="{
            get currentIndex() {
                return this.lbImages.indexOf(this.activeImage);
            },
            prevImage() {
                if (this.lbImages.length === 0) return;
                const newIndex = (this.currentIndex - 1 + this.lbImages.length) % this.lbImages.length;
                this.activeImage = this.lbImages[newIndex];
            },
            nextImage() {
                if (this.lbImages.length === 0) return;
                const newIndex = (this.currentIndex + 1) % this.lbImages.length;
                this.activeImage = this.lbImages[newIndex];
            }
        }"
        @keydown.left.window="prevImage()"
        @keydown.right.window="nextImage()"
        >
        <div @click.away="lightbox_open = false" class="relative max-w-[1200px] max-h-[80vh] gap-6"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
        >
            <!-- Кнопка закрытия -->
            <button @click="lightbox_open = false" class="md:absolute fixed md:top-5 md:right-5 top-5 right-5 text-white text-3xl z-50 hover:opacity-70">&times;</button>

            <!-- Контейнер с изображением и стрелками -->
            <div class="relative flex items-center justify-center">
                <!-- Стрелка назад -->
                <button @click="prevImage()"
                    class="absolute -left-6 z-50 p-3 text-white bg-[#2D92CE] hover:bg-[#2D92CE] rounded-full transition-all hidden md:block">
<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>

                <img :src="activeImage"
                    x-show="lightbox_open"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="scale-90 opacity-0"
                    x-transition:enter-end="scale-100 opacity-100"
                    class="w-[75vw] h-[60vh] object-cover rounded-sm shadow-2xl"
                    alt="Full size">

                <!-- Стрелка вперёд -->
                <button @click="nextImage()"
                    class="absolute -right-6 z-50 p-3 text-white bg-[#2D92CE] hover:bg-[#2D92CE] rounded-full transition-all hidden md:block">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>

            <!-- Превью (миниатюры) -->
            <div class="flex gap-4 overflow-hidden mt-4 justify-center">
                <template x-for="img in lbImages">
                    <div class="cursor-pointer rounded-lg shadow-hover transition-all max-w-[100px] aspect-square"
                        
                        @click="activeImage = img">
                        <img :src="img"
                            class="object-contain h-full w-full"
                            alt="Preview">
                    </div>
                </template>
            </div>
        </div>
    </div>
    
</main>



<x-feedback-section>
    
</x-feedback-section>
</x-public-layout>