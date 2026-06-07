<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentOwner;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Типы документов яхты.
 *
 * Каждый тип имеет уникальный строковый ключ (key), читаемое название (label),
 * описание (description) и флаг is_configurable — можно ли настроить обязательность.
 *
 * Кэширование: все типы кэшируются на 1 час. Инвалидация происходит
 * при сохранении/удалении через статические события модели.
 */
class YachtDocumentType extends Model
{
    use HasUuids;

    protected $table = 'yacht_document_types';

    protected $fillable = [
        'key',
        'label',
        'description',
        'is_configurable',
        'owner',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_configurable' => 'boolean',
            'owner'           => DocumentOwner::class,
            'sort_order'      => 'integer',
        ];
    }

    // ──────────────────────────────────────────────
    // Cache
    // ──────────────────────────────────────────────

    private const CACHE_KEY_ALL = 'yacht_document_types:all';
    private const CACHE_KEY_CONFIGURABLE = 'yacht_document_types:configurable';
    private const CACHE_TTL = 3600;

    private static function cacheKeyForOwner(DocumentOwner $owner): string
    {
        return "yacht_document_types:owner:{$owner->value}";
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY_ALL);
        Cache::forget(self::CACHE_KEY_CONFIGURABLE);
        foreach (DocumentOwner::cases() as $owner) {
            Cache::forget(self::cacheKeyForOwner($owner));
        }
    }

    // ──────────────────────────────────────────────
    // Query scopes
    // ──────────────────────────────────────────────

    /**
     * @return Collection<int, static>
     */
    public static function cachedAll(): Collection
    {
        $data = Cache::remember(self::CACHE_KEY_ALL, self::CACHE_TTL, fn () =>
            static::orderBy('sort_order')->orderBy('label')->get()->toArray()
        );

        return (new static())->newCollection(
            array_map(fn (array $item) => (new static())->newFromBuilder($item), $data),
        );
    }

    /**
     * Типы, для которых можно настроить обязательность.
     *
     * @return Collection<int, static>
     */
    public static function cachedConfigurable(): Collection
    {
        $data = Cache::remember(self::CACHE_KEY_CONFIGURABLE, self::CACHE_TTL, fn () =>
            static::where('is_configurable', true)
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get()
                ->toArray()
        );

        return (new static())->newCollection(
            array_map(fn (array $item) => (new static())->newFromBuilder($item), $data),
        );
    }

    /**
     * Типы, привязанные к конкретному владельцу (owner = $owner или owner IS NULL).
     *
     * @return Collection<int, static>
     */
    public static function cachedForOwner(DocumentOwner $owner): Collection
    {
        $data = Cache::remember(self::cacheKeyForOwner($owner), self::CACHE_TTL, fn () =>
            static::where(fn ($q) => $q->where('owner', $owner->value)->orWhereNull('owner'))
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get()
                ->toArray()
        );

        return (new static())->newCollection(
            array_map(fn (array $item) => (new static())->newFromBuilder($item), $data),
        );
    }

    /**
     * Настраиваемые типы для конкретного владельца (owner = $owner или owner IS NULL).
     *
     * @return Collection<int, static>
     */
    public static function cachedConfigurableForOwner(DocumentOwner $owner): Collection
    {
        return static::cachedForOwner($owner)->filter(fn (self $t) => $t->is_configurable)->values();
    }

    /**
     * Опции для Select-компонентов: key => label.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return static::cachedAll()
            ->pluck('label', 'key')
            ->toArray();
    }

    /**
     * Опции только для настраиваемых типов.
     *
     * @return array<string, string>
     */
    public static function configurableOptions(): array
    {
        return static::cachedConfigurable()
            ->pluck('label', 'key')
            ->toArray();
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function isUsedInDocuments(): bool
    {
        return Document::where('doc_type', $this->key)->exists();
    }

    public function usageCount(): int
    {
        return Document::where('doc_type', $this->key)->count();
    }
}