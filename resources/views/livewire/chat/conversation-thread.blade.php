{{--
    Лента сообщений и поле ввода. Используется на публичном сайте, в личном
    кабинете и в рабочем месте оператора — поэтому здесь только нейтральные
    утилиты Tailwind, без брендовых компонентов сайта и без классов Filament.
--}}
<div
    class="flex flex-col h-full min-h-0"
    @if ($polling) wire:poll.5s="refreshThread" @endif
>
    @if (! $this->conversation)
        <div class="flex-1 flex items-center justify-center p-6 text-center text-sm text-gray-500">
            Выберите обращение, чтобы прочитать переписку.
        </div>
    @else
        {{-- Лента --}}
        <div
            x-data="{
                toBottom() {
                    $nextTick(() => { $el.scrollTop = $el.scrollHeight })
                }
            }"
            x-init="toBottom()"
            @chat-thread-grew.window="toBottom()"
            class="flex-1 min-h-0 overflow-y-auto px-4 py-3 space-y-3 bg-gray-50"
        >
            @forelse ($this->messages as $message)
                @php
                    $isSystem = $message->author_role->isSystem();
                    $isMine = ! $isSystem && $message->author_role->isSupport() === $asSupport;
                @endphp

                @if ($isSystem)
                    <div wire:key="msg-{{ $message->id }}" class="flex justify-center">
                        <span class="rounded-full bg-gray-200 px-3 py-1 text-xs text-gray-600">
                            {{ $message->body }}
                        </span>
                    </div>
                @else
                    <div wire:key="msg-{{ $message->id }}" class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[80%] rounded-lg px-3 py-2 text-sm {{ $isMine ? 'bg-[#2D92CE] text-white' : 'bg-white text-gray-800 border border-gray-200' }}">
                            @unless ($isMine)
                                <div class="mb-0.5 text-xs font-semibold text-gray-500">
                                    {{ $message->authorName() }}
                                </div>
                            @endunless

                            <div class="whitespace-pre-line break-words">{{ $message->body }}</div>

                            <div class="mt-1 text-[11px] {{ $isMine ? 'text-white/70' : 'text-gray-400' }}">
                                {{ \App\Support\DisplayTime::format($message->created_at) }}
                            </div>
                        </div>
                    </div>
                @endif
            @empty
                <div class="flex h-full items-center justify-center p-6 text-center text-sm text-gray-500">
                    @if ($asSupport)
                        В этом обращении пока нет сообщений.
                    @else
                        Опишите вопрос — служба поддержки ответит здесь же, а уведомление придёт выбранным вами способом.
                    @endif
                </div>
            @endforelse
        </div>

        {{-- Ввод --}}
        <div class="border-t border-gray-200 bg-white p-3">
            @if ($error)
                <p class="mb-2 text-xs text-red-600">{{ $error }}</p>
            @endif

            @if ($this->conversation->isClosed() && ! $asSupport)
                <p class="mb-2 text-xs text-gray-500">
                    Обращение закрыто. Новое сообщение откроет его снова.
                </p>
            @endif

            <form wire:submit="send" class="flex items-end gap-2">
                <textarea
                    wire:model="draft"
                    rows="2"
                    maxlength="5000"
                    placeholder="Ваше сообщение…"
                    class="flex-1 resize-none rounded border border-gray-300 px-3 py-2 text-sm focus:border-[#2D92CE] focus:outline-none focus:ring-0"
                    @keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $wire.send() }"
                ></textarea>

                <button
                    type="submit"
                    class="shrink-0 bg-[#2D92CE] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#0074CC] disabled:opacity-50"
                    wire:loading.attr="disabled"
                    wire:target="send"
                >
                    Отправить
                </button>
            </form>

            <p class="mt-1 text-[11px] text-gray-400">Enter — отправить, Shift+Enter — новая строка.</p>
        </div>
    @endif
</div>
