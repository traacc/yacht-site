<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConversationRole;
use App\Enums\ConversationStatus;
use App\Enums\ConversationType;
use App\Enums\MessageAuthorRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Диалог: обращение в поддержку либо (в дальнейшем) переписка двух пользователей.
 *
 * Прочтение асимметрично: у пользователя оно личное и хранится в строке
 * участника (last_read_at), у поддержки — общее на всю команду
 * (support_read_at), потому что отвечать может любой оператор.
 *
 * @property-read ChatMessage|null $lastMessage
 */
class Conversation extends Model
{
    use HasUuids;

    protected $fillable = [
        'type',
        'status',
        'title',
        'subject_type',
        'subject_id',
        'created_by',
        'last_message_at',
        'support_read_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ConversationType::class,
            'status' => ConversationStatus::class,
            'last_message_at' => 'datetime',
            'support_read_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('created_at');
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Объект, к которому привязан диалог (объявление биржи и т.п.); у поддержки пусто. */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeSupport(Builder $query): Builder
    {
        return $query->where('type', ConversationType::Support);
    }

    /** Личные переписки пользователей (объявления бирж и барахолки). */
    public function scopeDirect(Builder $query): Builder
    {
        return $query->where('type', ConversationType::Direct);
    }

    /** Диалоги, привязанные к конкретному объекту (объявлению и т.п.). */
    public function scopeAboutSubject(Builder $query, Model $subject): Builder
    {
        return $query
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey());
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', ConversationStatus::Open);
    }

    /** Диалоги, в которых пользователь состоит участником. */
    public function scopeForParticipant(Builder $query, User $user): Builder
    {
        return $query->whereHas(
            'participants',
            fn (Builder $q) => $q->where('user_id', $user->getKey())
        );
    }

    /**
     * Обращения, требующие внимания поддержки: есть сообщения, пришедшие
     * после того, как ящик поддержки в последний раз читали.
     */
    public function scopeUnansweredForSupport(Builder $query): Builder
    {
        // Подзапрос коррелированный, поэтому внутри доступна колонка conversations.support_read_at:
        // ящик ни разу не открывали (NULL) либо сообщение пришло после последнего прочтения.
        return $query->whereHas('messages', function (Builder $q): void {
            $q->where('author_role', MessageAuthorRole::Client)
                ->where(function (Builder $unread): void {
                    $unread->whereNull('conversations.support_read_at')
                        ->orWhereColumn('chat_messages.created_at', '>', 'conversations.support_read_at');
                });
        });
    }

    /** Строка участника для пользователя (null — он не участник этого диалога). */
    public function participantFor(User $user): ?ConversationParticipant
    {
        return $this->participants()
            ->where('user_id', $user->getKey())
            ->first();
    }

    /** Непрочитанные сообщения пользователя: чужие и пришедшие после его last_read_at. */
    public function unreadCountFor(User $user): int
    {
        $participant = $this->participantFor($user);

        if (! $participant instanceof ConversationParticipant) {
            return 0;
        }

        return $this->messages()
            ->where('user_id', '!=', $user->getKey())
            ->when(
                $participant->last_read_at !== null,
                fn (Builder $q) => $q->where('created_at', '>', $participant->last_read_at)
            )
            ->count();
    }

    /** Непрочитанные сообщения клиента для общего ящика поддержки. */
    public function unreadCountForSupport(): int
    {
        return $this->messages()
            ->where('author_role', MessageAuthorRole::Client)
            ->when(
                $this->support_read_at !== null,
                fn (Builder $q) => $q->where('created_at', '>', $this->support_read_at)
            )
            ->count();
    }

    public function isUnansweredBySupport(): bool
    {
        return $this->unreadCountForSupport() > 0;
    }

    public function isClosed(): bool
    {
        return $this->status === ConversationStatus::Closed;
    }

    /** Участники, кроме указанного пользователя, — получатели уведомления. */
    public function otherParticipants(?User $except = null): HasMany
    {
        // when() проксируется в Builder, поэтому в замыкание приходит именно он.
        return $this->participants()
            ->when($except !== null, fn (Builder $q) => $q->where('user_id', '!=', $except->getKey()));
    }

    /** Клиент, обратившийся в поддержку. */
    public function client(): ?User
    {
        return $this->participants()
            ->where('role', ConversationRole::Client)
            ->with('user')
            ->first()?->user;
    }
}
