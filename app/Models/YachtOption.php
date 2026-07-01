<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * Опция яхты (например «Тип паруса», «Материал корпуса»).
 *
 * Сама опция — это атрибут, а конкретные варианты выбора хранятся
 * в {@see YachtOptionValue}. На яхте выбирается не более одного значения
 * на каждую опцию (см. yacht_option_selections).
 */
class YachtOption extends Model
{
    use HasUuids;

    protected $table = 'yacht_options';

    protected $fillable = [
        'key',
        'label',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    // ──────────────────────────────────────────────
    // Cache
    // ──────────────────────────────────────────────

    private const CACHE_KEY_ALL = 'yacht_options:all_with_values';
    private const CACHE_TTL = 3600;

    protected static function booted(): void
    {
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY_ALL);
    }

    /**
     * Все опции вместе со своими значениями, отсортированные по sort_order.
     *
     * @return Collection<int, static>
     */
    public static function cachedAllWithValues(): Collection
    {
        $data = Cache::remember(self::CACHE_KEY_ALL, self::CACHE_TTL, fn () =>
            static::with(['values' => fn ($q) => $q->orderBy('sort_order')->orderBy('label')])
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get()
                ->map(fn (self $option) => [
                    ...$option->toArray(),
                    'values' => $option->values->toArray(),
                ])
                ->toArray()
        );

        return (new static())->newCollection(
            array_map(function (array $item) {
                $values = $item['values'] ?? [];
                unset($item['values']);

                $option = (new static())->newFromBuilder($item);
                $option->setRelation('values', (new YachtOptionValue())->newCollection(
                    array_map(fn (array $v) => (new YachtOptionValue())->newFromBuilder($v), $values),
                ));

                return $option;
            }, $data),
        );
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function values(): HasMany
    {
        return $this->hasMany(YachtOptionValue::class);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function isUsed(): bool
    {
        return YachtOptionSelection::where('yacht_option_id', $this->id)->exists();
    }

    public function usageCount(): int
    {
        return YachtOptionSelection::where('yacht_option_id', $this->id)->count();
    }
}
