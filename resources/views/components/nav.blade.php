{{-- ===== НАВИГАЦИЯ ===== --}}
<nav x-data="{ mobileOpen: false }" class="sticky top-0 z-25 bg-white px-2">
    <div class="container mx-auto">
        <div class="flex items-center justify-between h-14">
            {{-- Логотип --}}
            <a href="/"  class="shrink-0">
                {!! \App\Support\Svg::inline('images/logo.svg') !!}
            </a>

            {{-- Десктоп-меню.
                 Шесть разделов верхнего уровня вместо десяти: в 1536-контейнер вместе с логотипом
                 и правым блоком помещается только ~700px навигации. Разделы с длинными списками
                 раскрываются мега-меню в две колонки. --}}
            <div class="hidden xl:flex items-center gap-1">
                <div x-data="{ open: false }" class="relative" @mouseenter="open = true" @mouseleave="open = false">
                    <button @click="open = !open"
                        class="flex items-center gap-1 px-3 py-2 text-sm text-[#2E325C] transition-colors">
                        Ассоциация
                        <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-cloak x-transition
                        class="absolute top-full left-0 mt-1 w-152 grid grid-cols-2 gap-x-2 bg-white rounded-lg shadow-xl border border-gray-100 py-2 z-50">
                        <div>
                            <p class="px-4 pb-1 text-xs uppercase tracking-wide text-gray-400">Об ассоциации</p>
                            <a href="{{ route('charter') }}"  class="block px-4 py-2 text-gray-700">Об Ассоциации</a>
                            <a href="{{ route('management') }}"  class="block px-4 py-2 text-gray-700">Руководство Ассоциации</a>
                            <a href="{{ route('trustees') }}"  class="block px-4 py-2 text-gray-700">Попечительский совет</a>
                            <a href="{{ route('policy') }}"  class="block px-4 py-2 text-gray-700">Политика Ассоциации</a>
                            <p class="px-4 pt-3 pb-1 text-xs uppercase tracking-wide text-gray-400">Документы</p>
                            <a href="{{ route('rules') }}"  class="block px-4 py-2 text-gray-700">Правила вступления</a>
                            <a href="{{ route('regulations') }}"  class="block px-4 py-2 text-gray-700">Технический регламент яхт</a>
                            <a href="{{ route('decisions') }}"  class="block px-4 py-2 text-gray-700">Решения общего собрания</a>
                            <a href="{{ route('votings') }}"  class="block px-4 py-2 text-gray-700">Голосования</a>
                        </div>
                        <div>
                            <p class="px-4 pb-1 text-xs uppercase tracking-wide text-gray-400">Помощь</p>
                            @if(request()->routeIs('help'))
                            <a href="{{ route('help') }}" @click.prevent="open = false; $dispatch('switch-help-tab', { tab: 'guide' })" class="block px-4 py-2 text-gray-700 cursor-pointer">Помощь по сайту</a>
                            <a href="{{ route('help') }}#users" @click.prevent="open = false; $dispatch('switch-help-tab', { tab: 'users' })" class="block px-4 py-2 text-gray-700 cursor-pointer">F.A.Q.</a>
                            <a href="{{ route('help') }}#owners" @click.prevent="open = false; $dispatch('switch-help-tab', { tab: 'owners' })" class="block px-4 py-2 text-gray-700 cursor-pointer">Для владельцев яхт</a>
                            @else
                            <a href="{{ route('help') }}"  class="block px-4 py-2 text-gray-700">Помощь по сайту</a>
                            <a href="{{ route('help') }}#users"  class="block px-4 py-2 text-gray-700">F.A.Q.</a>
                            <a href="{{ route('help') }}#owners"  class="block px-4 py-2 text-gray-700">Для владельцев яхт</a>
                            @endif
                            <p class="px-4 pt-3 pb-1 text-xs uppercase tracking-wide text-gray-400">Связь</p>
                            <button type="button" @click="open = false; isQuestionModalOpen = true" class="block w-full text-left px-4 py-2 text-gray-700 cursor-pointer">Задать вопрос</button>
                        </div>
                    </div>
                </div>
                <div x-data="{ open: false }" class="relative" @mouseenter="open = true" @mouseleave="open = false">
                    <button @click="open = !open"
                        class="flex items-center gap-1 px-3 py-2 text-sm text-[#2E325C] transition-colors">
                        Соревнования
                        <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-cloak x-transition
                        {{-- Шире прочих меню: названия бирж в w-52 не помещаются. --}}
                        class="absolute top-full left-0 mt-1 w-136 grid grid-cols-2 gap-x-2 bg-white rounded-lg shadow-xl border border-gray-100 py-2 z-50">
                        <div>
                            {{-- Порядок подразделов задан ТЗ 3-го этапа, п. 8.1. --}}
                            <p class="px-4 pb-1 text-xs uppercase tracking-wide text-gray-400">Соревнования</p>
                            @if(request()->routeIs('competitions'))
                            <a href="{{ route('competitions') }}" @click.prevent="open = false; $dispatch('switch-competitions-tab', { tab: 'calendar' })" class="block px-4 py-2 text-gray-700 cursor-pointer">Календарь</a>
                            @else
                            <a href="{{ route('competitions') }}"  class="block px-4 py-2 text-gray-700">Календарь</a>
                            @endif
                            <a href="{{ route('series') }}" class="block px-4 py-2 text-gray-700">Серии</a>
                            <a href="{{ route('regatta-entries') }}" class="block w-full text-left px-4 py-2 text-gray-700">Заявки</a>
                            @if(request()->routeIs('competitions'))
                            <a href="{{ route('competitions') }}#results" @click.prevent="open = false; $dispatch('switch-competitions-tab', { tab: 'results' })" class="block px-4 py-2 text-gray-700 cursor-pointer">Результаты</a>
                            @else
                            <a href="{{ route('competitions') }}#results"  class="block px-4 py-2 text-gray-700">Результаты</a>
                            @endif
                            <p class="px-4 pt-3 pb-1 text-xs uppercase tracking-wide text-gray-400">Рейтинги</p>
                            @if(request()->routeIs('ratings'))
                            <a href="{{ route('ratings') }}" @click.prevent="open = false; $dispatch('switch-ratings-tab', { tab: 'team' })" class="block px-4 py-2 text-gray-700 cursor-pointer">Командный</a>
                            <a href="{{ route('ratings') }}#personal" @click.prevent="open = false; $dispatch('switch-ratings-tab', { tab: 'personal' })" class="block px-4 py-2 text-gray-700 cursor-pointer">Личный</a>
                            @else
                            <a href="{{ route('ratings') }}"  class="block px-4 py-2 text-gray-700">Командный</a>
                            <a href="{{ route('ratings') }}#personal"  class="block px-4 py-2 text-gray-700">Личный</a>
                            @endif
                        </div>
                        <div>
                            <p class="px-4 pb-1 text-xs uppercase tracking-wide text-gray-400">Биржи</p>
                            @foreach (\App\Enums\AdvertType::competitionBoards() as $board)
                            <a href="{{ route($board->routeName()) }}" class="block px-4 py-2 text-gray-700">{{ $board->label() }}</a>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div x-data="{ open: false }" class="relative" @mouseenter="open = true" @mouseleave="open = false">
                    <button @click="open = !open"
                        class="flex items-center gap-1 px-3 py-2 text-sm text-[#2E325C] transition-colors">
                        Carter&nbsp;30
                        <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-cloak x-transition
                        class="absolute top-full left-0 mt-1 w-56 bg-white rounded-lg shadow-xl border border-gray-100 py-1 z-50">
                        <a href="{{ route('carter30.history') }}" class="block px-4 py-2 text-gray-700">История</a>
                        <a href="{{ route('carter30.regulations') }}" class="block px-4 py-2 text-gray-700">Технический регламент класса</a>
                        <a href="{{ route('carter30.repair') }}" class="block px-4 py-2 text-gray-700">Ремонт и модернизация</a>
                        <a href="{{ route('carter30.technical-help') }}" class="block px-4 py-2 text-gray-700">Техническая помощь</a>
                        <a href="{{ route('carter30.marketplace') }}" class="block px-4 py-2 text-gray-700">Барахолка</a>
                        <a href="{{ route('carter30.yacht-sale') }}" class="block px-4 py-2 text-gray-700">Продать яхту</a>
                    </div>
                </div>
                <div x-data="{ open: false }" class="relative" @mouseenter="open = true" @mouseleave="open = false">
                    <button @click="open = !open"
                        class="flex items-center gap-1 px-3 py-2 text-sm text-[#2E325C] transition-colors">
                        {{ \App\Enums\ServiceType::hubLabel() }}
                        <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-cloak x-transition
                        class="absolute top-full left-0 mt-1 w-56 bg-white rounded-lg shadow-xl border border-gray-100 py-1 z-50">
                        @foreach (\App\Enums\ServiceType::published() as $service)
                            <a href="{{ $service->url() }}" class="block px-4 py-2 text-gray-700">{{ $service->label() }}</a>
                        @endforeach
                        <a href="{{ route('services.index') }}" class="block px-4 py-2 text-gray-700 border-t border-gray-100">Все услуги</a>
                    </div>
                </div>
                <div x-data="{ open: false }" class="relative" @mouseenter="open = true" @mouseleave="open = false">
                    <button @click="open = !open"
                        class="flex items-center gap-1 px-3 py-2 text-sm text-[#2E325C] transition-colors">
                        Флот
                        <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-cloak x-transition
                        class="absolute top-full left-0 mt-1 w-52 bg-white rounded-lg shadow-xl border border-gray-100 py-1 z-50">
                        <a href="{{ route('teams') }}" class="block px-4 py-2 text-gray-700">Команды</a>
                        <a href="{{ route('yachts') }}" class="block px-4 py-2 text-gray-700">Яхты</a>
                    </div>
                </div>
                <div x-data="{ open: false }" class="relative" @mouseenter="open = true" @mouseleave="open = false">
                    <button @click="open = !open"
                        class="flex items-center gap-1 px-3 py-2 text-sm text-[#2E325C] transition-colors">
                        Новости
                        <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-cloak x-transition
                        class="absolute top-full right-0 mt-1 w-52 bg-white rounded-lg shadow-xl border border-gray-100 py-1 z-50">
                        <a href="{{ route('news') }}"  class="block px-4 py-2 text-gray-700">Новости ассоциации</a>
                        <a href="{{ route('world-news') }}" class="block px-4 py-2 text-gray-700">Новости парусного мира</a>
                        <a href="{{ route('press') }}"  class="block px-4 py-2 text-gray-700">Пресса о нас</a>
                        <a href="{{ route('gallery') }}" class="block px-4 py-2 text-gray-700 border-t border-gray-100">Галерея</a>
                    </div>
                </div>
            </div>

            {{-- Действия --}}
            <div class="hidden nav-social xl:flex items-center gap-2">
                <div class="flex items-center gap-2">
                    <a href="https://t.me/a_carterpro" class="text-[#2D92CE]" target="_blank">
                        {!! \App\Support\Svg::inline('images/social_icons/tl.svg') !!}
                    </a>
                    <a href="https://vk.com/carter_pro" class="text-[#2D92CE]" target="_blank">
                        {!! \App\Support\Svg::inline('images/social_icons/vk.svg') !!}
                    </a>
                </div>


                @auth
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" @click.outside="open = false"
                        class="flex items-center gap-2 px-2 py-1 rounded-lg transition-colors">
                        <img src="{{ auth()->user()->photo_url ? asset('storage/' . auth()->user()->photo_url) : asset('images/icons/avatar-default.svg') }}"
                            alt="" class="w-8 h-8 rounded-full object-cover border-2 border-gray-200">
                        <span class="text-sm font-medium text-[#2E325C] hidden md:inline">{{ auth()->user()->name }}</span>
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
                        @if(auth()->user()->canAccessAdminPanel())
                            <a href="{{ url('/admin') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-brand-light hover:text-brand-red">Панель управления</a>
                            <a href="{{ url('/user') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-brand-light hover:text-brand-red">Профиль пользователя</a>
                            <div class="border-t border-gray-100 my-1"></div>
                            @foreach(\App\Support\AccessControl::adminMenuLinks() as $link)
                                <a href="{{ $link['url'] }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-brand-light hover:text-brand-red">{{ $link['label'] }}</a>
                            @endforeach
                        @else
                            <a href="{{ url('/user') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-brand-light hover:text-brand-red">Личный кабинет</a>
                            <div class="border-t border-gray-100 my-1"></div>
                            <a href="{{ url('/user/yachts') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-brand-light hover:text-brand-red">Мои яхты</a>
                            <a href="{{ url('/user/teams') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-brand-light hover:text-brand-red">Мои команды</a>
                            <a href="{{ url('/user/regatta-entries') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-brand-light hover:text-brand-red">Заявки на регаты</a>
                            <a href="{{ url('/user/regatta-result-items') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-brand-light hover:text-brand-red">Результаты</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-brand-light hover:text-brand-red">Выйти</button>
                        </form>
                    </div>
                </div>
                @else
                <a href="#" @click="$dispatch('open-login-modal')" class="text-[#2D92CE] text-lg font-semibold px-4 py-2 transition-colors border-[#2D92CE] border flex gap-2 login-btn items-center">
                    {!! \App\Support\Svg::inline('images/icons/login.svg') !!} <span class="hidden md:inline">Войти</span>
                </a>
                @endauth
            </div>

            {{-- Мобильное меню --}}
            <button @click="mobileOpen = !mobileOpen" class="xl:hidden p-2 text-gray-300">
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
        
        class="xl:hidden fixed inset-0 bg-black/50 z-40 w-screen "
    >
        <div
        x-transition:enter="transition transform ease-out duration-300"
        x-transition:enter-start="translate-y-120 opacity-0"
        x-transition:enter-end="translate-y-80 opacity-100"
        x-transition:leave="transition transform ease-in duration-200"
        x-transition:leave-start="translate-y-80 opacity-100"
        x-transition:leave-end="translate-y-120 opacity-0"
        x-transition class="xl:hidden bg-[#2E325C] py-2 px-4 space-y-1  min-w-[220px] h-screen text-white fixed right-0 "
        @click.outside="mobileOpen=false"
        >
            <div class="flex justify-between items-center mt-4 mb-4">
                <h3 class="uppercase a-font text-xl">Меню</h3>
                <button @click="mobileOpen = false" class="text-2xl font-bold">{!! \App\Support\Svg::inline('images/icons/close.svg') !!}</button>
            </div>
            <div class="max-h-[85dvh] overflow-y-auto">
                <div class="space-y-2">
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open"
                            class="flex items-center gap-1  py-2 text-sm transition-colors">
                            Ассоциация
                            <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" x-cloak x-transition
                            class="">
                            <p class="px-4 pt-1 pb-1 text-xs uppercase tracking-wide text-white/50">Об ассоциации</p>
                            <a href="{{ route('charter') }}"  class="block px-4 py-2 text-sm">Об Ассоциации</a>
                            <a href="{{ route('management') }}"  class="block px-4 py-2 text-sm">Руководство Ассоциации</a>
                            <a href="{{ route('trustees') }}"  class="block px-4 py-2 text-sm">Попечительский совет</a>
                            <a href="{{ route('policy') }}"  class="block px-4 py-2 text-sm">Политика Ассоциации</a>
                            <p class="px-4 pt-3 pb-1 text-xs uppercase tracking-wide text-white/50">Документы</p>
                            <a href="{{ route('rules') }}"  class="block px-4 py-2 text-sm">Правила вступления</a>
                            <a href="{{ route('regulations') }}"  class="block px-4 py-2 text-sm">Технический регламент яхт</a>
                            <a href="{{ route('decisions') }}"  class="block px-4 py-2 text-sm">Решения общего собрания</a>
                            <a href="{{ route('votings') }}"  class="block px-4 py-2 text-sm">Голосования</a>
                            <p class="px-4 pt-3 pb-1 text-xs uppercase tracking-wide text-white/50">Помощь</p>
                            @if(request()->routeIs('help'))
                            <a href="{{ route('help') }}" @click.prevent="mobileOpen = false; $dispatch('switch-help-tab', { tab: 'guide' })" class="block px-4 py-2 text-sm">Помощь по сайту</a>
                            <a href="{{ route('help') }}#users" @click.prevent="mobileOpen = false; $dispatch('switch-help-tab', { tab: 'users' })" class="block px-4 py-2 text-sm">F.A.Q.</a>
                            <a href="{{ route('help') }}#owners" @click.prevent="mobileOpen = false; $dispatch('switch-help-tab', { tab: 'owners' })" class="block px-4 py-2 text-sm">Для владельцев яхт</a>
                            @else
                            <a href="{{ route('help') }}"  class="block px-4 py-2 text-sm">Помощь по сайту</a>
                            <a href="{{ route('help') }}#users"  class="block px-4 py-2 text-sm">F.A.Q.</a>
                            <a href="{{ route('help') }}#owners"  class="block px-4 py-2 text-sm">Для владельцев яхт</a>
                            @endif
                            <button type="button" @click="mobileOpen = false; isQuestionModalOpen = true" class="block w-full text-left px-4 py-2 text-sm cursor-pointer">Задать вопрос</button>
                        </div>
                    </div>
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open"
                            class="flex items-center gap-1 py-2 text-sm transition-colors">
                            Соревнования
                            <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" x-cloak x-transition class="">
                            {{-- Порядок подразделов задан ТЗ 3-го этапа, п. 8.1. --}}
                            @if(request()->routeIs('competitions'))
                            <a href="{{ route('competitions') }}" @click.prevent="mobileOpen = false; $dispatch('switch-competitions-tab', { tab: 'calendar' })" class="block px-4 py-2 text-sm">Календарь</a>
                            @else
                            <a href="{{ route('competitions') }}"  class="block px-4 py-2 text-sm">Календарь</a>
                            @endif
                            <a href="{{ route('series') }}" class="block px-4 py-2 text-sm">Серии</a>
                            <a href="{{ route('regatta-entries') }}" class="block w-full text-left px-4 py-2 text-sm">Заявки</a>
                            @if(request()->routeIs('competitions'))
                            <a href="{{ route('competitions') }}#results" @click.prevent="mobileOpen = false; $dispatch('switch-competitions-tab', { tab: 'results' })" class="block px-4 py-2 text-sm">Результаты</a>
                            @else
                            <a href="{{ route('competitions') }}#results"  class="block px-4 py-2 text-sm">Результаты</a>
                            @endif
                            <p class="px-4 pt-3 pb-1 text-xs uppercase tracking-wide text-white/50">Рейтинги</p>
                            @if(request()->routeIs('ratings'))
                            <a href="{{ route('ratings') }}" @click.prevent="mobileOpen = false; $dispatch('switch-ratings-tab', { tab: 'team' })" class="block px-4 py-2 text-sm">Командный</a>
                            <a href="{{ route('ratings') }}#personal" @click.prevent="mobileOpen = false; $dispatch('switch-ratings-tab', { tab: 'personal' })" class="block px-4 py-2 text-sm">Личный</a>
                            @else
                            <a href="{{ route('ratings') }}"  class="block px-4 py-2 text-sm">Командный</a>
                            <a href="{{ route('ratings') }}#personal"  class="block px-4 py-2 text-sm">Личный</a>
                            @endif
                            <p class="px-4 pt-3 pb-1 text-xs uppercase tracking-wide text-white/50">Биржи</p>
                            @foreach (\App\Enums\AdvertType::competitionBoards() as $board)
                            <a href="{{ route($board->routeName()) }}" class="block px-4 py-2 text-sm">{{ $board->label() }}</a>
                            @endforeach
                        </div>
                    </div>
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open"
                            class="flex items-center gap-1 py-2 text-sm transition-colors">
                            Carter&nbsp;30
                            <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" x-cloak x-transition class="">
                            <a href="{{ route('carter30.history') }}" class="block px-4 py-2 text-sm">История</a>
                            <a href="{{ route('carter30.regulations') }}" class="block px-4 py-2 text-sm">Технический регламент класса</a>
                            <a href="{{ route('carter30.repair') }}" class="block px-4 py-2 text-sm">Ремонт и модернизация</a>
                            <a href="{{ route('carter30.technical-help') }}" class="block px-4 py-2 text-sm">Техническая помощь</a>
                            <a href="{{ route('carter30.marketplace') }}" class="block px-4 py-2 text-sm">Барахолка</a>
                            <a href="{{ route('carter30.yacht-sale') }}" class="block px-4 py-2 text-sm">Продать яхту</a>
                        </div>
                    </div>
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open"
                            class="flex items-center gap-1 py-2 text-sm transition-colors">
                            {{ \App\Enums\ServiceType::hubLabel() }}
                            <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" x-cloak x-transition class="">
                            @foreach (\App\Enums\ServiceType::published() as $service)
                                <a href="{{ $service->url() }}" class="block px-4 py-2 text-sm">{{ $service->label() }}</a>
                            @endforeach
                            <a href="{{ route('services.index') }}" class="block px-4 py-2 text-sm">Все услуги</a>
                        </div>
                    </div>
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open"
                            class="flex items-center gap-1 py-2 text-sm transition-colors">
                            Флот
                            <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" x-cloak x-transition class="">
                            <a href="{{ route('teams') }}" class="block px-4 py-2 text-sm">Команды</a>
                            <a href="{{ route('yachts') }}" class="block px-4 py-2 text-sm">Яхты</a>
                        </div>
                    </div>
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open"
                            class="flex items-center gap-1 py-2 text-sm transition-colors">
                            Новости
                            <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" x-cloak x-transition class="">
                            <a href="{{ route('news') }}"  class="block px-4 py-2 text-sm">Новости ассоциации</a>
                            <a href="{{ route('world-news') }}" class="block px-4 py-2 text-sm">Новости парусного мира</a>
                            <a href="{{ route('press') }}"  class="block px-4 py-2 text-sm">Пресса о нас</a>
                            <a href="{{ route('gallery') }}" class="block px-4 py-2 text-sm">Галерея</a>
                        </div>
                    </div>
                </div>
                
                @auth
                <!--<div class="flex items-center gap-3 py-2 border-b border-white/20 mb-2">
                    <img src="{{ auth()->user()->photo_url ? asset('storage/' . auth()->user()->photo_url) : asset('images/icons/avatar-default.svg') }}"
                        alt="" class="w-10 h-10 rounded-full object-cover border-2 border-white/30">
                    <div>
                        <p class="text-sm font-medium">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-gray-300">{{ auth()->user()->email }}</p>
                    </div>
                </div>-->
                @if(auth()->user()->canAccessAdminPanel())
                    <a href="{{ url('/admin') }}" class="block py-2 text-sm">Панель управления</a>
                    <a href="{{ url('/user') }}" class="block py-2 text-sm">Профиль пользователя</a>
                    @foreach(\App\Support\AccessControl::adminMenuLinks() as $link)
                        <a href="{{ $link['url'] }}" class="block py-2 text-sm pl-3 text-white/80">— {{ $link['label'] }}</a>
                    @endforeach
                    <a href="{{ url('/admin/profile') }}" class="block py-2 text-sm pl-3 text-white/80">Профиль</a>
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
                    {!! \App\Support\Svg::inline('images/icons/login.svg') !!} Войти
                </a>
                @endauth
            </div>


        </div>
    </div>

</nav>
