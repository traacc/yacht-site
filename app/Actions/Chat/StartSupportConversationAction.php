<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Enums\ChatContext;
use App\Enums\ConversationRole;
use App\Enums\ConversationStatus;
use App\Enums\ConversationType;
use App\Enums\MessageAuthorRole;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
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
    public function handle(User $user, ?ChatContext $context = null, ?Model $subject = null): Conversation
    {
        $conversation = DB::transaction(function () use ($user, $context, $subject): Conversation {
            $existing = Conversation::query()
                ->support()
                ->open()
                ->forParticipant($user)
                ->latest('last_message_at')
                ->first();

            if ($existing instanceof Conversation) {
                $this->fillMissingContext($existing, $context, $subject);

                return $existing;
            }

            $conversation = Conversation::create([
                'type' => ConversationType::Support,
                'status' => ConversationStatus::Open,
                'title' => $context?->label(),
                'subject_type' => $subject === null ? null : $subject::class,
                'subject_id' => $subject?->getKey(),
                'created_by' => $user->getKey(),
            ]);

            $conversation->participants()->create([
                'user_id' => $user->getKey(),
                'role' => ConversationRole::Client,
            ]);

            return $conversation;
        });

        $this->announceContext($conversation, $context, $subject);

        return $conversation;
    }

    /**
     * У переиспользованного обращения тему и объект дозаполняем только если они
     * пусты: перезаписывать контекст первого вопроса вторым — терять историю.
     */
    private function fillMissingContext(Conversation $conversation, ?ChatContext $context, ?Model $subject): void
    {
        if ($context === null) {
            return;
        }

        $attributes = [];

        if ($conversation->title === null) {
            $attributes['title'] = $context->label();
        }

        if ($subject !== null && $conversation->subject_type === null) {
            $attributes['subject_type'] = $subject::class;
            $attributes['subject_id'] = $subject->getKey();
        }

        if ($attributes !== []) {
            $conversation->forceFill($attributes)->save();
        }
    }

    /**
     * Контекст записывается в ленту системным сообщением: в длинном треде
     * оператор иначе не понял бы, откуда пришёл очередной вопрос. Системные
     * сообщения никого не уведомляют (@see SendChatMessageAction).
     *
     * Только для тредов, где переписка уже началась. В новом обращении контекст
     * и так виден в теме, а системное сообщение проставило бы last_message_at —
     * и брошенный, ни разу не заполненный тред всплыл бы у оператора как активный.
     */
    private function announceContext(Conversation $conversation, ?ChatContext $context, ?Model $subject): void
    {
        if ($context === null || $conversation->messages()->doesntExist()) {
            return;
        }

        $body = 'Обращение по: '.$context->label();

        if ($subject !== null) {
            $body .= ' — '.($subject->getAttribute('title') ?? $subject->getKey());
        }

        // Не повторяем ту же запись, если пользователь несколько раз открыл
        // окно чата из одного и того же места.
        $alreadyAnnounced = $conversation->messages()
            ->where('author_role', MessageAuthorRole::System)
            ->where('body', $body)
            ->exists();

        if ($alreadyAnnounced) {
            return;
        }

        app(SendChatMessageAction::class)->handle(
            conversation: $conversation,
            author: null,
            role: MessageAuthorRole::System,
            body: $body,
        );
    }
}
