<x-mail::message>
# {{ $serviceRequest->type->label() }}

| Поле | Значение |
|------|----------|
| **Услуга** | {{ $serviceRequest->type->label() }} |
| **Имя** | {{ $serviceRequest->name }} |
| **Телефон** | {{ $serviceRequest->phone }} |
@if($serviceRequest->email)
| **Email** | {{ $serviceRequest->email }} |
@endif
@if($serviceRequest->dateRangeLabel())
| **Даты** | {{ $serviceRequest->dateRangeLabel() }} |
@endif
@if($serviceRequest->quantity !== null)
| **{{ $serviceRequest->type->quantityLabel() }}** | {{ $serviceRequest->quantity }} |
@endif
@foreach($serviceRequest->payloadLabels() as $label => $value)
| **{{ $label }}** | {{ $value }} |
@endforeach
@if($serviceRequest->user)
| **Пользователь сайта** | {{ $serviceRequest->user->name }} |
@endif
| **Дата** | {{ $serviceRequest->created_at->format('d.m.Y H:i') }} |

@if($serviceRequest->comment)
**Комментарий:**

{{ $serviceRequest->comment }}
@endif

<x-mail::button :url="$adminUrl">
Открыть в админке
</x-mail::button>
</x-mail::message>
