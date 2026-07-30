<x-mail::message>
# {{ $title }}

@if ($imageUrl)
{{-- Обложка. Ширина в атрибуте обязательна: Outlook (движок Word) игнорирует
     CSS и иначе растянет картинку по натуральному размеру. 506px — ширина
     контентной области письма (570 минус отступы по 32px). --}}
<a href="{{ $url ?? config('app.url') }}" style="display: block;"><img src="{{ $imageUrl }}" alt="{{ $title }}" width="506" style="width: 100%; max-width: 506px; height: auto; border: 0; border-radius: 4px; display: block; margin-bottom: 18px;"></a>
@endif

{{ $body }}

@if ($url)
<x-mail::button :url="$url">
Открыть на сайте
</x-mail::button>
@endif

@component('mail::subcopy')
Вы получаете это письмо, потому что подписаны на уведомления категории «{{ $category->getLabel() }}» на сайте {{ config('mail.brand.name') }}.

[Отписаться от этой рассылки]({{ $unsubscribeUrl }}) · [Настроить уведомления]({{ route('filament.user.pages.notification-settings') }})
@endcomponent
</x-mail::message>
