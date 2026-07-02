<x-mail::message>
# Новый запрос на аренду яхты

| Поле | Значение |
|------|----------|
| **Яхта** | {{ $rentalRequest->yacht?->name ?? '—' }} |
| **Имя** | {{ $rentalRequest->name }} |
| **Телефон** | {{ $rentalRequest->phone }} |
@if($rentalRequest->desired_date)
| **Желаемая дата** | {{ $rentalRequest->desired_date->format('d.m.Y') }} |
@endif
@if($rentalRequest->comment)
| **Комментарий** | {{ $rentalRequest->comment }} |
@endif
| **Источник** | {{ $rentalRequest->source }} |
| **Дата запроса** | {{ $rentalRequest->created_at->format('d.m.Y H:i') }} |

</x-mail::message>
