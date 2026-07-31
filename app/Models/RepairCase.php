<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCaptionedGallery;
use App\Models\Concerns\RegistersResponsiveFormats;
use App\Models\Scopes\OwnedScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Кейс ремонта или модернизации яхты (раздел «Carter 30»).
 *
 * Галерея с подписями и видео — в трейте HasCaptionedGallery (общий с Tour).
 */
class RepairCase extends Model implements HasMedia
{
    use HasCaptionedGallery, HasUuids, InteractsWithMedia, RegistersResponsiveFormats, SoftDeletes;

    protected $fillable = [
        'yacht_id',
        'title',
        'slug',
        'summary',
        'content',
        'video_links',
        'is_published',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'video_links' => 'array',
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

        $this->addMediaCollection('gallery')
            ->useDisk('public');

        // Чертежи и документы: не только изображения, поэтому конверсии
        // Spatie сгенерирует лишь для тех файлов, которые сможет открыть.
        $this->addMediaCollection('drawings')
            ->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addResponsiveFormatConversions();
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    /**
     * Яхта кейса.
     *
     * OwnedScope снимаем: он прячет яхты без владельца (импорт из реестра),
     * а кейс ремонта должен показывать привязанную яхту в любом случае.
     */
    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class)->withoutGlobalScope(OwnedScope::class);
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }

    // ──────────────────────────────────────────────
    // Вывод на сайте
    // ──────────────────────────────────────────────

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
