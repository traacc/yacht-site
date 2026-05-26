<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegattaEvents extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'regatta_id',
        'name',
        'description',
        'event_type',
        'event_datetime',
    ];

    protected function casts(): array
    {
        return [
            'event_datetime'   => 'datetime',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function regatta(): BelongsTo
    {
        return $this->belongsTo(Regatta::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(RaceResult::class)->orderBy('position');
    }
}