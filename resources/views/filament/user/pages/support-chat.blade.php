{{-- Переписка с поддержкой в личном кабинете: тот же компонент, что и в виджете. --}}
<x-filament-panels::page>
    <p class="text-sm text-gray-500">
        Напишите нам — ответ придёт сюда, а уведомление о нём тем способом, который выбран
        в разделе «Уведомления».
    </p>

    <div class="flex flex-col border border-gray-200 bg-white" style="height: 60vh">
        <livewire:chat.conversation-thread
            :conversationId="$conversationId"
            :key="'user-thread-'.($conversationId ?? 'none')"
        />
    </div>
</x-filament-panels::page>
