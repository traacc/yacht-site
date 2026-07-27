{{-- Лента изменений платежа в модалке реестра --}}
<div class="space-y-3">
    @foreach ($logs as $log)
        <div class="rounded-lg border border-gray-200 p-3 text-sm">
            <div class="flex items-center justify-between gap-2">
                <span class="font-medium">{{ $log->created_at->format('d.m.Y H:i:s') }}</span>
                <x-filament::badge :color="$log->event->color()">
                    {{ $log->event->label() }}
                </x-filament::badge>
            </div>
            <dl class="mt-2 space-y-1 text-gray-500">
                <div>Кто: {{ $log->actorLabel() }}</div>
                @foreach ($log->changesLines() as $line)
                    <div>{{ $line }}</div>
                @endforeach
                @if ($log->ip)
                    <div>IP: <span class="font-mono">{{ $log->ip }}</span></div>
                @endif
            </dl>
        </div>
    @endforeach
</div>
