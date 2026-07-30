<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdvertType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Категория объявлений: своя у каждой доски.
 */
class AdvertCategory extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'type',
        'title',
        'slug',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => AdvertType::class,
            'sort_order' => 'integer',
        ];
    }

    public function adverts(): HasMany
    {
        return $this->hasMany(Advert::class);
    }

    public function scopeOfType(Builder $query, AdvertType $type): Builder
    {
        return $query->where('type', $type);
    }

    /** Порядок задаётся drag&drop в админке; title — вторичный ключ. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }
}
