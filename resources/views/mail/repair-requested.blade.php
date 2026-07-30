<x-mail::message>
# Заявка на ремонт и модернизацию

| Поле | Значение |
|------|----------|
@if($repairRequest->repairCase)
| **Кейс** | {{ $repairRequest->repairCase->title }} |
@else
| **Кейс** | Обзорная страница раздела |
@endif
| **Имя** | {{ $repairRequest->name }} |
| **Телефон** | {{ $repairRequest->phone }} |
@if($repairRequest->email)
| **Email** | {{ $repairRequest->email }} |
@endif
@if($repairRequest->user)
| **Пользователь сайта** | {{ $repairRequest->user->name }} |
@endif
| **Дата** | {{ $repairRequest->created_at->format('d.m.Y H:i') }} |

@if($repairRequest->comment)
**Комментарий:**

{{ $repairRequest->comment }}
@endif

<x-mail::button :url="$adminUrl">
Открыть в админке
</x-mail::button>
</x-mail::message>
