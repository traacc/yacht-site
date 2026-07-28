<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageAuthorRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Сообщение в диалоге.
 *
 * author_role фиксируется в момент отправки и не пересчитывается по текущей
 * системной роли автора: оператор, ставший обычным пользователем, не должен
 * задним числом «переписать» историю обращения.
 */
class ChatMessage extends Model
{
    use HasUuids;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'author_role',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'author_role' => MessageAuthorRole::class,
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Подпись автора в ленте: у поддержки — обезличенная. */
    public function authorName(): string
    {
        return match ($this->author_role) {
            MessageAuthorRole::Support => 'Поддержка',
            MessageAuthorRole::System => 'Система',
            MessageAuthorRole::Client => $this->author?->name ?? 'Пользователь',
        };
    }
}
