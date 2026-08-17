{{--
    Виджет Yandex SmartCaptcha.

    name     — уникальное на странице имя виджета; по нему форма сбрасывает
               капчу через window.yandexCaptcha.reset('<name>') (токен
               одноразовый: после неудачной отправки нужен новый).
    callback — JS-выражение, выполняемое при получении токена. Вычисляется в
               области Alpine этого элемента, токен доступен как `token`,
               внутри Livewire-компонента доступен `$wire`.

    Реестр window.yandexCaptcha объявлен в layouts/public.blade.php.
--}}
@props(['name', 'callback'])

@if (\App\Rules\YandexCaptcha::enabled())
    <div wire:ignore
         x-data
         x-init="window.yandexCaptcha.render(@js($name), $el, {
             sitekey: @js(config('services.yandex_captcha.site_key')),
             hl: 'ru',
             callback: (token) => { {!! $callback !!} },
         })"
         {{ $attributes->merge(['class' => 'mt-4']) }}></div>
@endif
