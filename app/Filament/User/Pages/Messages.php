<?php

declare(strict_types=1);

namespace App\Filament\User\Pages;

use App\Livewire\Chat\ConversationThread;
use App\Models\Conversation;
use App\Models\User;
use App\Notifications\ChatMessageReceivedNotification;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;

/**
 * Личные переписки по объявлениям.
 *
 * Отличие от страницы поддержки: тредов здесь много (по одному на объявление
 * и собеседника), поэтому непрочитанные считаются подзапросом, а не вызовом
 * unreadCountFor() в цикле шаблона, и поддерживается deep-link ?conversation=
 * — из письма и колокольчика нужно попадать в конкретный диалог.
 *
 * @see ConversationThread
 * @see ChatMessageReceivedNotification
 */
class Messages extends Page
{
    protected string $view = 'filament.user.pages.messages';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Сообщения';

    protected static ?string $title = 'Сообщения по объявлениям';

    protected static ?int $navigationSort = 2;

    /** Выбранный диалог. */
    public ?string $selectedId = null;

    public static function getNavigationGroup(): ?string
    {
        return 'Аккаунт';
    }

    public function mount(?string $conversation = null): void
    {
        if ($conversation !== null) {
            $this->selectConversation($conversation);
        }

        $this->selectedId ??= $this->conversations()->first()?->getKey();
    }

    /**
     * Свои личные переписки, свежие сверху.
     *
     * @return Collection<int, Conversation>
     */
    public function conversations(): Collection
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return new Collection;
        }

        return Conversation::query()
            ->direct()
            ->forParticipant($user)
            ->with(['lastMessage', 'subject', 'participants.user'])
            // Непрочитанные — подзапросом: в цикле шаблона это дало бы N+1
            // по числу переписок.
            ->withCount(['messages as unread_count' => function (Builder $query) use ($user): void {
                $query->where('user_id', '!=', $user->getKey())
                    ->whereRaw('chat_messages.created_at > COALESCE((
                        select last_read_at from conversation_participants
                        where conversation_participants.conversation_id = conversations.id
                          and conversation_participants.user_id = ?
                    ), "1970-01-01")', [$user->getKey()]);
            }])
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get();
    }

    public function selectConversation(string $conversationId): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        // Проверяем принадлежность до присваивания: id приходит из браузера.
        $belongs = Conversation::query()
            ->whereKey($conversationId)
            ->direct()
            ->forParticipant($user)
            ->exists();

        if ($belongs) {
            $this->selectedId = $conversationId;
        }
    }

    public function selectedConversation(): ?Conversation
    {
        $user = auth()->user();

        if ($this->selectedId === null || ! $user instanceof User) {
            return null;
        }

        return Conversation::query()
            ->whereKey($this->selectedId)
            ->direct()
            ->forParticipant($user)
            ->with(['subject', 'participants.user'])
            ->first();
    }

    /** Имя собеседника: у личной переписки роли Client нет, берём «всех, кроме себя». */
    public function counterpartName(Conversation $conversation): string
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return 'Собеседник';
        }

        return $conversation->participants
            ->firstWhere(fn ($participant) => $participant->user_id !== $user->getKey())
            ?->user?->name ?? 'Собеседник';
    }

    /** Сообщение отправлено — перерисовываем список, чтобы обновились метки. */
    #[On('chat-message-sent')]
    public function refreshList(): void
    {
        // Список строится в render(), достаточно самого факта обращения к серверу.
    }
}
