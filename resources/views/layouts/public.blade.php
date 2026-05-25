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
    <link rel="shortcut icon" href="{{ asset('favicon.svg') }}?v=2" type="image/svg+xml">
</head>
<body class="font-sans bg-white text-[#2E325C] antialiased" x-data="{isRequestModalOpen: false }">

<x-nav />

{{ $slot }}
<x-request-modal></x-request-modal>
<x-footer />
<livewire:auth.login-modal />
<livewire:join-regatta-modal />

</body>
</html>