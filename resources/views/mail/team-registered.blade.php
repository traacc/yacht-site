<x-mail::message>
# Зарегистрирована новая команда

На сайте зарегистрирована новая команда.

| Поле | Значение |
|------|----------|
| **Название** | {{ $team->name }} |
| **Организатор** | {{ $team->organizer?->name ?? '—' }} |
@if($team->organizer?->email)
| **E-mail организатора** | {{ $team->organizer->email }} |
@endif
| **Дата регистрации** | {{ $team->created_at->format('d.m.Y H:i') }} |

</x-mail::message>
