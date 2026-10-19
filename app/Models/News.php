<?php

namespace App\Models;

use App\Enums\NewsPublicationStatus;
use App\Models\Concerns\NormalizesHeicImageColumns;
use App\Models\Concerns\RegistersResponsiveFormats;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class News extends Model implements HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia, NormalizesHeicImageColumns, RegistersResponsiveFormats, SoftDeletes;

    /** @var array<string> Строковые колонки-пути, где heic нормализуется в webp. */
    protected array $heicImageColumns = ['cover_image_url'];

    protected $fillable = [
        'author_id',
        'type',
        'title',
        'content',
        'external_url',
        'source_name',
        'source_published_at',
        'cover_image_url',
        'cover_object_position',
        'published_to_tg',
        'published_to_vk',
        'notified_users',
        'published_at',
        'unpublished_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (News $news) {
            // Значение по умолчанию БД не попадает обратно в экземпляр Eloquent
            // до refresh(), а observer должен видеть тип сразу после создания.
            if ($news->type === null) {
                $news->type = 'manual';
            }

            if ($news->published_at === null) {
                $news->published_at = now();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'published_to_tg' => 'boolean',
            'published_to_vk' => 'boolean',
            'notified_users' => 'boolean',
            'source_published_at' => 'datetime',
            'published_at' => 'datetime',
            'unpublished_at' => 'datetime',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereNull('unpublished_at');
    }

    public function scopeWithPublicationStatus(Builder $query, NewsPublicationStatus $status): Builder
    {
        return match ($status) {
            NewsPublicationStatus::Published => $query->published(),
            NewsPublicationStatus::Unpublished => $query->whereNotNull('unpublished_at'),
            NewsPublicationStatus::Scheduled => $query->whereNull('unpublished_at')
                ->where(fn (Builder $query): Builder => $query
                    ->whereNull('published_at')
                    ->orWhere('published_at', '>', now())),
        };
    }

    public function scopeManual(Builder $query): Builder
    {
        return $query->where('type', 'manual');
    }

    public function scopeExternal(Builder $query): Builder
    {
        return $query->where('type', 'external');
    }

    // ──────────────────────────────────────────────
    // Media Library
    // ──────────────────────────────────────────────

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gallery')
            ->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addResponsiveFormatConversions();
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function isPublished(): bool
    {
        return $this->published_at !== null
            && $this->published_at->isPast()
            && ! $this->isUnpublished();
    }

    /** Снята с публикации редактором (см. UnpublishNewsAction). */
    public function isUnpublished(): bool
    {
        return $this->unpublished_at !== null;
    }

    public function publicationStatus(): NewsPublicationStatus
    {
        return match (true) {
            $this->isUnpublished() => NewsPublicationStatus::Unpublished,
            $this->isScheduled() => NewsPublicationStatus::Scheduled,
            default => NewsPublicationStatus::Published,
        };
    }

    /** Дата публикации ещё не наступила, и новость не снята. */
    public function isScheduled(): bool
    {
        return ! $this->isUnpublished()
            && ($this->published_at === null || $this->published_at->isFuture());
    }

    public function publish(): void
    {
        $this->update(['published_at' => now()]);
    }

    public function pruningScope(): Builder
    {
        // Удаляем записи, которые были "мягко удалены" более 7 дней назад
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(7));
    }
}
