<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdvertKind;
use App\Enums\AdvertPosition;
use App\Enums\AdvertPriceUnit;
use App\Enums\AdvertStatus;
use App\Enums\AdvertType;
use App\Enums\SportCategory;
use App\Models\Concerns\RegistersResponsiveFormats;
use App\Models\Scopes\OwnedScope;
use App\Support\ResponsiveMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Объявление с премодерацией.
 *
 * Одна модель на все доски объявлений (@see AdvertType). Публикует только
 * модератор: пользователь подаёт объявление в статусе Pending, и туда же оно
 * возвращается после правки — иначе премодерация обходится тривиально
 * (опубликовать безобидное, отредактировать во что угодно).
 */
class Advert extends Model implements HasMedia
{
    use HasUuids, InteractsWithMedia, RegistersResponsiveFormats, SoftDeletes;

    /** Коллекция фотографий объявления. */
    public const PHOTOS = 'photos';

    protected $fillable = [
        'type',
        'kind',
        'status',
        'user_id',
        'advert_category_id',
        'yacht_id',
        'yacht_name',
        'title',
        'description',
        'details',
        'position',
        'sport_category',
        'date_from',
        'date_to',
        'price',
        'price_negotiable',
        'price_unit',
        'deposit',
        'city',
        'contact_phone',
        'contact_telegram',
        'contact_email',
        'rejection_reason',
        'moderated_at',
        'moderated_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => AdvertType::class,
            'kind' => AdvertKind::class,
            'status' => AdvertStatus::class,
            'position' => AdvertPosition::class,
            'sport_category' => SportCategory::class,
            'price' => 'integer',
            'price_negotiable' => 'boolean',
            'price_unit' => AdvertPriceUnit::class,
            'deposit' => 'integer',
            'date_from' => 'date',
            'date_to' => 'date',
            'moderated_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    // ──────────────────────────────────────────────
    // Media
    // ──────────────────────────────────────────────

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::PHOTOS)
            ->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addResponsiveFormatConversions();
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AdvertCategory::class, 'advert_category_id');
    }

    /**
     * Яхта объявления «Продать яхту».
     *
     * OwnedScope снимаем: он прячет яхты без владельца (импорт из реестра),
     * а объявление должно показывать привязанную яхту в любом случае.
     */
    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class)->withoutGlobalScope(OwnedScope::class);
    }

    public function moderatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    /** Регаты, на которые подано объявление («Яхты для соревнований»). */
    public function regattas(): BelongsToMany
    {
        return $this->belongsToMany(Regatta::class);
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    public function scopeOfType(Builder $query, AdvertType $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeOfKind(Builder $query, AdvertKind $kind): Builder
    {
        return $query->where('kind', $kind);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', AdvertStatus::Pending);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', AdvertStatus::Published);
    }

    /** Всё, что показывается на витрине: опубликованное и проданное (с плашкой). */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereIn('status', [AdvertStatus::Published, AdvertStatus::Sold]);
    }

    // ──────────────────────────────────────────────
    // Состояние
    // ──────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === AdvertStatus::Pending;
    }

    public function isPublished(): bool
    {
        return $this->status === AdvertStatus::Published;
    }

    public function isVisible(): bool
    {
        return $this->status->isVisible();
    }

    public function isSold(): bool
    {
        return $this->status === AdvertStatus::Sold;
    }

    public function approve(?User $moderator = null): void
    {
        $this->update([
            'status' => AdvertStatus::Published,
            'rejection_reason' => null,
            'moderated_at' => now(),
            'moderated_by' => $moderator?->getKey(),
            // Первую публикацию датируем один раз: повторная модерация после
            // правки не должна поднимать объявление в начало витрины.
            'published_at' => $this->published_at ?? now(),
        ]);
    }

    public function reject(?string $reason = null, ?User $moderator = null): void
    {
        $this->update([
            'status' => AdvertStatus::Rejected,
            'rejection_reason' => $reason,
            'moderated_at' => now(),
            'moderated_by' => $moderator?->getKey(),
        ]);
    }

    public function markSold(): void
    {
        $this->update(['status' => AdvertStatus::Sold]);
    }

    public function archive(): void
    {
        $this->update(['status' => AdvertStatus::Archived]);
    }

    /** Правка автором отправляет объявление на повторную модерацию. */
    public function sendToModeration(): void
    {
        $this->update([
            'status' => AdvertStatus::Pending,
            'rejection_reason' => null,
            'moderated_at' => null,
            'moderated_by' => null,
        ]);
    }

    // ──────────────────────────────────────────────
    // Вывод на сайте
    // ──────────────────────────────────────────────

    public function priceLabel(): string
    {
        if ($this->price === null) {
            return $this->price_negotiable ? 'Цена договорная' : 'Цена не указана';
        }

        $formatted = self::formatMoney($this->price).($this->price_unit?->suffix() ?? '');

        return $this->price_negotiable ? $formatted.' (договорная)' : $formatted;
    }

    /** Залог при аренде; null — залога нет. */
    public function depositLabel(): ?string
    {
        return $this->deposit === null ? null : self::formatMoney($this->deposit);
    }

    /** Подпись вида объявления под свою доску: «Продам или сдам», «Ищу парус»… */
    public function kindLabel(): ?string
    {
        return $this->kind === null ? null : $this->type->kindLabel($this->kind);
    }

    /** «Когда»: период объявления. */
    public function datesLabel(): ?string
    {
        if ($this->date_from === null && $this->date_to === null) {
            return null;
        }

        if ($this->date_from === null) {
            return 'по '.$this->date_to->translatedFormat('j F Y');
        }

        if ($this->date_to === null || $this->date_to->isSameDay($this->date_from)) {
            return $this->date_from->translatedFormat('j F Y');
        }

        return $this->date_from->translatedFormat('j F').' — '.$this->date_to->translatedFormat('j F Y');
    }

    /** Яхта объявления: из реестра или свободным текстом. */
    public function yachtLabel(): ?string
    {
        return $this->yacht?->name ?? ($this->yacht_name ?: null);
    }

    /**
     * Ссылка на карточку яхты.
     *
     * Отдельной публичной страницы у яхты нет: каталог `/yachts` открывает
     * карточку модалкой, поэтому ведём туда с параметром, который эту модалку
     * разворачивает. У яхты «свободным текстом» ссылки нет.
     */
    public function yachtUrl(): ?string
    {
        return $this->yacht_id === null ? null : url('/yachts').'?yacht='.$this->yacht_id;
    }

    private static function formatMoney(int $amount): string
    {
        return number_format((float) $amount, 0, ',', ' ').' ₽';
    }

    /** Есть ли хоть один контакт для публикации. */
    public function hasContacts(): bool
    {
        return filled($this->contact_phone)
            || filled($this->contact_telegram)
            || filled($this->contact_email);
    }

    /**
     * Фотографии с адаптивными форматами.
     *
     * @return list<array{src: string, webp: string|null, avif: string|null}>
     */
    public function photos(): array
    {
        return $this->getMedia(self::PHOTOS)
            ->map(function (Media $media): array {
                $urls = ResponsiveMedia::urls($media);

                return [
                    'src' => $urls['src'],
                    'webp' => $urls['webp'] ?? null,
                    'avif' => $urls['avif'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    public function firstPhoto(): ?Media
    {
        return $this->getMedia(self::PHOTOS)->first();
    }

    /** Ссылка на страницу объявления с учётом доски. */
    public function publicUrl(): string
    {
        return route($this->type->itemRouteName(), $this);
    }
}
