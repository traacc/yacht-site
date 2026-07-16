{{-- Список клиентов внешнего API + показ только что выпущенного токена.
     Рендерится внутри страницы SiteSettings (Livewire), поэтому wire:click
     обращается к методам страницы (revokeApiClient / deleteApiClient). --}}
<div class="fi-api-clients" style="display:flex;flex-direction:column;gap:1rem;">

    @if ($newToken)
        <div
            x-data="{ copied: false }"
            style="border:1px solid rgb(34 197 94 / .4);background:rgb(34 197 94 / .08);border-radius:.75rem;padding:1rem;"
        >
            <p style="font-weight:600;margin-bottom:.25rem;">
                Токен для «{{ $newTokenName }}» выпущен
            </p>
            <p class="fi-color-danger" style="font-size:.85rem;margin-bottom:.5rem;">
                Скопируйте его сейчас — значение показывается один раз и в базе не хранится.
            </p>
            <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
                <code style="user-select:all;word-break:break-all;padding:.4rem .6rem;border-radius:.5rem;background:rgb(0 0 0 / .06);font-family:ui-monospace,monospace;">{{ $newToken }}</code>
                <x-filament::button
                    size="sm"
                    icon="heroicon-o-clipboard-document"
                    x-on:click="navigator.clipboard.writeText(@js($newToken)); copied = true; setTimeout(() => copied = false, 1500)"
                >
                    <span x-show="!copied">Копировать</span>
                    <span x-show="copied" x-cloak>Скопировано</span>
                </x-filament::button>
            </div>
        </div>
    @endif

    @if ($clients->isEmpty())
        <p class="fi-color-gray" style="font-size:.9rem;">
            Токенов пока нет. Нажмите «Выпустить API-токен», чтобы создать доступ для внешней программы.
        </p>
    @else
        <table style="width:100%;border-collapse:collapse;font-size:.9rem;">
            <thead>
                <tr style="text-align:left;border-bottom:1px solid rgb(0 0 0 / .1);">
                    <th style="padding:.5rem .5rem;">Название</th>
                    <th style="padding:.5rem .5rem;">Статус</th>
                    <th style="padding:.5rem .5rem;">Последнее использование</th>
                    <th style="padding:.5rem .5rem;">Создан</th>
                    <th style="padding:.5rem .5rem;"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($clients as $client)
                    <tr style="border-bottom:1px solid rgb(0 0 0 / .05);">
                        <td style="padding:.5rem .5rem;font-weight:500;">{{ $client->name }}</td>
                        <td style="padding:.5rem .5rem;">
                            @if ($client->revoked_at)
                                <x-filament::badge color="danger">Отозван</x-filament::badge>
                            @else
                                <x-filament::badge color="success">Активен</x-filament::badge>
                            @endif
                        </td>
                        <td style="padding:.5rem .5rem;">
                            {{ $client->last_used_at?->diffForHumans() ?? '—' }}
                        </td>
                        <td style="padding:.5rem .5rem;">
                            {{ $client->created_at?->format('d.m.Y') ?? '—' }}
                        </td>
                        <td style="padding:.5rem .5rem;text-align:right;white-space:nowrap;">
                            @unless ($client->revoked_at)
                                <x-filament::button
                                    size="sm"
                                    color="warning"
                                    wire:click="revokeApiClient('{{ $client->id }}')"
                                    wire:confirm="Отозвать токен «{{ $client->name }}»? Внешняя программа потеряет доступ."
                                >
                                    Отозвать
                                </x-filament::button>
                            @endunless
                            <x-filament::button
                                size="sm"
                                color="gray"
                                wire:click="deleteApiClient('{{ $client->id }}')"
                                wire:confirm="Удалить токен «{{ $client->name }}» безвозвратно?"
                            >
                                Удалить
                            </x-filament::button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
