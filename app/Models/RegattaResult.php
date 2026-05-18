<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegattaResult extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'regatta_id',
        'team_id',
        'yacht_id',
        'total_points',
        'final_position',
    ];

    protected function casts(): array
    {
        return [
            'total_points'   => 'decimal:3',
            'final_position' => 'integer',
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

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    public function scopeRanked($query)
    {
        return $query->orderBy('final_position');
    }

    public function scopeTopN($query, int $n)
    {
        return $query->ranked()->limit($n);
    }
}