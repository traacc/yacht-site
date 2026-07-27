{{-- Одна запись журнала изменений развёрнуто --}}
<div class="space-y-3">
    <div class="rounded-lg border border-gray-200 p-3 text-sm">
        <div class="flex items-center justify-between gap-2">
            <span class="font-medium">{{ $log->registry_name }}</span>
            <x-filament::badge :color="$log->event->color()">
                {{ $log->event->label() }}
            </x-filament::badge>
        </div>
        <dl class="mt-2 space-y-1 text-gray-500">
            <div>Дата и время: {{ $log->created_at->format('d.m.Y H:i:s') }}</div>
            <div>Кто: {{ $log->actorLabel() }}</div>
            @if ($log->registry_amount !== null)
                <div>Сумма платежа: {{ number_format((float) $log->registry_amount, 2, ',', ' ') }} ₽</div>
            @endif
            @if ($log->ip)
                <div>IP: <span class="font-mono">{{ $log->ip }}</span></div>
            @endif
            @if ($log->payment_registry_id === null)
                <div class="text-danger-600">Платёж удалён из реестра</div>
            @endif
        </dl>
    </div>

    @if ($log->changesLines() !== [])
        <div class="rounded-lg border border-gray-200 p-3 text-sm">
            <span class="font-medium">Изменения</span>
            <dl class="mt-2 space-y-1 text-gray-500">
                @foreach ($log->changesLines() as $line)
                    <div>{{ $line }}</div>
                @endforeach
            </dl>
        </div>
    @endif
</div>
