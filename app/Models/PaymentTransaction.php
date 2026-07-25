<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentProviderCode;
use App\Enums\PaymentTransactionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Транзакция эквайринга — попытка онлайн-оплаты записи реестра платежей.
 * У одной записи реестра может быть несколько транзакций
 * (неудачные попытки + одна успешная).
 */
class PaymentTransaction extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'payment_registry_id',
        'user_id',
        'provider',
        'external_id',
        'status',
        'amount',
        'currency',
        'description',
        'confirmation_url',
        'idempotence_key',
        'payload',
        'paid_at',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'provider' => PaymentProviderCode::class,
            'status' => PaymentTransactionStatus::class,
            'payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    /** Запись бухгалтерского реестра, которую оплачивает транзакция. */
    public function registry(): BelongsTo
    {
        return $this->belongsTo(PaymentRegistry::class, 'payment_registry_id');
    }

    /** Плательщик (инициатор оплаты). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
