<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConversationRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Участник диалога и его личная отметка прочтения.
 *
 * Операторы поддержки здесь не появляются — см. Conversation::$support_read_at.
 */
class ConversationParticipant extends Model
{
    use HasUuids;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'role',
        'last_read_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => ConversationRole::class,
            'last_read_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
