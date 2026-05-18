<?php

namespace App\Models;

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
        'description',
        'schedule',
        'race_days_count',
        'races_count',
        'prizes',
    ];

    protected function casts(): array
    {
        return [
            'level_coefficient' => 'decimal:2',
            'date_start'        => 'date',
            'date_end'          => 'date',
            'race_days_count'   => 'integer',
            'races_count'       => 'integer',
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
        return $this->hasMany(RegattaEvents::class)->orderBy('event_number');
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
        return $this->hasMany(RegattaResult::class)->orderBy('final_position');
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

        if (! $end || $start->isSameDay($end)) {
            return $start->format('j F Y');
        }

        if ($start->isSameMonth($end) && $start->isSameYear($end)) {
            return $start->format('j').'–'.$end->format('j F Y');
        }

        if ($start->isSameYear($end)) {
            return $start->format('j F').' – '.$end->format('j F Y');
        }

        return $start->format('j F Y').' – '.$end->format('j F Y');
    }
    
    
}