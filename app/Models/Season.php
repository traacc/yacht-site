<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
            'year' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
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

    public function teamRatings(): HasMany
    {
        return $this->hasMany(TeamRating::class);
    }

    public function personalRatings(): HasMany
    {
        return $this->hasMany(PersonalRating::class);
    }

    public function foreignRegattas(): HasMany
    {
        return $this->hasMany(ForeignRegatta::class);
    }

    public function galleries(): HasMany
    {
        return $this->hasMany(Gallery::class);
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    /**
     * Сезоны, в которых есть хоть какие-то данные.
     *
     * Пустые сезоны (заведённые «впрок» или оставшиеся от импорта) не должны
     * засорять переключатели сезона на сайте и в админке.
     */
    public function scopeInUse(Builder $query): Builder
    {
        return $query->where(fn (Builder $inner) => $inner
            ->whereHas('regattas')
            ->orWhereHas('series')
            ->orWhereHas('foreignRegattas')
            ->orWhereHas('teamRatings')
            ->orWhereHas('personalRatings')
            ->orWhereHas('galleries'));
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function isActive(): bool
    {
        return now()->between($this->start_date, $this->end_date);
    }

    /**
     * Есть ли у сезона данные — тогда его нельзя удалять: серии и рейтинги
     * удалятся каскадом, а регаты (в т.ч. удалённые в корзину) держат FK.
     */
    public function isInUse(): bool
    {
        return static::query()->inUse()->whereKey($this->getKey())->exists()
            || $this->regattas()->withTrashed()->exists();
    }

    /**
     * Сезон календарного года. Сезоны заводятся на год целиком, поэтому
     * недостающий создаём сами — иначе регату нового года некуда отнести.
     */
    public static function forYear(int $year): static
    {
        return static::firstOrCreate(['year' => $year], [
            'start_date' => "{$year}-01-01",
            'end_date' => "{$year}-12-31",
        ]);
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
        return $this->teamRatings()
            ->ranked()
            ->with('team')
            ->take($limit)
            ->get()
            ->pluck('team');
    }

    /** @return Collection<User> */
    public function topUsers(int $limit = 3): Collection
    {
        return $this->personalRatings()
            ->ranked()
            ->with('user')
            ->take($limit)
            ->get()
            ->pluck('user');
    }
}
