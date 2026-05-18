<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Album extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'title',
        'description',
        'cover_url',
        'albumable_type',
        'albumable_id',
    ];

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    /** Полиморфный родитель: Regatta, Team или null (общие альбомы) */
    public function albumable(): MorphTo
    {
        return $this->morphTo();
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class)->orderBy('sort_order');
    }

    public function photos(): HasMany
    {
        return $this->media()->where('type', 'photo');
    }

    public function videos(): HasMany
    {
        return $this->media()->where('type', 'video');
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function getCoverAttribute(): ?string
    {
        return $this->cover_url
            ?? $this->photos()->value('url');
    }
}