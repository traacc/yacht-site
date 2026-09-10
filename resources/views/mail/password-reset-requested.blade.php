<x-mail::message>
# Запрос на восстановление пароля

Пользователь запросил восстановление пароля через форму на сайте.

| Поле | Значение |
|------|----------|
| **Пользователь** | {{ $request->requesterName() ?: 'не найден в базе' }} |
| **E-mail** | {{ $request->email }} |
| **Телефон** | {{ $request->phone }} |
| **Дата** | {{ $request->created_at->format('d.m.Y H:i') }} |

<x-mail::button :url="$answerUrl">
Ответить в админке
</x-mail::button>
</x-mail::message>
