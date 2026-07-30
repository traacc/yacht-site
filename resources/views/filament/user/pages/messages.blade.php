{{-- Личные переписки по объявлениям: слева список, справа лента (тот же компонент, что у поддержки). --}}
<x-filament-panels::page>
    <p class="text-sm text-gray-500">
        Здесь переписка с теми, кто откликнулся на ваши объявления, и с авторами объявлений,
        которым написали вы. Уведомления приходят способом, выбранным в разделе «Уведомления».
    </p>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3" style="height: 65vh">

        {{-- Список переписок --}}
        <div class="flex min-h-0 flex-col border border-gray-200 bg-white lg:col-span-1" wire:poll.10s>
            <div class="min-h-0 flex-1 overflow-y-auto">
                @forelse ($this->conversations() as $conversation)
                    <button
                        type="button"
                        wire:key="direct-conv-{{ $conversation->id }}"
                        wire:click="selectConversation('{{ $conversation->id }}')"
                        class="block w-full border-b border-gray-100 px-3 py-2 text-left transition-colors {{ $selectedId === $conversation->id ? 'bg-gray-100' : 'hover:bg-gray-50' }}"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate text-sm font-semibold text-gray-800">
                                {{ $this->counterpartName($conversation) }}
                            </span>

                            @if ($conversation->unread_count > 0)
                                <span class="shrink-0 rounded-full bg-red-500 px-2 py-0.5 text-xs font-semibold text-white">
                                    {{ $conversation->unread_count }}
                                </span>
                            @endif
                        </div>

                        <div class="mt-0.5 truncate text-xs text-gray-600">
                            {{ $conversation->title ?? 'Объявление' }}
                        </div>

                        <div class="mt-0.5 truncate text-xs text-gray-500">
                            {{ $conversation->lastMessage?->body ?? 'Нет сообщений' }}
                        </div>

                        <div class="mt-0.5 text-[11px] text-gray-400">
                            {{ \App\Support\DisplayTime::format($conversation->last_message_at) }}
                        </div>
                    </button>
                @empty
                    <p class="p-4 text-sm text-gray-500">
                        Переписок пока нет. Они появятся, когда вы напишете автору объявления
                        или кто-то откликнется на ваше.
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
                        {{ $this->counterpartName($selected) }}
                    </div>
                    <div class="truncate text-xs text-gray-500">
                        @if ($selected->subject instanceof \App\Models\Advert)
                            <a href="{{ $selected->subject->publicUrl() }}" target="_blank" class="text-[#2D92CE] hover:underline">
                                {{ $selected->subject->title }}
                            </a>
                        @else
                            {{ $selected->title ?? 'Объявление удалено' }}
                        @endif
                    </div>
                </div>
            @endif

            {{-- key завязан на выбранную переписку: при переключении компонент
                 должен смонтироваться заново, а не подставить чужую ленту. --}}
            <livewire:chat.conversation-thread
                :conversationId="$selectedId"
                :key="'direct-thread-'.($selectedId ?? 'none')"
            />
        </div>
    </div>
</x-filament-panels::page>
