<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Enums\ConversationStatus;
use App\Enums\MessageAuthorRole;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Notifications\ChatMessageReceivedNotification;
use App\Services\Chat\ChatAttachmentProcessor;
use App\Services\Chat\ChatRecipients;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
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

    /** Вложений в одном сообщении. */
    public const MAX_ATTACHMENTS = 5;

    /** Что вообще принимаем во вложения. */
    public const ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif',
        'application/pdf',
    ];

    public function __construct(
        private readonly ChatRecipients $recipients,
        private readonly ChatAttachmentProcessor $attachmentProcessor,
    ) {}

    /**
     * @param  list<UploadedFile>  $attachments
     */
    public function handle(
        Conversation $conversation,
        ?User $author,
        MessageAuthorRole $role,
        string $body = '',
        array $attachments = [],
    ): ChatMessage {
        $body = trim($body);

        // Сообщение может состоять только из вложений — но не быть пустым совсем.
        if ($body === '' && $attachments === []) {
            throw new RuntimeException('Нельзя отправить пустое сообщение.');
        }

        if (mb_strlen($body) > self::MAX_LENGTH) {
            throw new RuntimeException('Сообщение слишком длинное.');
        }

        $this->guardAttachments($attachments);
        $this->guardRate($author, $role);

        // Файлы готовим до транзакции: сбой обработки не должен оставлять
        // сообщение-пустышку в переписке.
        $prepared = $this->attachmentProcessor->process(
            $attachments,
            // Ограничение по размеру — только для пользователей: по ТЗ админы
            // загружают файлы любого размера.
            compress: $role === MessageAuthorRole::Client,
        );

        // Считаем до вставки: своё же сообщение иначе попадёт в «непрочитанные».
        $recipients = $this->resolveRecipients($conversation, $author, $role);

        $excerpt = $this->excerpt($body, count($prepared));

        $message = DB::transaction(function () use ($conversation, $author, $role, $body, $excerpt): ChatMessage {
            $message = $conversation->messages()->create([
                'user_id' => $author?->getKey(),
                'author_role' => $role,
                'body' => $body === '' ? null : $body,
            ]);

            $conversation->forceFill([
                'last_message_at' => $message->created_at,
                // Новое сообщение переоткрывает закрытое обращение.
                'status' => ConversationStatus::Open,
                'title' => $conversation->title ?? Str::limit($excerpt, 80),
            ])->save();

            return $message;
        });

        $this->attachFiles($message, $prepared);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new ChatMessageReceivedNotification(
                authorName: $message->authorName(),
                excerpt: Str::limit($excerpt, 200),
                forSupport: $role === MessageAuthorRole::Client,
            ));
        }

        return $message;
    }

    /**
     * Файлы прикрепляем после коммита и не роняем на этом отправку: потерять
     * вложение неприятно, потерять вместе с ним текст сообщения — хуже.
     *
     * @param  list<array{bytes: string, filename: string, mime: string}>  $prepared
     */
    private function attachFiles(ChatMessage $message, array $prepared): void
    {
        foreach ($prepared as $file) {
            try {
                $message->addMediaFromString($file['bytes'])
                    ->usingFileName($file['filename'])
                    ->usingName(pathinfo($file['filename'], PATHINFO_FILENAME))
                    ->toMediaCollection(ChatMessage::ATTACHMENTS);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /** Текст для темы обращения и для уведомления. */
    private function excerpt(string $body, int $attachmentCount): string
    {
        if ($body !== '') {
            return $body;
        }

        return $attachmentCount === 1
            ? 'Вложение: 1 файл'
            : 'Вложение: '.$attachmentCount.' файла(ов)';
    }

    /**
     * Лимиты вложений живут на сервере: ограничения в шаблоне — подсказка
     * пользователю, а не защита.
     *
     * @param  list<UploadedFile>  $attachments
     */
    private function guardAttachments(array $attachments): void
    {
        if (count($attachments) > self::MAX_ATTACHMENTS) {
            throw new RuntimeException('Можно приложить не более '.self::MAX_ATTACHMENTS.' файлов к одному сообщению.');
        }

        foreach ($attachments as $file) {
            if (! $file instanceof UploadedFile) {
                throw new RuntimeException('Некорректное вложение.');
            }

            if (! in_array((string) $file->getMimeType(), self::ALLOWED_MIMES, true)) {
                throw new RuntimeException('Такой тип файла приложить нельзя: '.$file->getClientOriginalName());
            }
        }
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
