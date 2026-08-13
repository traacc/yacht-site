<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CrewJoinRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Отклик «Хочу в этот экипаж» на клубной регате.
 *
 * Заявку подаёт любой человек — зарегистрированный (тогда заполнен `user_id`)
 * или гость. Решение принимает автор заявки экипажа или администратор
 * (@see App\Actions\RegattaEntry\ResolveCrewJoinRequestAction).
 */
class CrewJoinRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'regatta_entry_id',
        'user_id',
        'name',
        'email',
        'phone',
        'message',
        'status',
        'response_note',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => CrewJoinRequestStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function regattaEntry(): BelongsTo
    {
        return $this->belongsTo(RegattaEntry::class);
    }

    /** Автор отклика, если он зарегистрирован на сайте. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Кто принял решение по отклику. */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', CrewJoinRequestStatus::Pending);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === CrewJoinRequestStatus::Pending;
    }
}
