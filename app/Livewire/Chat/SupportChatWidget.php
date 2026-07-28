<?php

declare(strict_types=1);

namespace App\Livewire\Chat;

use App\Actions\Chat\StartSupportConversationAction;
use App\Models\Conversation;
use App\Models\User;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Плавающая кнопка чата поддержки на публичных страницах.
 *
 * Монтируется один раз в общем layout, рядом с остальными глобальными
 * компонентами (см. resources/views/layouts/public.blade.php).
 *
 * Диалог заводится лениво — при первом открытии окна, а не при каждом заходе
 * на сайт: иначе у оператора копились бы пустые обращения.
 *
 * Открыть из любого места:
 *   - в Livewire-шаблоне:  wire:click="$dispatch('open-support-chat')"
 *   - в обычном Alpine:    @click="Livewire.dispatch('open-support-chat')"
 *     ($dispatch из Alpine всплывает к window и до компонента не доходит).
 */
class SupportChatWidget extends Component
{
    public bool $isOpen = false;

    #[Locked]
    public ?string $conversationId = null;

    #[On('open-support-chat')]
    public function open(): void
    {
        if (! auth()->check()) {
            // Гость сначала входит: чат только для авторизованных.
            $this->dispatch('open-login-modal');

            return;
        }

        $this->ensureConversation();
        $this->isOpen = true;
    }

    public function toggle(): void
    {
        if ($this->isOpen) {
            $this->isOpen = false;

            return;
        }

        $this->open();
    }

    /** Непрочитанные ответы поддержки — бейдж на свёрнутой кнопке. */
    public function unreadCount(): int
    {
        $user = auth()->user();

        if (! $user instanceof User || $this->conversationId === null) {
            return 0;
        }

        $conversation = Conversation::query()->whereKey($this->conversationId)->first();

        return $conversation?->unreadCountFor($user) ?? 0;
    }

    /**
     * Диалог нужен и для бейджа у свёрнутого окна, поэтому подхватываем уже
     * существующее обращение при загрузке страницы, но не создаём новое.
     */
    public function mount(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        $this->conversationId = Conversation::query()
            ->support()
            ->forParticipant($user)
            ->latest('last_message_at')
            ->value('id');
    }

    private function ensureConversation(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        $this->conversationId = app(StartSupportConversationAction::class)
            ->handle($user)
            ->getKey();
    }

    public function render()
    {
        return view('livewire.chat.support-chat-widget', [
            'unread' => $this->unreadCount(),
        ]);
    }
}
