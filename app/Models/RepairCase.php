<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RegistersResponsiveFormats;
use App\Models\Scopes\OwnedScope;
use App\Support\ResponsiveMedia;
use App\Support\VideoEmbed;
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
 * Подписи к фотографиям хранятся в колонке `name` модели медиа: это «человеческое»
 * имя файла, отдельное от `file_name`, и его умеет редактировать репитер поверх
 * связи galleryMedia() — своей таблицы подписей заводить не пришлось.
 */
class RepairCase extends Model implements HasMedia
{
    use HasUuids, InteractsWithMedia, RegistersResponsiveFormats, SoftDeletes;

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

    /**
     * Фотографии с подписями и адаптивными форматами.
     *
     * @return list<array{src: string, webp: string|null, avif: string|null, caption: string}>
     */
    public function galleryPhotos(): array
    {
        return $this->getMedia('gallery')
            ->map(function (Media $media): array {
                $urls = ResponsiveMedia::urls($media);

                return [
                    'src' => $urls['src'],
                    'webp' => $urls['webp'] ?? null,
                    'avif' => $urls['avif'] ?? null,
                    // Подпись — имя медиа; если админ его не менял, там лежит
                    // имя исходного файла, поэтому пустую подпись не выдумываем.
                    'caption' => $this->captionFor($media),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Видео с подписями, готовые к вставке в iframe.
     *
     * @return list<array{embed_url: string, url: string, caption: string}>
     */
    public function videos(): array
    {
        return collect($this->video_links ?? [])
            ->filter(fn ($link) => is_array($link) && ! empty($link['url']))
            ->map(fn (array $link): array => [
                'url' => (string) $link['url'],
                'embed_url' => VideoEmbed::url((string) $link['url']),
                'caption' => (string) ($link['caption'] ?? ''),
            ])
            ->values()
            ->all();
    }

    /** Подпись под фото: пустая, если админ оставил исходное имя файла. */
    private function captionFor(Media $media): string
    {
        $name = trim((string) $media->name);
        $originalName = pathinfo((string) $media->file_name, PATHINFO_FILENAME);

        return $name === '' || $name === $originalName ? '' : $name;
    }
}
