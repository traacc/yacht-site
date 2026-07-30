<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Filament\Pages\SupportChat;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Support\AccessControl;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Права на скачивание вложения чата.
 *
 * Вложения лежат на приватном диске и отдаются маршрутом `chat.attachment`:
 * в переписке с поддержкой встречаются документы, поэтому прямой публичный
 * URL недопустим. Подписанные временные ссылки тоже не годятся — они меняются
 * на каждом рендере, а лента перечитывается опросом раз в 5 секунд, и браузер
 * перекачивал бы все картинки заново.
 */
class ChatAttachments
{
    /** Конверсии, которые разрешено запрашивать через маршрут. */
    private const ALLOWED_CONVERSIONS = ['preview'];

    public function allows(Media $media, ?User $user, ?string $conversion = null): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if ($media->collection_name !== ChatMessage::ATTACHMENTS) {
            return false;
        }

        if ($conversion !== null && ! in_array($conversion, self::ALLOWED_CONVERSIONS, true)) {
            return false;
        }

        $message = $media->model;

        if (! $message instanceof ChatMessage) {
            return false;
        }

        // Оператор поддержки видит переписку любого ОБРАЩЕНИЯ. На личные
        // переписки пользователей это исключение не распространяется: там
        // поддержка не участвует и заглядывать во вложения не должна.
        if (AccessControl::allows(SupportChat::class, $user)
            && Conversation::query()->whereKey($message->conversation_id)->support()->exists()
        ) {
            return true;
        }

        return Conversation::query()
            ->whereKey($message->conversation_id)
            ->forParticipant($user)
            ->exists();
    }

    /** Нормализует запрошенную конверсию: пусто/неизвестное — отдаём оригинал. */
    public function resolveConversion(?string $conversion): ?string
    {
        return in_array($conversion, self::ALLOWED_CONVERSIONS, true) ? $conversion : null;
    }
}
