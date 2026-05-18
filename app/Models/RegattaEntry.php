<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegattaEntry extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'regatta_id',
        'team_id',
        'yacht_id',
        'status',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function regatta(): BelongsTo
    {
        return $this->belongsTo(Regatta::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }

    /** Результаты этой заявки по отдельным гонкам */
    public function raceResults(): HasMany
    {
        return $this->hasMany(RaceResult::class)->orderBy('race_id');
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function isPending(): bool   { return $this->status === 'pending'; }
    public function isApproved(): bool  { return $this->status === 'approved'; }
    public function isRejected(): bool  { return $this->status === 'rejected'; }
    public function isWithdrawn(): bool { return $this->status === 'withdrawn'; }

    public function approve(): void
    {
        $this->update([
            'status'       => 'approved',
            'submitted_at' => $this->submitted_at ?? now(),
        ]);
    }

    public function reject(): void
    {
        $this->update(['status' => 'rejected']);
    }

    public function withdraw(): void
    {
        $this->update(['status' => 'withdrawn']);
    }

    /** Суммарные очки по всем гонкам этой заявки */
    public function totalPoints(): float
    {
        return (float) $this->raceResults()->sum('points');
    }
}