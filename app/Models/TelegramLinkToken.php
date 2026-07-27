<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Одноразовый токен привязки Telegram. Передаётся боту как payload команды
 * /start, поэтому в БД хранится только sha256-хеш (как у App\Models\ApiClient).
 */
class TelegramLinkToken extends Model
{
    use HasUuids;

    /** Срок жизни токена привязки. */
    public const TTL_MINUTES = 15;

    protected $fillable = [
        'user_id',
        'token_hash',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Хеш токена для хранения/поиска (в БД plaintext не попадает). */
    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /** Непогашенные и не истёкшие токены. */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('used_at')->where('expires_at', '>', now());
    }
}
