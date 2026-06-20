<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TeamMember extends Pivot
{
    use  HasFactory,HasUuids;

    public $table = 'team_members';

    protected $fillable = [
        'team_id',
        'user_id',
        'role',
        'status',
        'is_permanent',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'is_permanent' => 'boolean',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function regattaEntryCrews(): HasMany
    {
        return $this->hasMany(RegattaEntryCrew::class);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function isActive(): bool   { return $this->status === 'active'; }
    public function isPending(): bool  { return $this->status === 'invited'; }
    public function isLeft(): bool     { return $this->status === 'left'; }
    public function isOrganizer(): bool { return $this->role === 'organizer'; }
    public function isAdmin(): bool    { return in_array($this->role, ['organizer', 'team_admin']); }

    public function accept(): void
    {
        $this->update([
            'status'    => 'active',
            'joined_at' => now(),
        ]);
    }

    public function decline(): void
    {
        $this->update(['status' => 'declined']);
    }

    public function leave(): void
    {
        $this->update(['status' => 'left']);
    }
}