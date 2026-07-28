<?php

declare(strict_types=1);

namespace App\Livewire\Chat;

use App\Actions\Chat\MarkConversationReadAction;
use App\Actions\Chat\SendChatMessageAction;
use App\Enums\MessageAuthorRole;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

/**
 * Лента сообщений диалога и поле ввода.
 *
 * Единственный компонент переписки в проекте: используется в плавающем виджете
 * на публичном сайте, на странице личного кабинета и в правой панели рабочего
 * места оператора. Разница между сторонами — только флаг $asSupport.
 *
 * Новые сообщения подтягиваются опросом (wire:poll в шаблоне): WebSocket'ов в
 * проекте нет, а для поддержки задержки в несколько секунд достаточно.
 */
class ConversationThread extends Component
{
    /** Идентификатор диалога. Locked: подмена id в браузере открыла бы чужую переписку. */
    #[Locked]
    public ?string $conversationId = null;

    /** Компонент отрисован для оператора поддержки, а не для участника. */
    #[Locked]
    public bool $asSupport = false;

    /** Опрашивать сервер (выключается, когда окно чата свёрнуто). */
    public bool $polling = true;

    public string $draft = '';

    public ?string $error = null;

    /** Последнее показанное сообщение — по нему понимаем, что пришло новое. */
    #[Locked]
    public ?string $lastMessageId = null;

    public function mount(?string $conversationId = null, bool $asSupport = false, bool $polling = true): void
    {
        $this->conversationId = $conversationId;
        $this->asSupport = $asSupport;
        $this->polling = $polling;

        $this->lastMessageId = $this->messages->last()?->getKey();

        $this->markRead();
    }

    /**
     * Диалог с проверкой прав: оператору доступны все, пользователю — только свои.
     *
     * Computed, а не обычный метод: за один запрос свойство читают и markRead(),
     * и лента, и шаблон.
     */
    #[Computed]
    public function conversation(): ?Conversation
    {
        if ($this->conversationId === null) {
            return null;
        }

        $query = Conversation::query()->whereKey($this->conversationId);

        if (! $this->asSupport) {
            $user = auth()->user();

            if (! $user instanceof User) {
                return null;
            }

            $query->forParticipant($user);
        }

        return $query->first();
    }

    /** @return Collection<int, ChatMessage> */
    #[Computed]
    public function messages(): Collection
    {
        $conversation = $this->conversation;

        if (! $conversation instanceof Conversation) {
            return collect();
        }

        return $conversation->messages()->with('author')->get();
    }

    public function send(): void
    {
        $this->error = null;

        $conversation = $this->conversation;
        $user = auth()->user();

        if (! $conversation instanceof Conversation || ! $user instanceof User) {
            return;
        }

        try {
            app(SendChatMessageAction::class)->handle(
                conversation: $conversation,
                author: $user,
                role: $this->asSupport ? MessageAuthorRole::Support : MessageAuthorRole::Client,
                body: $this->draft,
            );
        } catch (RuntimeException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->draft = '';
        $this->refreshThread();

        // Список диалогов у оператора и счётчик непрочитанных в виджете
        // должны обновиться, не дожидаясь своего опроса.
        $this->dispatch('chat-message-sent');
    }

    /** Дёргается опросом: свежие сообщения приходят вместе с перерисовкой. */
    public function refreshThread(): void
    {
        unset($this->conversation, $this->messages);

        $latestId = $this->messages->last()?->getKey();

        if ($latestId !== $this->lastMessageId) {
            $this->lastMessageId = $latestId;
            // Прокручиваем ленту вниз только когда действительно что-то пришло,
            // иначе опрос будет дёргать её под курсором читающего.
            $this->dispatch('chat-thread-grew');
        }

        $this->markRead();
    }

    private function markRead(): void
    {
        $conversation = $this->conversation;

        if (! $conversation instanceof Conversation) {
            return;
        }

        app(MarkConversationReadAction::class)->handle(
            conversation: $conversation,
            reader: auth()->user(),
            asSupport: $this->asSupport,
        );
    }

    public function render()
    {
        return view('livewire.chat.conversation-thread');
    }
}
