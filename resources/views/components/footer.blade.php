{{-- ===== ФУТЕР ===== --}}

<footer class="bg-[#2E325C] text-white" x-data="">
    <div class="container mx-auto py-12">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 mb-10 text-sm md:text-base">
            {{-- Лого и описание --}}
            <div class="col-span-2 md:col-span-1 font-light">
                <a href="/" wire:navigate class="shrink-0 block mb-6">
                    {!! file_get_contents(public_path('images/logo.svg')) !!}
                </a>
                <div class="mb-6">
                    © 2026 CarterPro. Все права защищены.
                </div>
                <ul class="space-y-1 mb-4">
                    <li><a href="#" wire:navigate class="hover:text-white transition-colors">Политика конфиденциальности</a></li>
                    <li><a href="#" wire:navigate class="hover:text-white transition-colors">Пользовательское соглашение</a></li>
                </ul>
            </div>

            {{-- Навигация --}}
            <div>
                <h4 class="font-semibold mb-6">Навигация</h4>
                <ul class="space-y-4 font-light">
                    <li><a href="{{ route('competitions') }}" wire:navigate class="hover:text-white transition-colors">Соревнования</a></li>
                    <li><a href="{{ route('teams') }}" wire:navigate class="hover:text-white transition-colors">Команды</a></li>
                    <li><a href="{{ route('yachts') }}" wire:navigate class="hover:text-white transition-colors">Яхты</a></li>
                    <li><a href="{{ route('ratings') }}" wire:navigate class="hover:text-white transition-colors">Рейтинги</a></li>
                    <li><a href="{{ route('help') }}" wire:navigate class="hover:text-white transition-colors">Помощь</a></li>
                </ul>
            </div>

            {{-- Ассоциация --}}
            <div>
                <h4 class="font-semibold mb-6">Ассоциация</h4>
                <ul class="space-y-4 font-light">
                    <li><a href="{{ route('charter') }}" wire:navigate class="hover:text-white transition-colors">Устав Ассоциации</a></li>
                    <li><a href="{{ route('management') }}" wire:navigate class="hover:text-white transition-colors">Руководство</a></li>
                    <li><a href="{{ route('trustees') }}" wire:navigate class="hover:text-white transition-colors">Попечительский совет</a></li>
                    <li><a href="{{ route('policy') }}" wire:navigate class="hover:text-white transition-colors">Политика Ассоциации</a></li>
                    <li><a href="{{ route('rules') }}" wire:navigate class="hover:text-white transition-colors">Правила вступления</a></li>
                    <li><a href="{{ route('regulations') }}" wire:navigate class="hover:text-white transition-colors">Технический регламент яхт</a></li>
                    <li><a href="{{ route('decisions') }}" wire:navigate class="hover:text-white transition-colors">Решения общего собрания</a></li>
                </ul>
            </div>

            {{-- Контакты --}}
            <div class="col-span-2 md:col-span-1">
                <h4 class="font-semibold mb-6">Контакты</h4>
                <ul class="space-y-4">
                    <li class="flex items-center gap-2">
                        {!! file_get_contents(public_path('images/icons/phone.svg')) !!}
                        +7 (000) 000-60-00
                    </li>
                    <li class="flex items-center gap-2">
                        {!! file_get_contents(public_path('images/icons/mail.svg')) !!}
                        contact@mail.ru
                    </li>
                    <li class="flex items-center gap-2">
                        {!! file_get_contents(public_path('images/icons/marker.svg')) !!}
                        Москва
                    </li>
                    <li class="flex gap-4 pt-4">
                        <a href="#" class="text-white hover:text-white">
                            {!! file_get_contents(public_path('images/social_icons/tl.svg')) !!}
                        </a>
                        <a href="#" class="text-white hover:text-white">
                            {!! file_get_contents(public_path('images/social_icons/vk.svg')) !!}
                        </a>
                    </li>
                </ul>
                <a href="#" @click.prevent="$dispatch('open-login-modal')" class="font-semibold color-white px-4 py-2 mt-6 transition-colors justify-center border-white border flex gap-2 md:hidden">
                    {!! file_get_contents(public_path('images/icons/login.svg')) !!} Войти
                </a>
            </div>
        </div>
    </div>
</footer>