<x-mail::message>
# Зарегистрирована новая яхта

На сайте зарегистрирована новая яхта.

| Поле | Значение |
|------|----------|
| **Название** | {{ $yacht->name }} |
@if($yacht->vfps_number)
| **Номер паруса (ВФПС)** | {{ $yacht->vfps_number }} |
@endif
| **Владелец** | {{ $yacht->user?->name ?? '—' }} |
@if($yacht->user?->email)
| **E-mail владельца** | {{ $yacht->user->email }} |
@endif
| **Дата регистрации** | {{ $yacht->created_at->format('d.m.Y H:i') }} |

</x-mail::message>
