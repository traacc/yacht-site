@php
    $entry = $joinRequest->regattaEntry;
@endphp
<x-mail::message>
# Заявка в экипаж

{{ $joinRequest->name }} хочет присоединиться к экипажу
@if ($entry->team) «{{ $entry->team->name }}» @endif
на регате **{{ $entry->regatta->name }}**.

| Поле | Значение |
|------|----------|
| **Регата** | {{ $entry->regatta->name }} |
| **Экипаж** | {{ $entry->team?->name ?? '—' }} |
| **Имя** | {{ $joinRequest->name }} |
| **E-mail** | {{ $joinRequest->email }} |
| **Телефон** | {{ $joinRequest->phone ?? '—' }} |
| **Дата отклика** | {{ $joinRequest->created_at->format('d.m.Y H:i') }} |

@if (filled($joinRequest->message))
**Сообщение от кандидата:**

{{ $joinRequest->message }}
@endif

@if (filled($entry->join_conditions))
**Условия, объявленные экипажем:**

{{ $entry->join_conditions }}
@endif

<x-mail::button :url="route('competition-details', $entry->regatta)">
Перейти к регате
</x-mail::button>

</x-mail::message>
