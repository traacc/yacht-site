<?php

declare(strict_types=1);

namespace App\Models;

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
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_configurable' => 'boolean',
            'sort_order'      => 'integer',
        ];
    }

    // ──────────────────────────────────────────────
    // Cache
    // ──────────────────────────────────────────────

    private const CACHE_KEY_ALL = 'yacht_document_types:all';
    private const CACHE_KEY_CONFIGURABLE = 'yacht_document_types:configurable';
    private const CACHE_TTL = 3600;

    protected static function booted(): void
    {
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY_ALL);
        Cache::forget(self::CACHE_KEY_CONFIGURABLE);
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