<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RegistersResponsiveFormats;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Публикация стороннего издания об ассоциации или соревнованиях Carter 30
 * (раздел «Пресса о нас», ТЗ 3-го этапа, п. 9).
 *
 * Материал не наш, поэтому ссылка на оригинал (`source_url`) обязательна и
 * выводится на сайте вместе с перепечаткой текста (`content`).
 */
class PressMention extends Model implements HasMedia
{
    use HasUuids, InteractsWithMedia, RegistersResponsiveFormats, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'source_name',
        'source_url',
        'published_at',
        'summary',
        'content',
        'is_published',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'date',
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // ──────────────────────────────────────────────
    // Media
    // ──────────────────────────────────────────────

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')
            ->useDisk('public')
            ->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addResponsiveFormatConversions();
    }

    // ──────────────────────────────────────────────
    // Скоупы
    // ──────────────────────────────────────────────

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Свежие публикации сверху.
     *
     * Ручная сортировка (`sort_order`) идёт первой: ей закрепляют наверху
     * материал, который важнее хронологии. Даты выхода может не быть — такие
     * записи уходят в конец, а не в начало списка.
     */
    public function scopeRecentFirst(Builder $query): Builder
    {
        return $query->orderBy('sort_order')
            ->orderByRaw('published_at IS NULL')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at');
    }

    // ──────────────────────────────────────────────
    // Вывод на сайте
    // ──────────────────────────────────────────────

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function publicUrl(): string
    {
        return route('press-details', $this);
    }

    /** Домен издания — подпись для кнопки «Читать в оригинале». */
    public function sourceHost(): ?string
    {
        $host = parse_url($this->source_url, PHP_URL_HOST);

        return is_string($host) ? preg_replace('/^www\./', '', $host) : null;
    }

    /** Есть ли перепечатка текста: пустой RichEditor отдаёт «<p></p>», а не null. */
    public function hasContent(): bool
    {
        return trim(strip_tags((string) $this->content, '<img>')) !== '';
    }
}
