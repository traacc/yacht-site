<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class RegattaEntryCrew extends Pivot
{
    public $table = 'regatta_entry_crew';

    public $incrementing = true;

    protected $fillable = [
        'regatta_entry_id',
        'team_member_id',
        'user_id',
        'full_name',
        'email',
        'phone',
        'role',
    ];

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function regattaEntry(): BelongsTo
    {
        return $this->belongsTo(RegattaEntry::class);
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }

    /**
     * Участник сборного экипажа, попавший в заявку без команды —
     * индивидуальная заявка или принятый отклик «Хочу в этот экипаж».
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    /** Пользователь за строкой экипажа: напрямую либо через участника команды. */
    public function resolvedUserId(): ?string
    {
        return $this->user_id ?? $this->teamMember?->user_id;
    }

    /** Имя для списков экипажа: из аккаунта, иначе введённое вручную. */
    public function displayName(): string
    {
        return $this->teamMember?->user?->name
            ?? $this->user?->name
            ?? $this->full_name
            ?? '—';
    }
}
