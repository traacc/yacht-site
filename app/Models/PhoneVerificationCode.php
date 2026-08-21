<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Одноразовый код подтверждения телефона, отправленный по SMS.
 *
 * В БД хранится только sha256-хеш кода (как у App\Models\TelegramLinkToken).
 * Отработавшие записи чистит планировщик (`model:prune`, ежедневно).
 */
class PhoneVerificationCode extends Model
{
    use HasUuids, Prunable;

    /** Длина кода в SMS. */
    public const CODE_LENGTH = 6;

    /** Срок жизни кода. */
    public const TTL_MINUTES = 10;

    /** Сколько раз можно ошибиться при вводе, прежде чем код сгорит. */
    public const MAX_ATTEMPTS = 5;

    /** Пауза между отправками кода одному пользователю, секунд. */
    public const RESEND_COOLDOWN_SECONDS = 60;

    protected $fillable = [
        'user_id',
        'phone',
        'code_hash',
        'attempts',
        'expires_at',
        'confirmed_at',
        'provider_message_id',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Хеш кода для хранения/сравнения (в БД plaintext не попадает). */
    public static function hashCode(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /** Непогашенные, не истёкшие и не исчерпавшие попытки коды. */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('confirmed_at')
            ->where('expires_at', '>', now())
            ->where('attempts', '<', self::MAX_ATTEMPTS);
    }

    public function isUsable(): bool
    {
        return $this->confirmed_at === null
            && $this->expires_at?->isFuture() === true
            && $this->attempts < self::MAX_ATTEMPTS;
    }

    /** Сколько секунд осталось ждать до повторной отправки (0 — можно слать). */
    public function secondsUntilResend(): int
    {
        $readyAt = $this->created_at?->addSeconds(self::RESEND_COOLDOWN_SECONDS);

        if ($readyAt === null || $readyAt->isPast()) {
            return 0;
        }

        return (int) ceil(now()->diffInSeconds($readyAt, absolute: true));
    }

    /** Отработавшие коды не нужны — храним неделю на случай разбора инцидентов. */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<', now()->subWeek());
    }
}
