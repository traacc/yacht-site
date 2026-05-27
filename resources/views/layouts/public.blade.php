<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регаты CarterPro</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
    @livewireScripts
    <style>
        [x-cloak] { display: none !important; }
        .nav-link { @apply text-sm font-medium text-gray-700 hover:text-brand-red transition-colors; }
    </style>
    <link rel="preload" href="{{ Vite::asset('resources/fonts/BankGothic-Medium.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ Vite::asset('resources/fonts/Montserrat-Regular.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="shortcut icon" href="{{ asset('favicon.jpg?v=3') }}?v=2" type="image/svg+xml">
    <script src="https://smartcaptcha.yandexcloud.net/captcha.js" defer></script>
</head>
<body class="font-sans bg-white text-[#2E325C] antialiased" x-data="{isRequestModalOpen: false }">

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
<x-footer />
<livewire:auth.login-modal />
<livewire:join-regatta-modal />

</body>
</html>