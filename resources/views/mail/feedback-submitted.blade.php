<x-mail::message>
# Запрос на обратную связь

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
@if($feedback->page_title)
| **Страница** | {{ $feedback->page_title }} |
@endif
| **Источник** | {{ $feedback->source }} |
| **Дата** | {{ $feedback->created_at->format('d.m.Y H:i') }} |


</x-mail::message>