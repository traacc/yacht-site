<x-mail::message>
# Новая заявка на регату

Подана новая заявка на участие в регате **{{ $entry->regatta->name }}**.

| Поле | Значение |
|------|----------|
| **Регата** | {{ $entry->regatta->name }} |
| **Команда** | {{ $entry->team?->name ?? '—' }} |
| **Яхта** | {{ $entry->yacht?->name ?? '—' }} |
| **Дата подачи** | {{ optional($entry->submitted_at)->format('d.m.Y H:i') ?? $entry->created_at->format('d.m.Y H:i') }} |

<x-mail::button :url="route('competition-details', $entry->regatta)">
Перейти к регате
</x-mail::button>

</x-mail::message>
