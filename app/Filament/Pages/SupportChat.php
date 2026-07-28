<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Chat\CloseConversationAction;
use App\Enums\ConversationStatus;
use App\Enums\MessageAuthorRole;
use App\Filament\Concerns\RestrictsAccessByRole;
use App\Models\Conversation;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;
use UnitEnum;

/**
 * Рабочее место оператора: слева список обращений, справа переписка.
 *
 * Отвечать может любой оператор с доступом к странице, поэтому отметка
 * прочтения у поддержки общая (Conversation::$support_read_at), а не личная.
 */
class SupportChat extends Page
{
    use RestrictsAccessByRole;

    protected string $view = 'filament.pages.support-chat';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Чат поддержки';

    protected static ?string $title = 'Чат поддержки';

    protected static ?int $navigationSort = 92;

    protected static string|UnitEnum|null $navigationGroup = 'Сайт';

    /** Выбранное обращение. */
    public ?string $selectedId = null;

    /** Фильтр списка: open | closed | all. */
    public string $filter = 'open';

    public function mount(): void
    {
        $this->selectedId = $this->conversations()->first()?->getKey();
    }

    /** Бейдж в меню: сколько обращений ждут ответа. */
    public static function getNavigationBadge(): ?string
    {
        $count = Conversation::query()->support()->unansweredForSupport()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    /**
     * Список обращений: сначала ждущие ответа, затем по свежести.
     *
     * @return Collection<int, Conversation>
     */
    public function conversations(): Collection
    {
        return Conversation::query()
            ->support()
            ->when($this->filter === 'open', fn ($q) => $q->where('status', ConversationStatus::Open))
            ->when($this->filter === 'closed', fn ($q) => $q->where('status', ConversationStatus::Closed))
            ->with(['creator', 'lastMessage'])
            // Счётчик подзапросом, а не методом модели на каждой строке: иначе
            // сотня обращений в списке дала бы сотню отдельных COUNT-запросов.
            ->withCount(['messages as unread_support_count' => function ($q): void {
                $q->where('author_role', MessageAuthorRole::Client)
                    ->where(function ($unread): void {
                        $unread->whereNull('conversations.support_read_at')
                            ->orWhereColumn('chat_messages.created_at', '>', 'conversations.support_read_at');
                    });
            }])
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get();
    }

    public function selectConversation(string $conversationId): void
    {
        $this->selectedId = $conversationId;
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->selectedId = $this->conversations()->first()?->getKey();
    }

    public function closeConversation(): void
    {
        $conversation = $this->selectedConversation();

        if (! $conversation instanceof Conversation) {
            return;
        }

        app(CloseConversationAction::class)->handle($conversation);

        Notification::make()
            ->title('Обращение закрыто')
            ->success()
            ->send();
    }

    public function selectedConversation(): ?Conversation
    {
        return $this->selectedId === null
            ? null
            : Conversation::query()->whereKey($this->selectedId)->first();
    }

    /** Ответ оператора отправлен — перерисовываем список, чтобы обновились метки. */
    #[On('chat-message-sent')]
    public function refreshList(): void
    {
        // Список строится в render(), достаточно самого факта обращения к серверу.
    }
}
