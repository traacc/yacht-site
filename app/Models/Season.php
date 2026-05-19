<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Season extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'year',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'year'       => 'integer',
            'start_date' => 'date',
            'end_date'   => 'date',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function series(): HasMany
    {
        return $this->hasMany(Series::class);
    }

    public function regattas(): HasMany
    {
        return $this->hasMany(Regatta::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function isActive(): bool
    {
        return now()->between($this->start_date, $this->end_date);
    }

    public static function current(): ?static
    {
        return static::whereDate('start_date', '<=', now())
                     ->whereDate('end_date', '>=', now())
                     ->first();
    }

    /** @return Collection<Team> */
    public function topTeams(int $limit = 3): Collection
    {
        return $this->ratings()
            ->team()
            ->ranked()
            ->with('team')
            ->take($limit)
            ->get()
            ->pluck('team');
    }

    /** @return Collection<User> */
    public function topUsers(int $limit = 3): Collection
    {
        return $this->ratings()
            ->personal()
            ->ranked()
            ->with('user')
            ->take($limit)
            ->get()
            ->pluck('user');
    }
    
}