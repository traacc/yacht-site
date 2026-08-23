<x-mail::message>
# Заявка принята

{{ $serviceRequest->name }}, спасибо за обращение — мы получили вашу заявку
на услугу «{{ $serviceRequest->type->label() }}» и свяжемся с вами в ближайшее время.

| Поле | Значение |
|------|----------|
| **Услуга** | {{ $serviceRequest->type->label() }} |
@if($serviceRequest->subjectLabel())
| **Объект** | {{ $serviceRequest->subjectLabel() }} |
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
| **Телефон для связи** | {{ $serviceRequest->phone }} |
| **Дата заявки** | {{ $serviceRequest->created_at->format('d.m.Y H:i') }} |

@if($serviceRequest->comment)
**Ваш комментарий:**

{{ $serviceRequest->comment }}
@endif

Расчёт в заявке ориентировочный: итоговую стоимость подтверждает менеджер
после согласования программы.

@if($serviceRequest->type->url())
<x-mail::button :url="$serviceRequest->type->url()">
Вернуться к услуге
</x-mail::button>
@endif
</x-mail::message>
