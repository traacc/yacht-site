{{-- ===== НАВИГАЦИЯ ===== --}}
<nav x-data="{ mobileOpen: false }" class="sticky top-0 z-10 bg-white py-4">
    <div class="container mx-auto">
        <div class="flex items-center justify-between h-14">
            {{-- Логотип --}}
            <a href="/"  class="shrink-0">
                {!! file_get_contents(public_path('images/logo.svg')) !!}
            </a>

            {{-- Десктоп-меню --}}
            <div class="hidden md:flex items-center gap-1">
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" @click.outside="open = false"
                        class="flex items-center gap-1 px-3 py-2 text-sm text-[#2E325C] transition-colors">
                        Ассоциация
                        <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-cloak x-transition
                        class="absolute top-full right-0 mt-1 w-52 bg-white rounded-lg shadow-xl border border-gray-100 py-1 z-50">
                        <a href="{{ route('charter') }}"  class="block px-4 py-2 text-gray-700">Об Ассоциации</a>
                        <!--
                        <a href="{{ route('management') }}"  class="block px-4 py-2 text-gray-700">Руководство</a>
                        <a href="{{ route('trustees') }}"  class="block px-4 py-2 text-gray-700">Попечительский совет</a>
                        -->
                        <a href="{{ route('policy') }}"  class="block px-4 py-2 text-gray-700">Политика Ассоциации</a>
                        <a href="{{ route('rules') }}"  class="block px-4 py-2 text-gray-700">Правила вступления</a>
                        <a href="{{ route('regulations') }}"  class="block px-4 py-2 text-gray-700">Технический регламент яхт</a>
                        <a href="{{ route('decisions') }}"  class="block px-4 py-2 text-gray-700">Решения общего собрания</a></li>
                    </div>
                </div>
                <a href="{{ route('competitions') }}"  class="px-3 py-2 text-[#2E325C] transition-colors">Соревнования</a>
                <a href="{{ route('teams') }}"  class="px-3 py-2 text-[#2E325C] transition-colors">Команды</a>
                <a href="{{ route('yachts') }}"  class="px-3 py-2 text-[#2E325C] transition-colors">Яхты</a>
                <a href="{{ route('ratings') }}"  class="px-3 py-2 text-[#2E325C] transition-colors">Рейтинги</a>
                <a href="{{ route('gallery') }}"  class="px-3 py-2 text-[#2E325C] transition-colors">Галерея</a>
                <a href="{{ route('help') }}"  class="px-3 py-2 text-[#2E325C] transition-colors">Помощь</a>
            </div>

            {{-- Действия --}}
            <div class="hidden md:flex items-center gap-2">
                <a href="https://t.me/a_carterpro" class="text-[#2D92CE] hover:text-white">
                    {!! file_get_contents(public_path('images/social_icons/tl.svg')) !!}
                </a>
                <a href="https://vk.com/carter_pro" class="text-[#2D92CE] hover:text-white">
                    {!! file_get_contents(public_path('images/social_icons/vk.svg')) !!}
                </a>

                @auth
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" @click.outside="open = false"
                        class="flex items-center gap-2 px-2 py-1 rounded-lg transition-colors">
                        <img src="{{ auth()->user()->photo_url ? asset('storage/' . auth()->user()->photo_url) : asset('images/icons/avatar-default.svg') }}"
                            alt="" class="w-8 h-8 rounded-full object-cover border-2 border-gray-200">
                        <span class="text-sm font-medium text-[#2E325C] hidden md:inline">{{ auth()->user()->first_name }}</span>
                        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="open" x-cloak x-transition
                        class="absolute top-full right-0 mt-2 w-52 bg-white  shadow-xl border border-gray-100 py-1 z-50">
                        <div class="px-4 py-2 border-b border-gray-100">
                            <p class="text-sm font-medium text-[#2E325C]">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-gray-500">{{ auth()->user()->email }}</p>
                        </div>
                        @if(auth()->user()->isAdmin() || auth()->user()->isJudge() || auth()->user()->isSecretary() || auth()->user()->isAccountant())
                            <a href="{{ url('/admin') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-brand-light hover:text-brand-red">Панель управления</a>
                        @else
                            <a href="{{ url('/user') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-brand-light hover:text-brand-red">Личный кабинет</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-brand-light hover:text-brand-red">Выйти</button>
                        </form>
                    </div>
                </div>
                @else
                <a href="#" @click="$dispatch('open-login-modal')" class="text-[#2D92CE] text-lg font-semibold px-4 py-2 transition-colors border-[#2D92CE] border flex gap-2 login-btn items-center">
                    {!! file_get_contents(public_path('images/icons/login.svg')) !!} <span class="hidden md:inline">Войти</span>
                </a>
                @endauth
            </div>

            {{-- Мобильное меню --}}
            <button @click="mobileOpen = !mobileOpen" class="md:hidden p-2 text-gray-300">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path x-show="!mobileOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    <path x-show="mobileOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>
    <div
        x-show="mobileOpen" 
        x-cloak
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        
        class="md:hidden fixed inset-0 bg-black/50 z-40 w-screen"
    >
        <div
        x-transition:enter="transition transform ease-out duration-300"
        x-transition:enter-start="translate-y-120 opacity-0"
        x-transition:enter-end="translate-y-80 opacity-100"
        x-transition:leave="transition transform ease-in duration-200"
        x-transition:leave-start="translate-y-80 opacity-100"
        x-transition:leave-end="translate-y-120 opacity-0"
        x-transition class="md:hidden bg-[#2E325C] py-2 px-4 space-y-1  min-w-[220px] h-full text-white fixed right-0"
        @click.outside="mobileOpen=false"
        >
            <div class="flex justify-between items-center mt-4 mb-4">
                <h3 class="uppercase a-font text-xl">Меню</h3>
                <button @click="mobileOpen = false" class="text-2xl font-bold">{!! file_get_contents(public_path('images/icons/close.svg')) !!}</button>
            </div>
            <div class="space-y-2">
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open"
                        class="flex items-center gap-1  py-2 text-sm transition-colors">
                        Ассоциация
                        <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-cloak x-transition
                        class="">
                        <a href="{{ route('charter') }}"  class="block px-4 py-2 text-sm">Об Ассоциации</a>
                        <!--
                        <a href="{{ route('management') }}"  class="block px-4 py-2 text-sm">Руководство</a>
                        <a href="{{ route('trustees') }}"  class="block px-4 py-2 text-sm">Попечительский совет</a>
                        -->
                        <a href="{{ route('policy') }}"  class="block px-4 py-2 text-sm">Политика Ассоциации</a>
                        <a href="{{ route('rules') }}"  class="block px-4 py-2 text-sm">Правила вступления</a>
                        <a href="{{ route('regulations') }}"  class="block px-4 py-2 text-sm">Технический регламент яхт</a>
                        <a href="{{ route('decisions') }}"  class="block px-4 py-2 text-sm">Решения общего собрания</a></li>
                    </div>
                </div>
                <a href="{{ route('competitions') }}"  class="block py-2 text-sm">Соревнования</a>
                <a href="{{ route('teams') }}"  class="block py-2 text-sm">Команды</a>
                <a href="{{ route('yachts') }}"  class="block py-2 text-sm">Яхты</a>
                <a href="{{ route('news') }}"  class="block py-2 text-sm">Галерея</a>
                <a href="{{ route('help') }}"  class="block py-2 text-sm">Помощь</a>
            </div>
            
            @auth
            <!--<div class="flex items-center gap-3 py-2 border-b border-white/20 mb-2">
                <img src="{{ auth()->user()->photo_url ? asset('storage/' . auth()->user()->photo_url) : asset('images/icons/avatar-default.svg') }}"
                    alt="" class="w-10 h-10 rounded-full object-cover border-2 border-white/30">
                <div>
                    <p class="text-sm font-medium">{{ auth()->user()->first_name }}</p>
                    <p class="text-xs text-gray-300">{{ auth()->user()->email }}</p>
                </div>
            </div>-->
            @if(auth()->user()->isAdmin() || auth()->user()->isJudge() || auth()->user()->isSecretary() || auth()->user()->isAccountant())
                <a href="{{ url('/admin') }}" class="block py-2 text-sm">Панель управления</a>
                <a href="{{ url('/admin/regattas') }}" class="block py-2 text-sm pl-3 text-white/80">— Регаты</a>
                <a href="{{ url('/admin/teams') }}" class="block py-2 text-sm pl-3 text-white/80">— Команды</a>
                <a href="{{ url('/admin/yachts') }}" class="block py-2 text-sm pl-3 text-white/80">— Яхты</a>
                <a href="{{ url('/admin/users') }}" class="block py-2 text-sm pl-3 text-white/80">— Пользователи</a>
                <a href="{{ url('/admin/ratings') }}" class="block py-2 text-sm pl-3 text-white/80">— Рейтинги</a>
                <a href="{{ url('/admin/news') }}" class="block py-2 text-sm pl-3 text-white/80">— Новости</a>
            @else
                <a href="{{ url('/user') }}" class="block py-2 text-sm">Личный кабинет</a>
                <a href="{{ url('/user/yachts') }}" class="block py-2 text-sm pl-3 text-white/80">— Мои яхты</a>
                <a href="{{ url('/user/teams') }}" class="block py-2 text-sm pl-3 text-white/80">— Мои команды</a>
                <a href="{{ url('/user/regatta-entries') }}" class="block py-2 text-sm pl-3 text-white/80">— Заявки на регаты</a>
                <a href="{{ url('/user/regatta-result-items') }}" class="block py-2 text-sm pl-3 text-white/80">— Результаты</a>
            @endif
            <form method="POST" action="{{ route('logout') }}" class="mt-2">
                @csrf
                <button type="submit" class="w-full text-left py-2 text-sm text-red-300 hover:text-red-200">Выйти</button>
            </form>
            @else
            <a href="#" @click="$dispatch('open-login-modal')" class="login-btn font-semibold px-4 py-2 transition-colors border-white border text-white justify-center flex items-center gap-2">
                {!! file_get_contents(public_path('images/icons/login.svg')) !!} Войти
            </a>
            @endauth

        </div>
    </div>

</nav>