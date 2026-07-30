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
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
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
    use WithFileUploads;

    /** Сколько сообщений подгружается за раз. */
    private const PAGE = 30;

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

    /** Сколько последних сообщений показываем. Растёт по кнопке «Показать более ранние». */
    public int $limit = self::PAGE;

    /**
     * Выбранные, но ещё не отправленные файлы.
     *
     * @var list<TemporaryUploadedFile>
     */
    public array $attachments = [];

    /** Последнее показанное сообщение — по нему понимаем, что пришло новое. */
    #[Locked]
    public ?string $lastMessageId = null;

    public function mount(?string $conversationId = null, bool $asSupport = false, bool $polling = true): void
    {
        $this->conversationId = $conversationId;
        $this->asSupport = $asSupport;
        $this->polling = $polling;

        $this->lastMessageId = $this->visibleMessages->last()?->getKey();

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

    /**
     * Последние $limit сообщений в хронологическом порядке.
     *
     * Окном, а не всей перепиской: лента перечитывается каждым опросом (раз в
     * 5 секунд), и длинный тред тянул бы сотни строк на каждый запрос.
     *
     * ★ Не `messages()`: это имя зарезервировано Livewire под сообщения
     * валидации (HandlesValidation::getMessages() вызывает его и ждёт массив),
     * и при отправке вложений валидация падала бы на array_merge().
     *
     * @return Collection<int, ChatMessage>
     */
    #[Computed]
    public function visibleMessages(): Collection
    {
        $conversation = $this->conversation;

        if (! $conversation instanceof Conversation) {
            return collect();
        }

        return $conversation->messages()
            ->with(['author', 'media'])
            // reorder(): у связи задана сортировка по created_at, иначе latest() к ней добавится.
            ->reorder()
            ->latest('created_at')
            ->limit($this->limit)
            ->get()
            ->reverse()
            ->values();
    }

    /** Есть ли сообщения старше показанного окна. */
    #[Computed]
    public function hasMore(): bool
    {
        $conversation = $this->conversation;

        if (! $conversation instanceof Conversation) {
            return false;
        }

        return $conversation->messages()->count() > $this->limit;
    }

    public function loadOlder(): void
    {
        $this->limit += self::PAGE;

        unset($this->visibleMessages, $this->hasMore);
    }

    /** Правила вложений: у операторов лимит размера выше (ТЗ — админы без ограничений). */
    protected function rules(): array
    {
        $maxKilobytes = $this->asSupport ? 10240 : 5120;

        return [
            'attachments' => ['array', 'max:'.SendChatMessageAction::MAX_ATTACHMENTS],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,heic,heif,pdf', 'max:'.$maxKilobytes],
        ];
    }

    protected function validationAttributes(): array
    {
        return ['attachments' => 'вложения', 'attachments.*' => 'файл'];
    }

    public function removeAttachment(int $index): void
    {
        unset($this->attachments[$index]);

        $this->attachments = array_values($this->attachments);
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
            $this->validate();
        } catch (ValidationException $e) {
            $this->error = $e->validator->errors()->first();

            return;
        }

        try {
            app(SendChatMessageAction::class)->handle(
                conversation: $conversation,
                author: $user,
                role: $this->asSupport ? MessageAuthorRole::Support : MessageAuthorRole::Client,
                body: $this->draft,
                attachments: array_values($this->attachments),
            );
        } catch (RuntimeException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->draft = '';
        $this->attachments = [];
        $this->refreshThread();

        // Список диалогов у оператора и счётчик непрочитанных в виджете
        // должны обновиться, не дожидаясь своего опроса.
        $this->dispatch('chat-message-sent');
    }

    /** Дёргается опросом: свежие сообщения приходят вместе с перерисовкой. */
    public function refreshThread(): void
    {
        unset($this->conversation, $this->visibleMessages, $this->hasMore);

        $latestId = $this->visibleMessages->last()?->getKey();

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
