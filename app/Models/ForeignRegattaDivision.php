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
 * Тип определяет две вещи (@see FleetDivisionType): общая ли у лодок
 * спецификация — модель, описание, галерея — и общая ли цена. У монотипа без
 * выбора общее и то и другое, у монотипа с выбором — только цена, у гандикапа
 * ничего: там всё своё у каждой лодки. Наследование делает
 * ForeignRegattaYacht::spec().
 *
 * Количество лодок монотипа без выбора задаётся полем `yachts_count`, а строки
 * под них заводит App\Actions\Service\SyncFleetDivisionYachts: шкипер и
 * свободные места — свойство конкретной лодки, поэтому виртуальными карточками
 * не обойтись. В остальных дивизионах лодки добавляются поимённо.
 */
class ForeignRegattaDivision extends Model implements HasMedia
{
    use HasCaptionedGallery, HasUuids, InteractsWithMedia, RegistersResponsiveFormats, SoftDeletes;

    protected $fillable = [
        'foreign_regatta_id',
        'type',
        'name',
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
        'type' => FleetDivisionType::Monotype->value,
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

    /** Общая ли цена у лодок дивизиона. */
    public function sharesPrices(): bool
    {
        return $this->type->sharesPrices();
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
        if (! $this->sharesPrices()) {
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

        $model = trim((string) $this->model);

        return $model !== '' ? $model : 'Дивизион';
    }

    /** «8 яхт Bavaria 46» — подпись дивизиона в списке и в админке. */
    public function summaryLabel(): ?string
    {
        if (! $this->sharesSpec()) {
            return null;
        }

        $count = (int) ($this->yachts_count ?? 0);
        $model = trim((string) $this->model);

        if ($count < 1) {
            return $model === '' ? null : $model;
        }

        $label = Plural::with($count, 'яхта', 'яхты', 'яхт');

        return $model === '' ? $label : $label.' '.$model;
    }
}
