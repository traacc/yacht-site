{{-- Список транзакций эквайринга в модалке реестра платежей --}}
<div class="space-y-3">
    @foreach ($transactions as $transaction)
        <div class="rounded-lg border border-gray-200 p-3 text-sm">
            <div class="flex items-center justify-between gap-2">
                <span class="font-medium">
                    {{ number_format((float) $transaction->amount, 2, ',', ' ') }} ₽
                    · {{ $transaction->provider->label() }}
                </span>
                <x-filament::badge :color="$transaction->status->color()">
                    {{ $transaction->status->label() }}
                </x-filament::badge>
            </div>
            <dl class="mt-2 space-y-1 text-gray-500">
                <div>Создана: {{ $transaction->created_at->format('d.m.Y H:i') }}</div>
                @if ($transaction->paid_at)
                    <div>Оплачена: {{ $transaction->paid_at->format('d.m.Y H:i') }}</div>
                @endif
                @if ($transaction->external_id)
                    <div>ID у провайдера: <span class="font-mono">{{ $transaction->external_id }}</span></div>
                @endif
                @if ($transaction->user)
                    <div>Плательщик: {{ $transaction->user->full_name }}</div>
                @endif
                @if ($transaction->failure_reason)
                    <div class="text-danger-600">Причина: {{ $transaction->failure_reason }}</div>
                @endif
            </dl>
        </div>
    @endforeach
</div>
