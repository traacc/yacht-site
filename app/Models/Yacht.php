<?php

namespace App\Models;

use App\Enums\RentalRequestStatus;
use App\Models\Concerns\RegistersResponsiveFormats;
use App\Models\Scopes\OwnedScope;
use App\Support\ResponsiveMedia;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[ScopedBy([OwnedScope::class])]
class Yacht extends Model implements HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia, RegistersResponsiveFormats, SoftDeletes;

    /** doc_type документа с ORC-сертификатом яхты */
    public const ORC_DOC_TYPE = 'orc_certificate';

    /** Заголовок документа с ORC-сертификатом (виден в карточке яхты) */
    public const ORC_DOC_TITLE = 'ORC-сертификат';

    /**
     * Коллекции фотографий яхты в порядке показа в галерее:
     * обложка → экстерьер → интерьер.
     */
    public const PHOTO_COLLECTIONS = [
        'cover' => 'Обложка',
        'gallery' => 'Экстерьер',
        'interior_gallery' => 'Интерьер',
    ];

    protected $fillable = [
        'name',
        'vfps_number',
        'user_id',
        'gims_number',
        'orc_cert_url',
        'class',
        'project',
        'year',
        'reg_place',
        'home_region',
        'mooring_place',
        'sail_type',
        'current_mass_kg',
        'for_rent',
        'approval_status',

        'owner_name',
        'owner_email',
        'owner_phone',
        'owner_photo',

        'past_regattas',
        'suitable_for',
    ];

    protected function casts(): array
    {
        return [
            'current_mass_kg' => 'decimal:2',
            'past_regattas' => 'array',
            'suitable_for' => 'array',
            'for_rent' => 'boolean',
        ];
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    /**
     * Яхты, свободные в указанный период (не имеют одобренных заявок на регаты,
     * пересекающиеся с этим периодом).
     */
    public function scopeFreeDuring($query, string $dateStart, string $dateEnd)
    {
        return $query->whereDoesntHave('regattaEntries', function ($q) use ($dateStart, $dateEnd) {
            $q->where('status', 'approved')
                ->whereHas('regatta', function ($regattaQuery) use ($dateStart, $dateEnd) {
                    $regattaQuery->where('date_start', '<=', $dateEnd)
                        ->where('date_end', '>=', $dateStart);
                });
        });
    }

    /**
     * Яхты, которые можно арендовать на весь диапазон дат.
     *
     * Три условия сразу: владелец объявил аренду на период, полностью
     * покрывающий запрос; нет одобренной брони, пересекающейся с диапазоном
     * (заявка без desired_date_end — бронь на один день); яхта не занята
     * регатой. Скоуп общий для витрины бронирования и подбора флота, иначе
     * страницы разошлись бы в ответе «свободна / занята».
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAvailableForRent(Builder $query, string $dateStart, string $dateEnd): Builder
    {
        return $query
            ->whereHas('rentals', fn (Builder $rentals) => $rentals
                ->whereDate('date_start', '<=', $dateStart)
                ->whereDate('date_end', '>=', $dateEnd))
            ->whereDoesntHave('rentalRequests', fn (Builder $requests) => $requests
                ->where('status', RentalRequestStatus::Approved->value)
                ->whereNotNull('desired_date')
                ->whereDate('desired_date', '<=', $dateEnd)
                ->where(fn (Builder $inner) => $inner
                    ->whereDate('desired_date_end', '>=', $dateStart)
                    ->orWhere(fn (Builder $single) => $single
                        ->whereNull('desired_date_end')
                        ->whereDate('desired_date', '>=', $dateStart))))
            // Регаты живут отдельно от аренды, поэтому проверяются дополнительно.
            ->freeDuring($dateStart, $dateEnd);
    }

    public function pruningScope(): Builder
    {
        // Удаляем записи, которые были "мягко удалены" более 7 дней назад
        return static::onlyTrashed()->where('deleted_at', '<=', now());
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function regattaEntries(): HasMany
    {
        return $this->hasMany(RegattaEntry::class);
    }

    /** Предложения аренды: регата + стоимость аренды на неё */
    public function rentals(): HasMany
    {
        return $this->hasMany(YachtRental::class);
    }

    /** Запросы на аренду яхты от пользователей */
    public function rentalRequests(): HasMany
    {
        return $this->hasMany(YachtRentalRequest::class);
    }

    /** Документы (ORC-сертификаты, технические паспорта) */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Выбранные значения опций яхты (например «Дакрон» для опции «Тип паруса»).
     * Список опций и их значений задаёт администратор; на яхте выбирается
     * не более одного значения на каждую опцию.
     */
    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(YachtOptionValue::class, 'yacht_option_selections')
            ->withPivot('yacht_option_id');
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function registerMediaCollections(): void
    {
        // Обложка — первое фото яхты: аватарка в списке и первый кадр галереи.
        $this->addMediaCollection('cover')
            ->useDisk('public')
            ->singleFile();

        $this->addMediaCollection('gallery')
            ->useDisk('public');

        $this->addMediaCollection('interior_gallery')
            ->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->sharpen(10)
            ->nonQueued();

        $this->addResponsiveFormatConversions();
    }

    /**
     * Все фотографии яхты одним списком: сначала обложка, затем экстерьер,
     * затем интерьер. Один и тот же массив питает и аватарку в списке яхт,
     * и галерею в карточке, поэтому порядок задан здесь, а не в шаблоне.
     *
     * @return list<array{url: string, webp: string|null, avif: string|null, thumbnail: string, name: string, group: string, collection: string}>
     */
    public function photoGallery(): array
    {
        $photos = [];

        foreach (self::PHOTO_COLLECTIONS as $collection => $group) {
            foreach ($this->getMedia($collection) as $media) {
                $urls = ResponsiveMedia::urls($media);

                $photos[] = [
                    'url' => $urls['src'],
                    'webp' => $urls['webp'] ?? null,
                    'avif' => $urls['avif'] ?? null,
                    'thumbnail' => $media->hasGeneratedConversion('thumb')
                        ? $media->getUrl('thumb')
                        : $urls['src'],
                    'name' => $media->name,
                    'group' => $group,
                    'collection' => $collection,
                ];
            }
        }

        return $photos;
    }

    /**
     * Обложка для аватарки в списке: загруженная обложка, а если её нет —
     * первое фото любой галереи, чтобы строка не осталась без картинки.
     *
     * @return array{url: string, webp: string|null, avif: string|null, thumbnail: string, name: string, group: string, collection: string}|null
     */
    public function coverPhoto(): ?array
    {
        return $this->photoGallery()[0] ?? null;
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->approval_status === 'pending';
    }

    /**
     * Проверить занятость яхты в указанный период.
     * Учитываются только одобренные заявки.
     */
    public function isBusyDuring(string $dateStart, string $dateEnd): bool
    {
        return $this->regattaEntries()
            ->where('status', 'approved')
            ->whereHas('regatta', function ($q) use ($dateStart, $dateEnd) {
                $q->where('date_start', '<=', $dateEnd)
                    ->where('date_end', '>=', $dateStart);
            })
            ->exists();
    }
}
