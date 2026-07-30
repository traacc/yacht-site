{{-- Переписка с поддержкой в личном кабинете: слева история обращений, справа лента. --}}
<x-filament-panels::page>
    <p class="text-sm text-gray-500">
        Напишите нам — ответ придёт сюда, а уведомление о нём тем способом, который выбран
        в разделе «Уведомления».
    </p>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3" style="height: 65vh">

        {{-- История обращений --}}
        <div class="flex min-h-0 flex-col border border-gray-200 bg-white lg:col-span-1" wire:poll.10s>
            <div class="shrink-0 border-b border-gray-200 p-2">
                <button
                    type="button"
                    wire:click="startConversation"
                    class="w-full bg-[#2D92CE] px-3 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#0074CC]"
                >
                    Новое обращение
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto">
                @forelse ($this->conversations() as $conversation)
                    @php($unread = $conversation->unreadCountFor(auth()->user()))
                    <button
                        type="button"
                        wire:key="user-conv-{{ $conversation->id }}"
                        wire:click="selectConversation('{{ $conversation->id }}')"
                        class="block w-full border-b border-gray-100 px-3 py-2 text-left transition-colors {{ $selectedId === $conversation->id ? 'bg-gray-100' : 'hover:bg-gray-50' }}"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate text-sm font-semibold text-gray-800">
                                {{ $conversation->title ?? 'Без темы' }}
                            </span>

                            @if ($unread > 0)
                                <span class="shrink-0 rounded-full bg-red-500 px-2 py-0.5 text-xs font-semibold text-white">
                                    {{ $unread }}
                                </span>
                            @endif
                        </div>

                        <div class="mt-0.5 truncate text-xs text-gray-500">
                            {{ $conversation->lastMessage?->body ?? 'Нет сообщений' }}
                        </div>

                        <div class="mt-0.5 flex items-center gap-2 text-[11px] text-gray-400">
                            <span>{{ \App\Support\DisplayTime::format($conversation->last_message_at) }}</span>
                            @if ($conversation->isClosed())
                                <span class="rounded bg-gray-200 px-1.5 text-gray-600">закрыто</span>
                            @endif
                        </div>
                    </button>
                @empty
                    <p class="p-4 text-sm text-gray-500">
                        Обращений пока нет. Нажмите «Новое обращение», чтобы написать в поддержку.
                    </p>
                @endforelse
            </div>
        </div>

        {{-- Переписка --}}
        <div class="flex min-h-0 flex-col border border-gray-200 bg-white lg:col-span-2">
            @php($selected = $this->selectedConversation())

            @if ($selected)
                <div class="shrink-0 border-b border-gray-200 px-4 py-2">
                    <div class="truncate text-sm font-semibold text-gray-800">
                        {{ $selected->title ?? 'Обращение в поддержку' }}
                    </div>
                    <div class="text-xs text-gray-500">
                        {{ $selected->isClosed() ? 'Обращение закрыто — новое сообщение откроет его снова' : 'Обращение открыто' }}
                    </div>
                </div>
            @endif

            {{-- key завязан на выбранное обращение: при переключении компонент
                 должен смонтироваться заново, а не подставить чужую ленту. --}}
            <livewire:chat.conversation-thread
                :conversationId="$selectedId"
                :key="'user-thread-'.($selectedId ?? 'none')"
            />
        </div>
    </div>
</x-filament-panels::page>
