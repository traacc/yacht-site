{{--
    Лента сообщений и поле ввода. Используется на публичном сайте, в личном
    кабинете и в рабочем месте оператора — поэтому здесь только нейтральные
    утилиты Tailwind, без брендовых компонентов сайта и без классов Filament.
--}}
<div
    class="flex flex-col h-full min-h-0"
    {{-- Пока в поле висят выбранные файлы, опрос выключен: перерисовка ленты
         мешала бы дозагрузке временных файлов. --}}
    @if ($polling && $attachments === []) wire:poll.5s="refreshThread" @endif
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
            @if ($this->hasMore)
                <div class="flex justify-center">
                    <button
                        type="button"
                        wire:click="loadOlder"
                        class="rounded border border-gray-300 bg-white px-3 py-1 text-xs text-gray-600 transition-colors hover:bg-gray-100"
                    >
                        Показать более ранние сообщения
                    </button>
                </div>
            @endif

            @forelse ($this->visibleMessages as $message)
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

                            @if ($message->body)
                                <div class="whitespace-pre-line break-words">{{ $message->body }}</div>
                            @endif

                            {{-- Ссылки стабильные (не подписанные), поэтому опрос ленты
                                 не заставляет браузер перекачивать вложения. --}}
                            @if ($message->hasAttachments())
                                <div class="mt-2 space-y-2">
                                    @foreach ($message->getMedia(\App\Models\ChatMessage::ATTACHMENTS) as $file)
                                        @if (str_starts_with((string) $file->mime_type, 'image/'))
                                            <a
                                                href="{{ route('chat.attachment', ['media' => $file->uuid]) }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="block"
                                            >
                                                <img
                                                    src="{{ route('chat.attachment', ['media' => $file->uuid, 'p' => 'preview']) }}"
                                                    alt="{{ $file->name }}"
                                                    loading="lazy"
                                                    class="max-h-60 w-auto max-w-full rounded border border-gray-200"
                                                >
                                            </a>
                                        @else
                                            <a
                                                href="{{ route('chat.attachment', ['media' => $file->uuid]) }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="flex items-center gap-2 rounded border px-2 py-1 text-xs {{ $isMine ? 'border-white/40 text-white' : 'border-gray-200 text-gray-700' }}"
                                            >
                                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v5h5M7 3h8l5 5v13H7V3Z"/>
                                                </svg>
                                                <span class="truncate">{{ $file->file_name }}</span>
                                                <span class="shrink-0 opacity-70">{{ round($file->size / 1024) }} КБ</span>
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

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

            {{-- Выбранные файлы: до отправки живут в свойстве компонента. --}}
            @if ($attachments !== [])
                <div class="mb-2 flex flex-wrap gap-2">
                    @foreach ($attachments as $index => $file)
                        <span
                            wire:key="attachment-{{ $index }}-{{ $file->getFilename() }}"
                            class="flex max-w-full items-center gap-1 rounded bg-gray-100 px-2 py-1 text-xs text-gray-700"
                        >
                            <span class="truncate">{{ $file->getClientOriginalName() }}</span>
                            <button
                                type="button"
                                wire:click="removeAttachment({{ $index }})"
                                aria-label="Убрать файл"
                                class="shrink-0 text-gray-400 transition-colors hover:text-red-500"
                            >
                                &times;
                            </button>
                        </span>
                    @endforeach
                </div>
            @endif

            <div wire:loading wire:target="attachments" class="mb-2 text-xs text-gray-500">
                Загружаем файлы…
            </div>

            <form wire:submit="send" class="flex items-end gap-2">
                <label
                    class="shrink-0 cursor-pointer border border-gray-300 px-3 py-2 text-gray-500 transition-colors hover:bg-gray-100"
                    title="Прикрепить файлы (до {{ \App\Actions\Chat\SendChatMessageAction::MAX_ATTACHMENTS }})"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.4 12.6 12 19a4.2 4.2 0 0 1-6-6l7.1-7.1a2.8 2.8 0 0 1 4 4l-7.1 7.1a1.4 1.4 0 0 1-2-2l6.4-6.4"/>
                    </svg>
                    <input
                        type="file"
                        multiple
                        wire:model="attachments"
                        accept=".jpg,.jpeg,.png,.webp,.heic,.heif,.pdf"
                        class="hidden"
                    >
                </label>

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
                    wire:target="send,attachments"
                >
                    Отправить
                </button>
            </form>

            <p class="mt-1 text-[11px] text-gray-400">
                Enter — отправить, Shift+Enter — новая строка. Можно приложить до
                {{ \App\Actions\Chat\SendChatMessageAction::MAX_ATTACHMENTS }} файлов (фото или PDF).
            </p>
        </div>
    @endif
</div>
