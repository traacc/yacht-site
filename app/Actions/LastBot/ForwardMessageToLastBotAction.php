<?php

declare(strict_types=1);

namespace App\Actions\LastBot;

use App\Enums\ConversationType;
use App\Enums\MessageAuthorRole;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Services\LastBot\LastBotClient;
use App\Services\LastBot\LastBotException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Отправляет сообщение клиента из обращения в поддержку в LastBot.
 *
 * Обращению соответствует один диалог LastBot (conversations.lastbot_thread_id).
 * Первая реплика создаёт диалог через REST, остальные уходят в него через
 * ActionCable. Если LastBot диалог закрыл (task.closed_at), следующая реплика
 * открывает новый — так же поступает их виджет. Контакт LastBot один на
 * пользователя: uuid берётся из прошлых обращений, чтобы у их операторов вся
 * история человека была в одной карточке.
 *
 * Уходит только то, что написал клиент: ответы наших операторов из Filament
 * в LastBot не попадают — писать в их диалог от имени оператора нечем.
 * Вложения не передаются, вместо них — пометка в тексте.
 */
class ForwardMessageToLastBotAction
{
    public function __construct(
        private readonly LastBotClient $client,
        private readonly SyncLastBotThreadAction $sync,
    ) {}

    /** true — сообщение отправлено сейчас (false — не нужно или уже было). */
    public function handle(ChatMessage $message): bool
    {
        if (! $this->client->isEnabled() || ! self::applies($message)) {
            return false;
        }

        $conversation = $message->conversation;

        // Пересылка и опрос одного обращения идут строго по очереди: иначе две
        // первые реплики подряд создали бы в LastBot два диалога.
        return Cache::lock(SyncLastBotThreadAction::lockKey($conversation), 120)->block(60, function () use ($message, $conversation): bool {
            $message->refresh();
            $conversation->refresh();

            if ($message->lastbot_forwarded_at !== null) {
                return false;
            }

            $contents = $this->contents($message);

            if ($contents === '') {
                return false;
            }

            if ($this->openThreadExists($conversation)) {
                $confirmed = $this->client->sendToThread(
                    $conversation->lastbot_thread_id,
                    $conversation->lastbot_contact_uuid,
                    $contents,
                );

                if (! $confirmed) {
                    // Повтор дал бы дубль, поэтому только отмечаем в логе.
                    Log::warning('LastBot: no echo for forwarded chat message', [
                        'conversation' => $conversation->getKey(),
                        'message' => $message->getKey(),
                    ]);
                }
            } else {
                $this->createThread($conversation, $contents);
            }

            $message->forceFill(['lastbot_forwarded_at' => now()])->saveQuietly();

            return true;
        });
    }

    /** Пересылаются только реплики клиента в обращениях в поддержку. */
    public static function applies(ChatMessage $message, ?Conversation $conversation = null): bool
    {
        $conversation ??= $message->conversation;

        return $message->author_role === MessageAuthorRole::Client
            && $conversation?->type === ConversationType::Support;
    }

    /**
     * Есть ли диалог, в который можно дописать. Заодно забираем из него то,
     * что пришло с прошлого опроса, — чтобы ответы не обгоняли вопросы в ленте.
     */
    private function openThreadExists(Conversation $conversation): bool
    {
        if ($conversation->lastbot_thread_id === null || $conversation->lastbot_contact_uuid === null) {
            return false;
        }

        try {
            $thread = $this->client->thread(
                $conversation->lastbot_thread_id,
                $conversation->lastbot_contact_uuid,
                SyncLastBotThreadAction::sessionId($conversation),
            );
        } catch (LastBotException $e) {
            // Диалог удалён в LastBot — начинаем новый, иначе обращение застрянет.
            if (in_array($e->getCode(), [403, 404], true)) {
                return false;
            }

            throw $e;
        }

        return ! $this->sync->import($conversation, $thread)->closed;
    }

    private function createThread(Conversation $conversation, string $contents): void
    {
        $email = $this->email($conversation);
        $thread = $this->client->createThread(
            $this->contactUuid($conversation),
            $contents,
            SyncLastBotThreadAction::sessionId($conversation),
            $email,
        );

        $conversation->forceFill([
            'lastbot_thread_id' => (string) $thread['id'],
            'lastbot_contact_uuid' => (string) $thread['contact']['uuid'],
        ])->save();
    }

    /** null — контакта ещё нет, LastBot заведёт его вместе с диалогом. */
    private function contactUuid(Conversation $conversation): ?string
    {
        if ($conversation->lastbot_contact_uuid !== null) {
            return $conversation->lastbot_contact_uuid;
        }

        $client = $conversation->client();

        return $client instanceof User
            ? Conversation::query()
                ->support()
                ->forParticipant($client)
                ->whereNotNull('lastbot_contact_uuid')
                ->latest('last_message_at')
                ->value('lastbot_contact_uuid')
            : null;
    }

    private function email(Conversation $conversation): ?string
    {
        if (! config('services.lastbot.send_user_email')) {
            return null;
        }

        return $conversation->client()?->email;
    }

    private function contents(ChatMessage $message): string
    {
        $body = trim((string) $message->body);
        $files = $message->getMedia(ChatMessage::ATTACHMENTS)->count();

        if ($files === 0) {
            return $body;
        }

        // Файлы остаются только у нас: их видит оператор в админке сайта.
        return trim($body."\n\n[Пользователь приложил файлов: {$files}. Они доступны оператору на сайте.]");
    }
}
