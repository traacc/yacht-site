<x-mail::message>
# {{ $title }}

{{ $body }}

@if ($url)
<x-mail::button :url="$url">
Открыть на сайте
</x-mail::button>
@endif

@component('mail::subcopy')
Вы получаете это письмо, потому что подписаны на уведомления категории «{{ $category->getLabel() }}» на сайте Carter Pro.

[Отписаться от этой рассылки]({{ $unsubscribeUrl }}) · [Настроить уведомления]({{ route('filament.user.pages.notification-settings') }})
@endcomponent
</x-mail::message>
