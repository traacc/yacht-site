<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Telegram\TelegramUpdateHandler;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Привязка аккаунта сайта к личному чату с ботом в Telegram.
 *
 * Создаётся, когда пользователь открывает deep-link из личного кабинета и
 * нажимает Start: бот не может написать первым, пока чат не начат.
 *
 * @see TelegramUpdateHandler
 */
class TelegramAccount extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'chat_id',
        'username',
        'first_name',
        'linked_at',
        'blocked_at',
    ];

    protected function casts(): array
    {
        return [
            'linked_at' => 'datetime',
            'blocked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Пользователь заблокировал бота — доставка невозможна до повторного /start. */
    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    public function displayName(): string
    {
        return match (true) {
            filled($this->username) => '@'.$this->username,
            filled($this->first_name) => (string) $this->first_name,
            default => (string) $this->chat_id,
        };
    }
}
