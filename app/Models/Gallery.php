<?php

namespace App\Models;

use App\Models\Concerns\RegistersResponsiveFormats;
use App\Support\ResponsiveMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

/**
 * @property-read string|null $cover_path  // ★ аксессор — URL обложки из коллекции 'cover'
 * @property-read array       $images      // ★ аксессор — массив URL изображений из коллекции 'images'
 * @property-read array       $videos      // ★ аксессор — массив URL видео из коллекции 'videos'
 */
class Gallery extends Model implements HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia, Prunable, RegistersResponsiveFormats, SoftDeletes;

    // ──────────────────────────────────────────────
    // Table & Fillable
    // ИЗМЕНЕНИЕ: cover_path и images исключены из $fillable,
    //            т.к. теперь они управляются через Spatie Media Library.
    // ──────────────────────────────────────────────

    protected $table = 'gallery';

    protected $fillable = [
        'season_id',
        'regatta_id',
        'name',
        'water_area',
        'date',
        // ↓↓↓ УДАЛЕНО: 'cover_path' — заменено коллекцией 'cover' (см. registerMediaCollections)
        // ↓↓↓ УДАЛЕНО: 'images'     — заменено коллекцией 'images' (см. registerMediaCollections)
        'is_published',
        'sort_order',
    ];

    protected $attributes = [
        'is_published' => true,
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            // ↓↓↓ УДАЛЕНО: 'images' => 'array' — больше не хранится в таблице
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function regatta(): BelongsTo
    {
        return $this->belongsTo(Regatta::class);
    }

    public function videoLinks(): HasMany
    {
        return $this->hasMany(VideoLink::class)->orderBy('sort_order');
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
        return $query->orderBy('sort_order')->orderByDesc('date');
    }

    // ══════════════════════════════════════════════
    // Spatie Media Library Integration
    // ══════════════════════════════════════════════

    /**
     * Регистрирует именованные медиа-коллекции.
     *
     * ИЗМЕНЕНИЕ: полностью заменяет старые поля cover_path и images.
     *
     * Коллекции:
     *   'cover'  — одна обложка галереи (singleFile).
     *   'images' — все изображения галереи, до 200 файлов.
     */
    public function registerMediaCollections(): void
    {
        // ─── Коллекция «cover» — обложка галереи ───
        $this->addMediaCollection('cover')
            ->singleFile()                                         // только один файл
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->useDisk('public');                                   // соответствует старому ->disk('public')

        // ─── Коллекция «images» — фотографии галереи ───
        $this->addMediaCollection('images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->useDisk('public');                                   // соответствует старому ->disk('public')
        // Spatie v11 не имеет встроенного ->maxFiles(); ограничение реализуется
        // на уровне Filament-формы через ->maxFiles(200), как и было ранее.

        // ─── Коллекция «videos» — видеофайлы галереи ───
        // ★ ДОБАВЛЕНО: отдельная коллекция для видео. В старой схеме видео отсутствовали.
        // В публичной части галереи уже есть табы «Видео»/«Фотографии» (см. gallery.blade.php),
        // но раньше оба таба показывали одни и те же images. Теперь видео — отдельная коллекция.
        $this->addMediaCollection('videos')
            ->acceptsMimeTypes([
                'video/mp4',
                'video/webm',
                'video/ogg',
                'video/quicktime',         // .mov
                'video/x-msvideo',         // .avi
            ])
            ->useDisk('public');
    }

    /**
     * Регистрирует конверсии (производные размеры) изображений.
     *
     * ИЗМЕНЕНИЕ: новые возможности Spatie — автоматическая генерация миниатюр.
     * Раньше никакие конверсии не создавались.
     *
     * Конверсии:
     *   'thumb'      — 150×150, для админ-таблиц и превью-полосок.
     *   'preview'    — 400×300, для карточек галереи на публичной странице.
     *   'gallery_lg' — 1200×800, для lightbox / полноэкранного просмотра.
     */
    public function registerMediaConversions(?SpatieMedia $media = null): void
    {
        // ИЗМЕНЕНИЕ: конверсии переведены в очередь (->queued()).
        // Сохранение галереи теперь не блокируется генерацией миниатюр —
        // тяжёлая обработка изображений выполняется фоновым queue worker'ом
        // (QUEUE_CONNECTION=redis). ⚠️ Требует постоянно работающего воркера:
        // `php artisan queue:work redis` (на проде — через Supervisor/systemd),
        // иначе превью не сгенерируются.
        $this->addMediaConversion('thumb')
            ->width(150)
            ->height(150)
            ->sharpen(5)
            ->queued();

        $this->addMediaConversion('preview')
            ->width(400)
            ->height(300)
            ->quality(85)
            ->queued();

        $this->addMediaConversion('gallery_lg')
            ->width(1200)
            ->height(800)
            ->quality(92)
            ->queued();

        // Полноразмерные webp/avif для отдачи через <picture> (грид фото + обложка).
        $this->addResponsiveFormatConversions();
    }

    /**
     * Обложка галереи как Media-объект (для <x-responsive-picture>).
     */
    public function coverMedia(): ?SpatieMedia
    {
        return $this->getFirstMedia('cover');
    }

    /**
     * Изображения галереи в виде наборов URL-ов для <picture>.
     *
     * @return array<int, array{src: string, webp?: string, avif?: string}>
     */
    public function imagesResponsive(): array
    {
        return $this->getMedia('images')
            ->map(fn (SpatieMedia $media) => ResponsiveMedia::urls($media))
            ->values()
            ->toArray();
    }

    // ══════════════════════════════════════════════
    // Backward-compatible Accessors
    // ══════════════════════════════════════════════

    /**
     * Аксессор cover_path — эмулирует старое поле.
     *
     * ИЗМЕНЕНИЕ: раньше возвращал строку-путь (например "gallery/covers/xxx.jpg"),
     * которую затем оборачивали в Storage::disk('public')->url().
     * Теперь возвращает **готовый URL** из коллекции 'cover' Spatie.
     *
     * ⚠️ Код, который ранее делал Storage::disk('public')->url($gallery->cover_path),
     *   должен быть обновлён до простого $gallery->cover_path (URL уже полный).
     *
     * @return string|null Полный URL обложки или null, если обложка не задана.
     */
    public function getCoverPathAttribute(): ?string
    {
        $media = $this->getFirstMedia('cover');

        return $media?->getUrl();
    }

    /**
     * Аксессор images — эмулирует старое поле.
     *
     * ИЗМЕНЕНИЕ: раньше возвращал массив строк-путей, которые оборачивали
     * в Storage::disk('public')->url(). Теперь возвращает массив **готовых URL**
     * из коллекции 'images' Spatie.
     *
     * ⚠️ Код, который ранее делал collect($gallery->images)->map(fn($i) => Storage::disk('public')->url($i)),
     *   должен быть обновлён до простого $gallery->images (URL уже полные).
     *
     * @return array Массив полных URL изображений галереи.
     */
    public function getImagesAttribute(): array
    {
        return $this->getMedia('images')
            ->map(fn (SpatieMedia $media) => $media->getUrl())
            ->values()
            ->toArray();
    }

    /**
     * Аксессор videos — массив URL видеофайлов из коллекции 'videos'.
     *
     * ★ ДОБАВЛЕНО: отдельное поле для видео. Раньше видео отсутствовали на уровне БД,
     *   хотя в blade-шаблоне уже были табы «Видео»/«Фотографии» (оба показывали images).
     *   Теперь таб «Видео» должен использовать $gallery->videos.
     *
     * @return array Массив полных URL видеофайлов галереи.
     */
    public function getVideosAttribute(): array
    {
        return $this->getMedia('videos')
            ->map(fn (SpatieMedia $media) => $media->getUrl())
            ->values()
            ->toArray();
    }

    public function pruningScope(): Builder
    {
        // Удаляем записи, которые были "мягко удалены" более 7 дней назад
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(7));
    }

    /**
     * Брошенные черновики галерей.
     *
     * При нажатии «Новая галерея» сразу создаётся черновик (name = '',
     * is_published = false), чтобы загруженные фото сохранялись мгновенно.
     * Если админ закрыл модалку, не указав название, черновик остаётся
     * «висеть». Такие записи старше суток удаляются плановым `model:prune`
     * (см. routes/console.php). Spatie сам удалит связанные медиафайлы при
     * forceDelete (модель soft-deletable → Prunable вызывает forceDelete).
     */
    public function prunable(): Builder
    {
        return static::query()
            ->where('name', '')
            ->where('is_published', false)
            ->where('created_at', '<=', now()->subDay());
    }
}
