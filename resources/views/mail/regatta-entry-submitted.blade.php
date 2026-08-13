<x-mail::message>
# Новая заявка на регату

Подана новая заявка на участие в регате **{{ $entry->regatta->name }}**.

| Поле | Значение |
|------|----------|
| **Регата** | {{ $entry->regatta->name }} |
| **Заявитель** | {{ $entry->participantName() }} |
@if (! $entry->team)
{{-- Индивидуальные и сборные заявки идут без команды: связываться приходится по контактам заявителя. --}}
| **Участие** | {{ $entry->participation_kind->getLabel() }} |
| **Контакты** | {{ $entry->applicantContacts() ?? '—' }} |
| **Человек в заявке** | {{ $entry->crew->count() }} |
@else
| **Команда** | {{ $entry->team->name }} |
@endif
| **Яхта** | {{ $entry->yacht?->name ?? '—' }} |
| **Дата подачи** | {{ optional($entry->submitted_at)->format('d.m.Y H:i') ?? $entry->created_at->format('d.m.Y H:i') }} |

<x-mail::button :url="route('competition-details', $entry->regatta)">
Перейти к регате
</x-mail::button>

</x-mail::message>
