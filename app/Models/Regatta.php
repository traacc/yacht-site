<?php

namespace App\Models;

use App\Enums\RegattaStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Regatta extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'season_id',
        'series_id',
        'name',
        'level_coefficient',
        'date_start',
        'date_end',
        'background_image',
        'location',
        'water_area',
        'short_description',
        'description',
        'coordinates',
        'schedule',
        'race_days_count',
        'races_count',
        'prizes',
        'regatta_status',
    ];

    protected function casts(): array
    {
        return [
            'coordinates'       => 'array',
            'level_coefficient' => 'decimal:2',
            'date_start'        => 'date',
            'date_end'          => 'date',
            'race_days_count'   => 'integer',
            'races_count'       => 'integer',
            'regatta_status'    => RegattaStatus::class,
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    public function races(): HasMany
    {
        return $this->hasMany(RegattaEvents::class)->orderBy('event_datetime');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(RegattaEntry::class);
    }

    /** Одобренные заявки */
    public function approvedEntries(): HasMany
    {
        return $this->entries()->where('status', 'approved');
    }

    public function results(): HasMany
    {
        return $this->hasMany(RegattaResult::class);
    }

    /** Все позиции (items) регаты через результат */
    public function resultItems(): HasManyThrough
    {
        return $this->hasManyThrough(RegattaResultItem::class, RegattaResult::class);
    }

    /** Все результаты отдельных гонок регаты */
    public function raceResults(): HasManyThrough
    {
        return $this->hasManyThrough(RaceResult::class, RegattaEvents::class);
    }

    /** Документы регаты (положение, инструкции по гонкам, протоколы) */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /** Альбомы регаты */
    public function albums(): MorphMany
    {
        return $this->morphMany(Album::class, 'albumable');
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    public function scopeUpcoming($query)
    {
        return $query->where('date_start', '>=', now());
    }

    public function scopeClosest($query)
    {
        return $query->upcoming()->orderBy('date_start');
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function isUpcoming(): bool { return $this->date_start->isFuture(); }

    /** Get the closest upcoming regatta by start date */
    public static function closestUpcoming(): ?self
    {
        return static::closest()->first();
    }

    public function startsInLessThanMonth(): bool
    {
        return $this->date_start && $this->date_start->isFuture() && now()->diffInDays($this->date_start, false) < 30;
    }

    public function isActive(): bool   { return now()->between($this->date_start, $this->date_end); }
    public function isFinished(): bool { return $this->date_end->isPast(); }

    public function hasTeam(Team $team): bool
    {
        return $this->entries()
                    ->where('team_id', $team->id)
                    ->whereIn('status', ['pending', 'approved'])
                    ->exists();
    }

    /** Human-friendly date range for the regatta */
    public function dateRange(): string
    {
        $start = $this->date_start;
        $end = $this->date_end;

        if (! $start) {
            return '';
        }

        // Single day event: "19 May 2026" / "May 19, 2026"
        if (! $end || $start->isSameDay($end)) {
            return $start->isoFormat('ll');
        }

        // Same month and year: "14–16 May 2026"
        if ($start->isSameMonth($end) && $start->isSameYear($end)) {
            return $start->isoFormat('D') . '–' . $end->isoFormat('D MMMM');
        }

        // Same year, different months: "28 May – 2 June 2026"
        if ($start->isSameYear($end)) {
            // 'll' is the short/standard date without the year (e.g., "28 May" or "May 28")
            return $start->isoFormat('D MMMM') . ' – ' . $end->isoFormat('D MMMM');
        }

        // Different years: "30 December 2026 – 3 January 2027"
        return $start->isoFormat('LL') . ' – ' . $end->isoFormat('LL');
    }
    
    
}