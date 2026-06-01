<x-mail::message>
# Новая заявка с сайта

| Поле | Значение |
|------|----------|
| **Имя** | {{ $feedback->name }} |
| **Телефон** | {{ $feedback->phone }} |
@if($feedback->email)
| **Email** | {{ $feedback->email }} |
@endif
@if($feedback->message)
| **Сообщение** | {{ $feedback->message }} |
@endif
| **Источник** | {{ $feedback->source }} |
| **Дата** | {{ $feedback->created_at->format('d.m.Y H:i') }} |


</x-mail::message>