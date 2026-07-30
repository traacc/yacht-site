<x-mail::message>
# Новый вопрос с сайта

| Поле | Значение |
|------|----------|
| **Автор** | {{ $userQuestion->user?->name ?? 'Неизвестен' }} |
@if($userQuestion->user?->email)
| **Email автора** | {{ $userQuestion->user->email }} |
@endif
| **Дата** | {{ $userQuestion->created_at->format('d.m.Y H:i') }} |

**Вопрос:**

{{ $userQuestion->question }}

<x-mail::button :url="$answerUrl">
Ответить в админке
</x-mail::button>
</x-mail::message>
