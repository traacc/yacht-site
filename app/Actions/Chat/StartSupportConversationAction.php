<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Enums\ConversationRole;
use App\Enums\ConversationStatus;
use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Возвращает диалог пользователя со службой поддержки.
 *
 * Открытое обращение переиспользуется, а не создаётся заново при каждом
 * открытии окна чата: иначе у оператора копился бы список пустых тредов,
 * а переписка рвалась бы на куски.
 */
class StartSupportConversationAction
{
    public function handle(User $user): Conversation
    {
        return DB::transaction(function () use ($user): Conversation {
            $existing = Conversation::query()
                ->support()
                ->open()
                ->forParticipant($user)
                ->latest('last_message_at')
                ->first();

            if ($existing instanceof Conversation) {
                return $existing;
            }

            $conversation = Conversation::create([
                'type' => ConversationType::Support,
                'status' => ConversationStatus::Open,
                'created_by' => $user->getKey(),
            ]);

            $conversation->participants()->create([
                'user_id' => $user->getKey(),
                'role' => ConversationRole::Client,
            ]);

            return $conversation;
        });
    }
}
