<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Enums\ConversationStatus;
use App\Enums\MessageAuthorRole;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Notifications\ChatMessageReceivedNotification;
use App\Services\Chat\ChatRecipients;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Отправка сообщения в диалог.
 *
 * Здесь же решается, кого уведомлять: уведомление уходит противоположной
 * стороне только если у неё в этом диалоге ещё нет непрочитанных. Иначе
 * оживлённая переписка обернулась бы письмом и сообщением в Telegram на
 * каждую реплику. Дополнительного состояния для этого не нужно — признак
 * считается по отметкам прочтения до вставки сообщения.
 */
class SendChatMessageAction
{
    /** Сообщений от одного пользователя в минуту. */
    private const MAX_PER_MINUTE = 20;

    private const MAX_LENGTH = 5000;

    public function __construct(
        private readonly ChatRecipients $recipients,
    ) {}

    public function handle(
        Conversation $conversation,
        ?User $author,
        MessageAuthorRole $role,
        string $body,
    ): ChatMessage {
        $body = trim($body);

        if ($body === '') {
            throw new RuntimeException('Нельзя отправить пустое сообщение.');
        }

        if (mb_strlen($body) > self::MAX_LENGTH) {
            throw new RuntimeException('Сообщение слишком длинное.');
        }

        $this->guardRate($author, $role);

        // Считаем до вставки: своё же сообщение иначе попадёт в «непрочитанные».
        $recipients = $this->resolveRecipients($conversation, $author, $role);

        $message = DB::transaction(function () use ($conversation, $author, $role, $body): ChatMessage {
            $message = $conversation->messages()->create([
                'user_id' => $author?->getKey(),
                'author_role' => $role,
                'body' => $body,
            ]);

            $conversation->forceFill([
                'last_message_at' => $message->created_at,
                // Новое сообщение переоткрывает закрытое обращение.
                'status' => ConversationStatus::Open,
                'title' => $conversation->title ?? Str::limit($body, 80),
            ])->save();

            return $message;
        });

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new ChatMessageReceivedNotification(
                authorName: $message->authorName(),
                excerpt: Str::limit($body, 200),
                forSupport: $role === MessageAuthorRole::Client,
            ));
        }

        return $message;
    }

    /**
     * Получатели уведомления: противоположная сторона, у которой сейчас нет
     * непрочитанных сообщений в этом диалоге.
     *
     * @return Collection<int, User>
     */
    private function resolveRecipients(
        Conversation $conversation,
        ?User $author,
        MessageAuthorRole $role,
    ): Collection {
        // Системные записи («обращение закрыто») никого не дёргают.
        if ($role->isSystem()) {
            return new Collection;
        }

        if ($role === MessageAuthorRole::Client) {
            return $conversation->isUnansweredBySupport()
                ? new Collection
                : $this->recipients->forSupport();
        }

        return $this->recipients
            ->participants($conversation, $author)
            ->filter(static fn (User $user): bool => $conversation->unreadCountFor($user) === 0)
            ->values();
    }

    /** Защита от спама и от автокликеров: ограничение живёт на самом авторе. */
    private function guardRate(?User $author, MessageAuthorRole $role): void
    {
        if (! $author instanceof User || $role->isSystem()) {
            return;
        }

        $key = 'chat-message:'.$author->getKey();

        if (RateLimiter::tooManyAttempts($key, self::MAX_PER_MINUTE)) {
            $seconds = RateLimiter::availableIn($key);

            throw new RuntimeException("Слишком много сообщений подряд. Повторите через {$seconds} сек.");
        }

        RateLimiter::hit($key, 60);
    }
}
