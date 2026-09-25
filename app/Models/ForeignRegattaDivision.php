<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CharterPriceUnit;
use App\Enums\Currency;
use App\Enums\DownwindSail;
use App\Enums\FleetDivisionType;
use App\Models\Concerns\HasCaptionedGallery;
use App\Models\Concerns\RegistersResponsiveFormats;
use App\Support\Plural;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Дивизион флота зарубежной регаты.
 *
 * Тип определяет, где живут характеристики лодок: у дивизиона-флота
 * (@see FleetDivisionType::Fleet) они общие и лежат здесь, у списка конкретных
 * лодок — у каждой свои. Наследование делает ForeignRegattaYacht::spec().
 *
 * Количество лодок дивизиона-флота задаётся полем `yachts_count`, а строки под
 * них заводит App\Actions\Service\SyncFleetDivisionYachts: шкипер и свободные
 * места — свойство конкретной лодки, поэтому виртуальными карточками не обойтись.
 */
class ForeignRegattaDivision extends Model implements HasMedia
{
    use HasCaptionedGallery, HasUuids, InteractsWithMedia, RegistersResponsiveFormats, SoftDeletes;

    protected $fillable = [
        'foreign_regatta_id',
        'type',
        'name',
        'yacht_model_id',
        'model',
        'description',
        'year',
        'cabins',
        'downwind_sail',
        'price',
        'price_unit',
        'seat_price',
        'cabin_price',
        'charter_fee',
        'deposit',
        'price_note',
        'yachts_count',
        'sort_order',
    ];

    protected $attributes = [
        'type' => FleetDivisionType::Fleet->value,
    ];

    protected function casts(): array
    {
        return [
            'type' => FleetDivisionType::class,
            'year' => 'integer',
            'cabins' => 'integer',
            'downwind_sail' => DownwindSail::class,
            'price' => 'integer',
            'price_unit' => CharterPriceUnit::class,
            'seat_price' => 'integer',
            'cabin_price' => 'integer',
            'charter_fee' => 'integer',
            'deposit' => 'integer',
            'yachts_count' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    // ──────────────────────────────────────────────
    // Media
    // ──────────────────────────────────────────────

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gallery')->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addResponsiveFormatConversions();
    }

    // ──────────────────────────────────────────────
    // Связи
    // ──────────────────────────────────────────────

    public function regatta(): BelongsTo
    {
        return $this->belongsTo(ForeignRegatta::class, 'foreign_regatta_id');
    }

    public function yachts(): HasMany
    {
        return $this->hasMany(ForeignRegattaYacht::class, 'division_id')->ordered();
    }

    /**
     * Модель из справочника — для монотипного дивизиона.
     *
     * По ТЗ в таком дивизионе «вводятся только модели яхт с описанием и
     * галереей»: характеристики берутся отсюда, а на самом дивизионе остаются
     * цены и количество лодок.
     */
    public function yachtModel(): BelongsTo
    {
        return $this->belongsTo(CharterYachtModel::class, 'yacht_model_id');
    }

    // ──────────────────────────────────────────────
    // Скоупы
    // ──────────────────────────────────────────────

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // ──────────────────────────────────────────────
    // Представление
    // ──────────────────────────────────────────────

    /** Валюта цен: одна на всю регату, у дивизиона своей нет. */
    public function priceCurrency(): Currency
    {
        return Currency::fromNullable($this->regatta?->currency);
    }

    /** Наследуют ли лодки дивизиона его характеристики. */
    public function sharesSpec(): bool
    {
        return $this->type->sharesSpec();
    }

    /**
     * Характеристики дивизиона: свои, а чего нет — из модели справочника.
     *
     * Своё поле остаётся приоритетом ради дивизионов, заведённых до появления
     * справочника: у них модель записана строкой.
     */
    public function effectiveModel(): ?string
    {
        return $this->firstFilled($this->model, $this->yachtModel?->name);
    }

    public function effectiveDescription(): ?string
    {
        return $this->firstFilled($this->description, $this->yachtModel?->description);
    }

    public function effectiveCabins(): ?int
    {
        return $this->cabins ?? $this->yachtModel?->cabins;
    }

    public function effectiveDownwindSail(): ?DownwindSail
    {
        return $this->downwind_sail ?? $this->yachtModel?->downwind_sail;
    }

    /**
     * Фотографии дивизиона, а если своих нет — фотографии модели.
     *
     * @return list<array{src: string, webp: string|null, avif: string|null, caption: string}>
     */
    public function effectivePhotos(): array
    {
        $own = $this->galleryPhotos();

        return $own !== [] ? $own : ($this->yachtModel?->galleryPhotos() ?? []);
    }

    private function firstFilled(?string $own, ?string $fallback): ?string
    {
        $own = trim((string) $own);

        return $own !== '' ? $own : $fallback;
    }

    /**
     * Цены дивизиона для блока над списком лодок: «за что» => «сколько».
     *
     * У дивизиона-списка цен нет — там они свои у каждой лодки, и блок над
     * списком остаётся без строки стоимости.
     *
     * @return array<string, string>
     */
    public function priceLabels(): array
    {
        if (! $this->sharesSpec()) {
            return [];
        }

        $unit = $this->price_unit?->label();

        return array_filter([
            'Яхта целиком' => $this->price === null
                ? null
                : $this->formatPrice($this->price).($unit === null ? '' : ' '.$unit),
            'Место в двухместной каюте' => $this->seat_price === null
                ? null
                : $this->formatPrice($this->seat_price),
            'Двухместная каюта' => $this->cabin_price === null
                ? null
                : $this->formatPrice($this->cabin_price),
            'Сборы чартерной компании' => $this->charter_fee === null
                ? null
                : $this->formatPrice($this->charter_fee),
            'Депозит' => $this->deposit === null
                ? null
                : $this->formatPrice($this->deposit),
        ], fn (?string $value): bool => $value !== null);
    }

    private function formatPrice(int $value): string
    {
        return $this->priceCurrency()->format($value);
    }

    /** Заголовок секции на витрине: название, а без него — модель лодок. */
    public function title(): string
    {
        $name = trim((string) $this->name);

        if ($name !== '') {
            return $name;
        }

        $model = trim((string) $this->effectiveModel());

        return $model !== '' ? $model : 'Дивизион';
    }

    /** «8 яхт Bavaria 46» — подпись дивизиона в списке и в админке. */
    public function summaryLabel(): ?string
    {
        if (! $this->sharesSpec()) {
            return null;
        }

        $count = (int) ($this->yachts_count ?? 0);
        $model = trim((string) $this->effectiveModel());

        if ($count < 1) {
            return $model === '' ? null : $model;
        }

        $label = Plural::with($count, 'яхта', 'яхты', 'яхт');

        return $model === '' ? $label : $label.' '.$model;
    }
}
