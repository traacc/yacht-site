{{-- Рабочее место оператора: слева обращения, справа переписка. --}}
<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3" style="height: 70vh">

        {{-- Список обращений --}}
        <div class="flex min-h-0 flex-col border border-gray-200 bg-white lg:col-span-1" wire:poll.10s>
            <div class="flex shrink-0 gap-1 border-b border-gray-200 p-2">
                @foreach (['open' => 'Открытые', 'closed' => 'Закрытые', 'all' => 'Все'] as $value => $label)
                    <button
                        type="button"
                        wire:click="setFilter('{{ $value }}')"
                        class="px-3 py-1 text-sm transition-colors {{ $filter === $value ? 'bg-[#2D92CE] text-white' : 'text-gray-600 hover:bg-gray-100' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto">
                @forelse ($this->conversations() as $conversation)
                    <button
                        type="button"
                        wire:key="conv-{{ $conversation->id }}"
                        wire:click="selectConversation('{{ $conversation->id }}')"
                        class="block w-full border-b border-gray-100 px-3 py-2 text-left transition-colors {{ $selectedId === $conversation->id ? 'bg-gray-100' : 'hover:bg-gray-50' }}"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate text-sm font-semibold text-gray-800">
                                {{ $conversation->creator?->name ?? 'Пользователь удалён' }}
                            </span>

                            @if ($conversation->unread_support_count > 0)
                                <span class="shrink-0 rounded-full bg-red-500 px-2 py-0.5 text-xs font-semibold text-white">
                                    {{ $conversation->unread_support_count }}
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
                    <p class="p-4 text-sm text-gray-500">Обращений нет.</p>
                @endforelse
            </div>
        </div>

        {{-- Переписка --}}
        <div class="flex min-h-0 flex-col border border-gray-200 bg-white lg:col-span-2">
            @php($selected = $this->selectedConversation())

            @if ($selected)
                <div class="flex shrink-0 items-center justify-between gap-2 border-b border-gray-200 px-4 py-2">
                    <div class="min-w-0">
                        <div class="truncate text-sm font-semibold text-gray-800">
                            {{ $selected->creator?->name ?? 'Пользователь удалён' }}
                        </div>
                        <div class="truncate text-xs text-gray-500">{{ $selected->title }}</div>
                    </div>

                    @unless ($selected->isClosed())
                        <button
                            type="button"
                            wire:click="closeConversation"
                            wire:confirm="Закрыть обращение? Новое сообщение пользователя откроет его снова."
                            class="shrink-0 border border-gray-300 px-3 py-1 text-sm text-gray-600 transition-colors hover:bg-gray-100"
                        >
                            Закрыть обращение
                        </button>
                    @endunless
                </div>
            @endif

            {{-- key завязан на выбранное обращение: при переключении компонент
                 должен смонтироваться заново, а не подставить чужую ленту. --}}
            <livewire:chat.conversation-thread
                :conversationId="$selectedId"
                :asSupport="true"
                :key="'thread-'.($selectedId ?? 'none')"
            />
        </div>
    </div>
</x-filament-panels::page>
