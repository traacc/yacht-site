<x-public-layout>
<x-breadcrumbs_page title="Новости ассоциации">
</x-breadcrumbs_page>
<main class="main">
    <section class="md:py-12 py-4 reggata-list">
        <div class="container mx-auto">
            <div class="flex justify-between mb-6 flex-col md:flex-row">
                <h2 class="section-title a-font text-5xl">Новости ассоциации</h2>
                <div class="controls flex gap-4">
                    <div class="calendar-icon">
                        <select class="border-[#C6C6C6] focus:outline-hidden focus:ring-2 text-[#2E325C] pl-5 w-[150px]" name="year" id="">
                            <option value="2026">2026</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section class="md:py-12 py-4">
        <div class="flex container mx-auto mb-6">
            <div class="row flex flex-col md:flex-row gap-6">
                <div class="col">
                    <div class="item">
                        <div class="img">
                            <img class="w-full h-full" src="{{ asset('images/gallery.png') }}" alt="">
                        </div>
                        <div class="info p-4 bg-[#F8F8F8]">
                            <h4 class="text-xl font-semibold mb-4">Открыта регистрация на Кубок Carter Pro</h4>
                            <p class="mb-4 font-medium">Организаторы команд уже могут подать заявку на участие в ближайшей регате сезона через личный кабинет.</p>
                            <div class="date mb-4 text-brand-gray-light">10 июня 2026</div>
                            <a href="{{ route('news-details') }}" class="text-lg font-semibold hover:underline">Читать далее →</a>
                        </div>
                    </div>
                </div>
                <div class="col flex flex-col gap-4">
                    <div class="item flex">
                        <div class="img max-w-[300px]">
                            <img class="w-full max-w-[300px] md:max-w-full h-full object-cover" src="{{ asset('images/news/news_3.png') }}" alt="">
                        </div>
                        <div class="info p-4 bg-[#F8F8F8] h-full">
                            <h4 class="text-xl font-semibold mb-3">Открыта регистрация на Кубок Carter Pro</h4>
                            <p class="mb-3 font-medium">На сайте опубликованы уточнения п</p>
                            <div class="date mb-3 text-brand-gray-light">10 июня 2026</div>
                            <a href="{{ route('news-details') }}" class="text-lg font-semibold hover:underline">Читать далее →</a>
                        </div>
                    </div>
                    <div class="item flex">
                        <div class="img max-w-[300px]">
                            <img class="w-full max-w-[300px] md:max-w-full h-full object-cover" src="{{ asset('images/news/news_3.png') }}" alt="">
                        </div>
                        <div class="info p-4 bg-[#F8F8F8]">
                            <h4 class="text-xl font-semibold mb-3">Открыта регистрация на Кубок Carter Pro</h4>
                            <p class="mb-3 font-medium">На сайте опубликованы уточнения п</p>
                            <div class="date mb-3 text-brand-gray-light">10 июня 2026</div>
                            <a href="{{ route('news-details') }}" class="text-lg font-semibold hover:underline">Читать далее →</a>
                        </div>
                    </div>
                    <div class="item flex">
                        <div class="img max-w-[300px]">
                            <img class="w-full max-w-[300px] md:max-w-full h-full object-cover" src="{{ asset('images/news/news_3.png') }}" alt="">
                        </div>
                        <div class="info p-4 bg-[#F8F8F8]">
                            <h4 class="text-xl font-semibold mb-3">Открыта регистрация на Кубок Carter Pro</h4>
                            <p class="mb-3 font-medium">На сайте опубликованы уточнения п</p>
                            <div class="date mb-3 text-brand-gray-light">10 июня 2026</div>
                            <a href="{{ route('news-details') }}" class="text-lg font-semibold hover:underline">Читать далее →</a>
                        </div>
                    </div>
                </div>

            </div>

        </div>
        <div class="grid md:grid-cols-4 gap-6  container mx-auto">
            @foreach(range(1, 8) as $item)
            <div class="item flex md:flex-col">
                <div class="img max-w-[300px] md:max-w-full">
                    <img class="w-full max-w-[300px] md:max-w-full h-full object-cover" src="{{ asset('images/gallery.png') }}" alt="">
                </div>
                <div class="info p-4 bg-[#F8F8F8]">
                    <h4 class="text-xl font-semibold mb-4">Открыта регистрация на Кубок Carter Pro</h4>
                    <p class="mb-4 font-medium">На сайте опубликованы уточнения п</p>
                    <div class="date mb-4 text-brand-gray-light">10 июня 2026</div>
                    <a href="{{ route('news-details') }}" class="text-lg font-semibold hover:underline">Читать далее →</a>
                </div>
            </div>
            @endforeach
        </div>
    </section>
    
</main>
</x-public-layout>