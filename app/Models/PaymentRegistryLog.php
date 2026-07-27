<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentRegistryLogEvent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Запись журнала изменений реестра платежей: что изменено, когда и кем.
 *
 * Записи создаёт только App\Services\PaymentRegistryLogger — журнал
 * доступен исключительно на чтение (см. PaymentRegistryLogResource).
 */
class PaymentRegistryLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'payment_registry_id',
        'registry_name',
        'registry_amount',
        'user_id',
        'actor_name',
        'event',
        'changed_fields',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'registry_amount' => 'decimal:2',
            'event' => PaymentRegistryLogEvent::class,
            'changed_fields' => 'array',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    /** Платёж (может отсутствовать, если запись реестра удалена физически). */
    public function registry(): BelongsTo
    {
        return $this->belongsTo(PaymentRegistry::class, 'payment_registry_id')->withTrashed();
    }

    /** Пользователь-актор (null — изменение выполнила система). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    /** Кто выполнил действие: живой пользователь → снапшот ФИО → «Система». */
    public function actorLabel(): string
    {
        return $this->user?->name
            ?? $this->actor_name
            ?? 'Система';
    }

    /**
     * Человекочитаемое описание изменений.
     *
     * @return list<string>
     */
    public function changesLines(): array
    {
        $lines = [];

        foreach ((array) $this->changed_fields as $change) {
            if (! is_array($change)) {
                continue;
            }

            $label = $change['label'] ?? $change['field'] ?? '—';
            $old = $change['old_label'] ?? $change['old'] ?? '—';
            $new = $change['new_label'] ?? $change['new'] ?? '—';

            $lines[] = sprintf('%s: «%s» → «%s»', $label, $old, $new);
        }

        return $lines;
    }

    /** Изменения одной строкой (для таблицы и Excel). */
    public function changesText(): string
    {
        return implode(PHP_EOL, $this->changesLines());
    }
}
