<x-mail::message>
# Новое сообщение в поддержку

| Поле | Значение |
|------|----------|
| **Автор** | {{ $message->authorName() }} |
@if($message->author?->email)
| **Email автора** | {{ $message->author->email }} |
@endif
@if($conversation?->title)
| **Тема обращения** | {{ $conversation->title }} |
@endif
| **Дата** | {{ $message->created_at->format('d.m.Y H:i') }} |
@if($attachmentsCount > 0)
| **Вложения** | {{ $attachmentsCount }} файл(ов) — смотрите в чате |
@endif

@if($message->body)
**Сообщение:**

{{ $message->body }}
@endif

<x-mail::button :url="$answerUrl">
Ответить в чате поддержки
</x-mail::button>
</x-mail::message>
