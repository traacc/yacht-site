<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RaceResult extends Model
{
    use HasFactory, HasUuids;

    /**
     * Коды судейских пенальти (парусный спорт, правила ISAF).
     * DNF — не финишировал, DNS — не стартовал, DSQ — дисквалификация,
     * OCS — за линией старта, RET — отказался от гонки.
     */
    public const PENALTY_CODES = ['DNF', 'DNS', 'DSQ', 'OCS', 'RET', 'BFD', 'UFD'];

    protected $fillable = [
        'race_id',
        'regatta_entry_id',
        'position',
        'points',
        'penalty_code',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'points'   => 'decimal:3',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function race(): BelongsTo
    {
        return $this->belongsTo(RegattaEvents::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(RegattaEntry::class, 'regatta_entry_id');
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function hasPenalty(): bool
    {
        return $this->penalty_code !== null;
    }

    public function getDisplayPositionAttribute(): string
    {
        return $this->penalty_code ?? (string) $this->position;
    }
}