<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Enums\ConversationRole;
use App\Enums\ConversationStatus;
use App\Enums\ConversationType;
use App\Models\Advert;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Переписка покупателя с автором объявления.
 *
 * Диалог заводится один на пару «объявление + собеседник» и переиспользуется:
 * иначе каждое нажатие «Написать автору» плодило бы новый пустой тред, а
 * история рвалась бы на куски. Закрытым такой диалог не бывает — закрывать
 * его, в отличие от обращения в поддержку, некому.
 */
class StartDirectConversationAction
{
    public function handle(User $initiator, Advert $advert): Conversation
    {
        $author = $advert->author;

        if (! $author instanceof User) {
            throw new RuntimeException('У объявления нет автора.');
        }

        if ($author->is($initiator)) {
            throw new RuntimeException('Нельзя написать самому себе.');
        }

        if (! $advert->isVisible()) {
            throw new RuntimeException('Объявление недоступно.');
        }

        return DB::transaction(function () use ($initiator, $author, $advert): Conversation {
            $existing = Conversation::query()
                ->direct()
                ->aboutSubject($advert)
                // forParticipant — обычный whereHas, поэтому чейнится дважды:
                // нужен диалог именно этой пары, а не любой по объявлению.
                ->forParticipant($initiator)
                ->forParticipant($author)
                ->first();

            if ($existing instanceof Conversation) {
                return $existing;
            }

            $conversation = Conversation::create([
                'type' => ConversationType::Direct,
                'status' => ConversationStatus::Open,
                'title' => $advert->title,
                'subject_type' => $advert::class,
                'subject_id' => $advert->getKey(),
                'created_by' => $initiator->getKey(),
            ]);

            // Обе стороны — равноправные участники: роль Client зарезервирована
            // за обращениями в поддержку.
            foreach ([$initiator, $author] as $participant) {
                $conversation->participants()->create([
                    'user_id' => $participant->getKey(),
                    'role' => ConversationRole::Member,
                ]);
            }

            return $conversation;
        });
    }
}
