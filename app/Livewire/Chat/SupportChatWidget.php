<?php

declare(strict_types=1);

namespace App\Livewire\Chat;

use App\Actions\Chat\StartSupportConversationAction;
use App\Enums\ChatContext;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
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

    /**
     * Открыть чат, при желании — с контекстом обращения.
     *
     * Контекст приходит из браузера, поэтому принимается только алиас из
     * ChatContext, а объект ищется по id внутри разрешённой для него модели.
     * Неизвестные значения молча игнорируются: пользователю важно, чтобы чат
     * открылся, а не сообщение об ошибке.
     */
    #[On('open-support-chat')]
    public function open(?string $context = null, ?string $id = null): void
    {
        if (! auth()->check()) {
            // Гость сначала входит: чат только для авторизованных.
            $this->dispatch('open-login-modal');

            return;
        }

        $this->ensureConversation(
            ChatContext::tryFrom((string) $context),
            $id,
        );

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

    /**
     * Диалог для окна чата.
     *
     * Уже подхваченное в mount() обращение переиспользуется, даже если оно
     * закрыто: иначе бейдж непрочитанных указывал бы на закрытый тред, а клик
     * заводил бы новый — и ответ поддержки становился недостижимым. Закрытое
     * обращение переоткроется первым же сообщением (@see SendChatMessageAction).
     */
    private function ensureConversation(?ChatContext $context = null, ?string $subjectId = null): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        // Закрытое обращение открываем как есть: иначе непрочитанный ответ
        // поддержки стал бы недостижим. Первое сообщение переоткроет тред.
        if ($this->ownConversation($user)?->isClosed() === true) {
            return;
        }

        // Для открытого обращения Action вернёт его же и дозаполнит контекст.
        $this->conversationId = app(StartSupportConversationAction::class)
            ->handle($user, $context, $this->resolveSubject($context, $subjectId))
            ->getKey();
    }

    /** Подхваченный диалог, если он всё ещё принадлежит пользователю. */
    private function ownConversation(User $user): ?Conversation
    {
        if ($this->conversationId === null) {
            return null;
        }

        return Conversation::query()
            ->whereKey($this->conversationId)
            ->forParticipant($user)
            ->first();
    }

    /** Объект контекста ищется только в модели, разрешённой для этого контекста. */
    private function resolveSubject(?ChatContext $context, ?string $subjectId): ?Model
    {
        $modelClass = $context?->modelClass();

        if ($modelClass === null || $subjectId === null || $subjectId === '') {
            return null;
        }

        return $modelClass::query()->whereKey($subjectId)->first();
    }

    public function render()
    {
        return view('livewire.chat.support-chat-widget', [
            'unread' => $this->unreadCount(),
        ]);
    }
}
