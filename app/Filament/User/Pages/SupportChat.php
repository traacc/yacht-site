<?php

declare(strict_types=1);

namespace App\Filament\User\Pages;

use App\Actions\Chat\StartSupportConversationAction;
use App\Livewire\Chat\ConversationThread;
use App\Models\Conversation;
use App\Models\User;
use App\Notifications\ChatMessageReceivedNotification;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;

/**
 * Переписка со службой поддержки в личном кабинете.
 *
 * Полноразмерная версия плавающего виджета с публичных страниц: слева история
 * своих обращений (включая закрытые), справа переписка тем же компонентом ленты.
 * Сюда ведёт кнопка «Открыть» из письма, Telegram и колокольчика.
 *
 * @see ConversationThread
 * @see ChatMessageReceivedNotification
 */
class SupportChat extends Page
{
    protected string $view = 'filament.user.pages.support-chat';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Поддержка';

    protected static ?string $title = 'Служба поддержки';

    protected static ?int $navigationSort = 1;

    /** Выбранное обращение. */
    public ?string $selectedId = null;

    public static function getNavigationGroup(): ?string
    {
        return 'Аккаунт';
    }

    public function mount(): void
    {
        // Диалог здесь намеренно НЕ создаётся: заход на страницу — ещё не обращение,
        // иначе у оператора копились бы пустые треды с каждого визита. Новый диалог
        // заводится по кнопке «Новое обращение» либо первым отправленным сообщением.
        $this->selectedId = $this->conversations()->first()?->getKey();
    }

    /**
     * История своих обращений, свежие сверху. Закрытые тоже показываем: в них
     * может лежать непрочитанный ответ поддержки.
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
            ->support()
            ->forParticipant($user)
            ->with('lastMessage')
            ->orderByDesc('last_message_at')
            ->limit(50)
            ->get();
    }

    public function selectConversation(string $conversationId): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        // Проверяем принадлежность до присваивания: id приходит из браузера.
        // В ConversationThread такая же проверка есть — это второй эшелон.
        $belongs = Conversation::query()
            ->whereKey($conversationId)
            ->forParticipant($user)
            ->exists();

        if ($belongs) {
            $this->selectedId = $conversationId;
        }
    }

    /** Открывает новое обращение либо переиспользует уже открытое. */
    public function startConversation(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        $this->selectedId = app(StartSupportConversationAction::class)
            ->handle($user)
            ->getKey();
    }

    public function selectedConversation(): ?Conversation
    {
        $user = auth()->user();

        if ($this->selectedId === null || ! $user instanceof User) {
            return null;
        }

        return Conversation::query()
            ->whereKey($this->selectedId)
            ->forParticipant($user)
            ->first();
    }

    /** Сообщение отправлено — перерисовываем список, чтобы обновились метки. */
    #[On('chat-message-sent')]
    public function refreshList(): void
    {
        // Список строится в render(), достаточно самого факта обращения к серверу.
    }
}
