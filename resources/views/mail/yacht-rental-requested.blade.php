<x-mail::message>
# Новый запрос на аренду яхты

| Поле | Значение |
|------|----------|
| **Яхта** | {{ $rentalRequest->yacht?->name ?? '—' }} |
| **Имя** | {{ $rentalRequest->name }} |
| **Телефон** | {{ $rentalRequest->phone }} |
@if($rentalRequest->email)
| **Email** | {{ $rentalRequest->email }} |
@endif
@if($rentalRequest->desired_date && $rentalRequest->desired_date_end && ! $rentalRequest->desired_date->isSameDay($rentalRequest->desired_date_end))
| **Желаемый период** | {{ $rentalRequest->desired_date->format('d.m.Y') }} — {{ $rentalRequest->desired_date_end->format('d.m.Y') }} ({{ $rentalRequest->days() }} сут.) |
@elseif($rentalRequest->desired_date)
| **Желаемая дата** | {{ $rentalRequest->desired_date->format('d.m.Y') }} |
@endif
@if($rentalRequest->comment)
| **Комментарий** | {{ $rentalRequest->comment }} |
@endif
@if($rentalRequest->agreement_accepted_at)
| **Условия аренды приняты** | {{ $rentalRequest->agreement_accepted_at->format('d.m.Y H:i') }} |
@endif
| **Источник** | {{ $rentalRequest->source }} |
| **Дата запроса** | {{ $rentalRequest->created_at->format('d.m.Y H:i') }} |

</x-mail::message>
