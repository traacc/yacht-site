<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegattaResultItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'regatta_result_id',
        'team_id',
        'yacht_id',
        'not_participate',
        'total_points',
        'final_position',
    ];

    protected function casts(): array
    {
        return [
            //'total_points'   => 'decimal:3',
            //'final_position' => 'integer',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function regattaResult(): BelongsTo
    {
        return $this->belongsTo(RegattaResult::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }
}