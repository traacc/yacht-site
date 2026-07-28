<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;

/**
 * Отмечает диалог прочитанным.
 *
 * Прочтение асимметрично: у пользователя оно личное (строка участника),
 * у поддержки — общее на команду, потому что отвечать может любой оператор.
 */
class MarkConversationReadAction
{
    public function handle(Conversation $conversation, ?User $reader, bool $asSupport = false): void
    {
        if ($asSupport) {
            $conversation->forceFill(['support_read_at' => now()])->save();

            return;
        }

        if (! $reader instanceof User) {
            return;
        }

        $participant = $conversation->participantFor($reader);

        if ($participant instanceof ConversationParticipant) {
            $participant->update(['last_read_at' => now()]);
        }
    }
}
