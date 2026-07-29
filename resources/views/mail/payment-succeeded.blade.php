<x-mail::message>
# Оплата получена

Онлайн-платёж успешно завершён.

| Поле | Значение |
|------|----------|
| **Назначение** | {{ $transaction->registry?->name ?? '—' }} |
| **Сумма** | {{ number_format((float) $transaction->amount, 2, ',', ' ') }} ₽ |
| **Плательщик** | {{ $transaction->user?->full_name ?? $transaction->user?->email ?? '—' }} |
| **Дата оплаты** | {{ optional($transaction->paid_at)->format('d.m.Y H:i') ?? '—' }} |

С уважением,<br>
{{ config('mail.brand.name') }}
</x-mail::message>
