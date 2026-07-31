{{-- ===== ФУТЕР ===== --}}

<footer class="bg-[#2E325C] text-white" x-data="">
    <div class="container mx-auto py-12">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 mb-10 text-sm md:text-base">
            {{-- Лого и описание --}}
            <div class="col-span-2 md:col-span-1 font-light lg:block hidden">
                <a href="/"  class="shrink-0 block mb-6">
                    {!! file_get_contents(public_path('images/logo.svg')) !!}
                </a>
                <div class="mb-6">
                    © 2026 CarterPro. Все права защищены.
                </div>
                <ul class="space-y-1 mb-4">
                    <li><a href="/files/Политика_обработки_персональных_данных_1.pdf"  class="hover:text-white transition-colors">Политика конфиденциальности</a></li>
                    <li><a href="#"  class="hover:text-white transition-colors">Пользовательское соглашение</a></li>
                </ul>
            </div>

            {{-- Навигация --}}
            <div>
                <h4 class="font-semibold mb-6">Навигация</h4>
                <ul class="space-y-4 font-light">
                    <li><a href="{{ route('competitions') }}"  class="hover:text-white transition-colors">Соревнования</a></li>
                    <!--
                    <li><a href="{{ route('carter30.history') }}"  class="hover:text-white transition-colors">Carter 30</a></li>
                    <li><a href="{{ route('carter30.repair') }}"  class="hover:text-white transition-colors">Ремонт и модернизация</a></li>
                    <li><a href="{{ route('carter30.technical-help') }}"  class="hover:text-white transition-colors">Техническая помощь</a></li>
                    <li><a href="{{ route('carter30.marketplace') }}"  class="hover:text-white transition-colors">Барахолка</a></li>
                    <li><a href="{{ route('carter30.yacht-sale') }}"  class="hover:text-white transition-colors">Продать яхту</a></li>
                    -->
                    <li><a href="{{ route('teams') }}"  class="hover:text-white transition-colors">Команды</a></li>
                    <li><a href="{{ route('yachts') }}"  class="hover:text-white transition-colors">Яхты</a></li>
                    <li><a href="{{ route('ratings') }}"  class="hover:text-white transition-colors">Рейтинги</a></li>
                    <li><a href="{{ route('news') }}"  class="hover:text-white transition-colors">Новости</a></li>
                    <li><a href="{{ route('help') }}"  class="hover:text-white transition-colors">Помощь</a></li>
                </ul>
            </div>

            {{-- Ассоциация --}}
            <div>
                <h4 class="font-semibold mb-6">Ассоциация</h4>
                <ul class="space-y-4 font-light">
                    <li><a href="{{ route('charter') }}"  class="hover:text-white transition-colors">Об Ассоциации</a></li>
                    <li><a href="{{ route('management') }}"  class="hover:text-white transition-colors">Руководство Ассоциации</a></li>
                    <li><a href="{{ route('trustees') }}"  class="hover:text-white transition-colors">Попечительский совет</a></li>
                    <li><a href="{{ route('policy') }}"  class="hover:text-white transition-colors">Политика Ассоциации</a></li>
                    <li><a href="{{ route('rules') }}"  class="hover:text-white transition-colors">Правила вступления</a></li>
                    <li><a href="{{ route('regulations') }}"  class="hover:text-white transition-colors">Технический регламент яхт</a></li>
                    <li><a href="{{ route('decisions') }}"  class="hover:text-white transition-colors">Решения общего собрания</a></li>
                </ul>
            </div>

            {{-- Контакты --}}
            <div class="col-span-2 md:col-span-1">
                <h4 class="font-semibold mb-6">Контакты</h4>
                <ul class="space-y-4">
                    <li class="flex items-center gap-2">
                        {!! file_get_contents(public_path('images/icons/phone.svg')) !!}
                        <a href="tel:+79366101113">+7 (936) 610-11-13 </a>
                    </li>
                    <li class="flex items-center gap-2">
                        {!! file_get_contents(public_path('images/icons/mail.svg')) !!}
                        <a href="mailto:info@carter-pro.ru">info@carter-pro.ru</a>
                    </li>
                    <li class="flex items-center gap-2">
                        {!! file_get_contents(public_path('images/icons/marker.svg')) !!}
                        Москва
                    </li>
                    <li class="flex gap-4 pt-4">
                        <a href="https://t.me/a_carterpro" class="text-white hover:text-white" target="_blank">
                            {!! file_get_contents(public_path('images/social_icons/tl.svg')) !!}
                        </a>
                        <a href="https://vk.com/carter_pro" class="text-white hover:text-white" target="_blank">
                            {!! file_get_contents(public_path('images/social_icons/vk.svg')) !!}
                        </a>
                    </li>
                </ul>
                {{-- Лого и описание --}}
                <div class="col-span-2 md:col-span-1 font-light block lg:hidden">
                    <a href="/"  class="shrink-0 block mb-6">
                        {!! file_get_contents(public_path('images/logo.svg')) !!}
                    </a>
                    <div class="mb-6">
                        © 2026 CarterPro. Все права защищены.
                    </div>
                    <ul class="space-y-1 mb-4">
                        <li><a href="/files/Политика_обработки_персональных_данных_1.pdf"  class="hover:text-white transition-colors">Политика конфиденциальности</a></li>
                        <li><a href="#"  class="hover:text-white transition-colors">Пользовательское соглашение</a></li>
                    </ul>
                </div>
                @guest
                <a href="#" @click.prevent="$dispatch('open-login-modal')" class="font-semibold color-white px-4 py-2 mt-6 transition-colors justify-center border-white border flex gap-2 md:hidden">
                    {!! file_get_contents(public_path('images/icons/login.svg')) !!} Войти
                </a>
                @endguest
            </div>
        </div>
    </div>
</footer>