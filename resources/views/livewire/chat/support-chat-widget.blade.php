{{--
    Плавающее окно чата поддержки.
    z-index ниже баннера cookie (9999) намеренно: согласие на куки не должно
    перекрываться кнопкой чата.
--}}
<div
    class="fixed bottom-6 right-6 z-[9998]"
    {{-- Опрашиваем только ради бейджа и только тем, у кого переписка уже есть:
         гости и не писавшие пользователи не должны дёргать сервер вхолостую. --}}
    @if (! $isOpen && $conversationId !== null) wire:poll.30s @endif
>
    @if ($isOpen)
        <div class="flex h-[520px] max-h-[calc(100vh-6rem)] w-[360px] max-w-[calc(100vw-3rem)] flex-col border border-gray-200 bg-white shadow-2xl">
            <div class="flex shrink-0 items-center justify-between bg-[#2E325C] px-4 py-3 text-white">
                <div>
                    <div class="text-sm font-semibold">Служба поддержки</div>
                    <div class="text-xs text-white/70">Отвечаем в рабочее время</div>
                </div>

                <div class="flex items-center gap-3">
                    {{-- Все обращения, включая закрытые, доступны в личном кабинете. --}}
                    <a
                        href="{{ \App\Filament\User\Pages\SupportChat::getUrl(panel: 'user') }}"
                        class="text-xs text-white/70 underline transition-colors hover:text-white"
                    >
                        История обращений
                    </a>

                    <button
                        type="button"
                        wire:click="toggle"
                        aria-label="Свернуть чат"
                        class="text-2xl leading-none text-white/80 transition-colors hover:text-white"
                    >
                        &times;
                    </button>
                </div>
            </div>

            <livewire:chat.conversation-thread
                :conversationId="$conversationId"
                :key="'support-thread-'.($conversationId ?? 'none')"
            />
        </div>
    @else
        <button
            type="button"
            wire:click="toggle"
            aria-label="Открыть чат поддержки"
            class="relative flex h-14 w-14 items-center justify-center rounded-full bg-[#2D92CE] text-white shadow-lg transition-colors hover:bg-[#0074CC]"
        >
            <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 10.5h8m-8 4h5m-5.7 5.2L3 21l1.2-3.6A8.5 8.5 0 1 1 12 20.5a8.6 8.6 0 0 1-4.7-1.4Z"/>
            </svg>

            @if ($unread > 0)
                <span class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-xs font-semibold text-white">
                    {{ $unread }}
                </span>
            @endif
        </button>
    @endif
</div>
