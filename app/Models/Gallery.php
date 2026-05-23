<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Gallery extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'gallery';

    protected $fillable = [
        'season_id',
        'regatta_id',
        'name',
        'water_area',
        'date',
        'cover_path',
        'images',
        'is_published',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'date'         => 'date',
            'images'       => 'array',
            'is_published' => 'boolean',
            'sort_order'   => 'integer',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function regatta(): BelongsTo
    {
        return $this->belongsTo(Regatta::class);
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderByDesc('date');
    }
}
