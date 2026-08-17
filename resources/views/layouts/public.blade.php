<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $metaTitle = $title ?? 'Регаты CarterPro';
        $metaDescription = $description ?? 'Календарь гонок, рейтинги, правила и новости парусного спорта. Официальный сайт CarterPro: регистрация на гонки!';
        $metaImage = $ogImage ?? asset('favicon.jpg');
    @endphp
    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">

    {{-- Open Graph --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="CarterPro">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ $metaImage }}">
    <meta property="og:locale" content="ru_RU">

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $metaTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    <meta name="twitter:image" content="{{ $metaImage }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
    @livewireScripts
    <style>
        [x-cloak] { display: none !important; }
        .nav-link { @apply text-sm font-medium text-gray-700 hover:text-brand-red transition-colors; }
    </style>
    <meta name="mailru-verification" content="cfd9f51b9ce97857" />
    <link rel="preload" href="{{ Vite::asset('resources/fonts/TTLakesCondensed-DemiBold.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ Vite::asset('resources/fonts/Montserrat-Regular.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="shortcut icon" href="{{ asset('favicon.jpg?v=4') }}?v=2" type="image/svg+xml">
    @if (\App\Rules\YandexCaptcha::enabled())
        {{-- Реестр виджетов Yandex SmartCaptcha. Скрипт капчи грузится асинхронно,
             поэтому формы не вызывают smartCaptcha.render() напрямую, а ставят
             отрисовку в очередь; она выполнится в onload-колбэке. Виджеты
             хранятся по имени, чтобы форма могла сбросить свой (токен одноразовый,
             после неудачной отправки его нужно получать заново). --}}
        <script>
            window.yandexCaptcha = {
                loaded: false,
                queue: [],
                widgets: {},
                onLoad(callback) {
                    this.loaded ? callback() : this.queue.push(callback);
                },
                render(name, container, params) {
                    this.onLoad(() => {
                        this.widgets[name] = window.smartCaptcha.render(container, params);
                    });
                },
                reset(name) {
                    const widgetId = this.widgets[name];

                    if (widgetId !== undefined) {
                        window.smartCaptcha.reset(widgetId);
                    }
                },
            };

            window.yandexCaptchaOnLoad = function () {
                window.yandexCaptcha.loaded = true;
                window.yandexCaptcha.queue.splice(0).forEach((callback) => callback());
            };
        </script>
        <script src="https://smartcaptcha.yandexcloud.net/captcha.js?render=onload&amp;onload=yandexCaptchaOnLoad" defer></script>
    @endif
    <meta name="yandex-verification" content="4bd7f3f3aecedff0" />
    <meta name="google-site-verification" content="A8qH64mGazrMuvqwvvGCQvLR5xkkqBGqa7Unkg39JSs" />
</head>
<body class="font-sans bg-white text-[#2E325C] antialiased" x-data="{isRequestModalOpen: false, isQuestionModalOpen: false }">

<x-nav />

@if (session('warning'))
<div
    x-data="{ show: true }"
    x-show="show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-x-full"
    x-transition:enter-end="opacity-100 translate-x-0"
    x-transition:leave="transition ease-in duration-300"
    x-transition:leave-start="opacity-100 translate-x-0"
    x-transition:leave-end="opacity-0 translate-x-full"
    x-init="setTimeout(() => show = false, 10000)"
    x-init=""
    class="fixed top-20 right-4 z-50 bg-white text-sm md:text-xl text-[#2E325C] px-6 py-4 shadow-lg max-w-sm flex items-center gap-3"
>
    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
    </svg>
    <span class="text-sm font-medium">{{ session('warning') }}</span>
    <button @click="show = false" class="ml-auto text-white/70 hover:text-white shrink-0">&times;</button>
</div>
@endif

@if(session('registered'))
<div
    x-data="{ show: true }"
    x-show="show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-x-full"
    x-transition:enter-end="opacity-100 translate-x-0"
    x-transition:leave="transition ease-in duration-300"
    x-transition:leave-start="opacity-100 translate-x-0"
    x-transition:leave-end="opacity-0 translate-x-full"
    x-init="setTimeout(() => show = false, 10000)"
    class="fixed top-20 right-4 z-50 bg-white text-sm md:text-xl text-[#2E325C] px-6 py-4 shadow-lg max-w-sm flex items-center gap-3"
>
    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
    </svg>
    <span class="text-sm font-medium">Поздравляем, теперь вы стали членом ассоциации!</span>
    <button @click="show = false" class="ml-auto text-white/70 hover:text-white shrink-0">&times;</button>
</div>
@endif

{{ $slot }}
<x-request-modal></x-request-modal>
<x-question-modal></x-question-modal>
<x-footer />
<livewire:auth.login-modal />
<livewire:join-regatta-modal />
<livewire:user-card-modal />
<livewire:team-card-modal />
<livewire:entry-crew-modal />
<livewire:crew-join-modal />
<livewire:seat-entry-modal />
<livewire:participation-wizard />
<livewire:cookie-consent />
<livewire:chat.support-chat-widget />

</body>
</html>