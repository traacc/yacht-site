<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Rating extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'season_id',
        'team_id',
        'user_id',
        'rating_type',
        'total_points',
        'rank_position',
    ];

    protected function casts(): array
    {
        return [
            'total_points'  => 'decimal:3',
            'rank_position' => 'integer',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    public function scopeTeam(Builder $query): Builder
    {
        return $query->where('rating_type', 'team');
    }

    public function scopePersonal(Builder $query): Builder
    {
        return $query->where('rating_type', 'personal');
    }

    public function scopeRanked(Builder $query): Builder
    {
        return $query->orderBy('rank_position');
    }

    public function scopeForSeason(Builder $query, Season $season): Builder
    {
        return $query->where('season_id', $season->id);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function isTeamRating(): bool    { return $this->rating_type === 'team'; }
    public function isPersonalRating(): bool { return $this->rating_type === 'personal'; }
}