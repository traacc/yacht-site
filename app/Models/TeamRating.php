<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Командный рейтинг команды за сезон.
 */
class TeamRating extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'season_id',
        'team_id',
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

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    public function scopeRanked(Builder $query): Builder
    {
        return $query->orderBy('rank_position');
    }

    public function scopeForSeason(Builder $query, Season $season): Builder
    {
        return $query->where('season_id', $season->id);
    }
}
