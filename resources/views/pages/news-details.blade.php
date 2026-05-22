<x-public-layout>
<x-breadcrumbs_page title="Открыта регистрация на Кубок Carter Pro">
</x-breadcrumbs_page>
<main class="main">
    <section class="py-12">
        <div class="container mx-auto flex flex-col md:flex-row gap-12">
            <div class="content max-w-[902px]">
                <h2 class="section-title a-font text-5xl mb-4">Открыта регистрация на Кубок Carter Pro</h2>
                <p class="date text-brand-gray-light mb-4">10 июня 2026</p>
                <p class="font-semibold text-lg text-[#2E325C] mb-4">Открыта регистрация на участие в регате Кубок Carter 30 Pro 2026, которая пройдёт 23–25 мая на акватории Пироговского водохранилища.</p>

                <div class="img mb-4">
                    <img class="w-full" src="{{ asset('images/gallery.png') }}" alt="">
                </div>
                <div class="text space-y-4 text-lg">
                    <p>К участию приглашаются команды класса Carter 30. Организаторы команд могут подать заявку через личный кабинет, выбрав команду и подтверждённую яхту.</p>
                    <p>Регистрация доступна до окончания приёма заявок. После отправки заявка будет рассмотрена организаторами, а её статус можно будет отслеживать в личном кабинете.</p>
                    <p>Подать заявку можно на странице регаты или в разделе “Заявки на соревнования” в личном кабинете.</p>
                </div>
            </div>
            <div class="aside">
                <h3 class="section-title a-font text-lg md:text-3xl mb-4 text-center">Другие новости</h2>
                <div class="col flex flex-col gap-8">
                    @foreach(range(1, 3) as $item)
                    <div class="item flex gap-2">
                        <div class="img max-w-[200px]">
                            <img class="w-full" src="{{ asset('images/news/news_others.png') }}" alt="">
                        </div>
                        <div class="info py-2 bg-[#F8F8F8]">
                            <h4 class="text-lg font-semibold mb-3">Обновлены правила подачи заявок</h4>
                            <p class="mb-3 font-medium">На сайте опубликованы уточн...</p>
                            <div class="date mb-3 text-brand-gray-light">10 июня 2026</div>
                            <a href="#" class="text-lg font-semibold hover:underline">Все галерея →</a>
                        </div>
                    </div>
                    @endforeach
                </div>
                <a href="#" class="mx-auto mt-6 block text-lg font-semibold hover:underline text-center">Показать все →</a>
            </div>
        </div>
    </section>
    
</main>
</x-public-layout>