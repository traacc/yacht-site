<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CharterPriceUnit;
use App\Enums\Currency;
use App\Enums\DownwindSail;
use App\Enums\FleetDivisionType;
use App\Enums\ParticipationOption;
use App\Models\Concerns\CountsFleetOccupancy;
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
 * выбора общее и то и другое, у монотипа с выбором — только спецификация
 * (модель одна на дивизион, выбирается из справочника), у гандикапа ничего:
 * там всё своё у каждой лодки. Наследование делает
 * ForeignRegattaYacht::spec().
 *
 * Количество лодок монотипа без выбора задаётся полем `yachts_count`, а строки
 * под них заводит App\Actions\Service\SyncFleetDivisionYachts: шкипер и
 * свободные места — свойство конкретной лодки, поэтому виртуальными карточками
 * не обойтись. В остальных дивизионах лодки добавляются поимённо.
 */
class ForeignRegattaDivision extends Model implements HasMedia
{
    use CountsFleetOccupancy, HasCaptionedGallery, HasUuids, InteractsWithMedia, RegistersResponsiveFormats, SoftDeletes;

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
        'base_marina',
        'price',
        'price_unit',
        'seat_price',
        'solo_seat_price',
        'cabin_price',
        'charter_fee',
        'deposit',
        'downwind_sail_price',
        'downwind_sail_deposit',
        'price_note',
        'yachts_count',
        'yachts_taken',
        'seats_total',
        'seats_taken',
        'solo_seats_total',
        'solo_seats_taken',
        'cabins_total',
        'cabins_taken',
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
            'solo_seat_price' => 'integer',
            'cabin_price' => 'integer',
            'charter_fee' => 'integer',
            'deposit' => 'integer',
            'downwind_sail_price' => 'integer',
            'downwind_sail_deposit' => 'integer',
            'yachts_count' => 'integer',
            'yachts_taken' => 'integer',
            'seats_total' => 'integer',
            'seats_taken' => 'integer',
            'solo_seats_total' => 'integer',
            'solo_seats_taken' => 'integer',
            'cabins_total' => 'integer',
            'cabins_taken' => 'integer',
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

    /**
     * Модель из справочника — одна на весь дивизион.
     *
     * Так устроен монотип с выбором яхт: лодки называются поимённо и считаются
     * по отдельности, но модель у них общая (@see FleetDivisionType::MonotypeChoice).
     * Монотип без выбора обходится строкой `model`, гандикапу модель задаётся
     * у каждой лодки.
     */
    public function yachtModel(): BelongsTo
    {
        return $this->belongsTo(CharterYachtModel::class, 'yacht_model_id');
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
     * Продаёт ли дивизион места сам, а не через отдельные лодки.
     *
     * Так устроен монотип без выбора яхт: лодки в нём одинаковые и
     * взаимозаменяемые, поэтому и счётчик занятости, и кнопки на витрине — на
     * дивизионе, а заявка приходит на него же.
     */
    public function sellsDirectly(): bool
    {
        return $this->type->usesYachtsCount();
    }

    /** Цена варианта участия у дивизиона. */
    public function priceFor(ParticipationOption $option): ?int
    {
        return $this->getAttribute($option->priceColumn());
    }

    public function occupancyTotal(ParticipationOption $option): ?int
    {
        // «Всего яхт» отдельной колонкой не заводим: это и есть количество
        // лодок дивизиона.
        return $option === ParticipationOption::Yacht
            ? $this->yachts_count
            : $this->getAttribute($option->totalColumn());
    }

    /**
     * Варианты, которые дивизион предлагает сам.
     *
     * Нужны цена и свободные места: место без цены продать нельзя, а место без
     * остатка — уже нечего. Для дивизионов, где лодки выбираются поимённо,
     * список пуст — там предлагают сами лодки.
     *
     * @return list<ParticipationOption>
     */
    public function offeredParticipations(): array
    {
        if (! $this->sellsDirectly()) {
            return [];
        }

        return array_values(array_filter(
            ParticipationOption::cases(),
            fn (ParticipationOption $option): bool => $this->priceFor($option) !== null
                && $this->hasVacancy($option),
        ));
    }

    /** Подпись цены варианта — на кнопке заявки. */
    public function participationPriceLabel(ParticipationOption $option): ?string
    {
        $price = $this->priceFor($option);

        if ($price === null) {
            return null;
        }

        $label = $this->formatPrice($price);

        return $option === ParticipationOption::Yacht && $this->price_unit !== null
            ? $label.' '.$this->price_unit->label()
            : $label;
    }

    /**
     * Цены дивизиона для блока над списком лодок: «за что» => «сколько».
     *
     * У гандикапного дивизиона цен нет — там они свои у каждой лодки, и блок
     * над списком остаётся без строки стоимости.
     *
     * @return array<string, string>
     */
    public function priceLabels(): array
    {
        if (! $this->sharesPrices()) {
            return [];
        }

        $prices = collect(ParticipationOption::cases())
            ->mapWithKeys(fn (ParticipationOption $option): array => [
                $option->label() => $this->participationPriceLabel($option),
            ])
            ->all();

        return array_filter([
            ...$prices,
            'Сборы чартерной компании' => $this->charter_fee === null
                ? null
                : $this->formatPrice($this->charter_fee),
            'Депозит' => $this->deposit === null
                ? null
                : $this->formatPrice($this->deposit),
            'Аренда спинакера/геннакера' => $this->downwind_sail_price === null
                ? null
                : $this->formatPrice($this->downwind_sail_price),
            'Депозит за спинакер/геннакер' => $this->downwind_sail_deposit === null
                ? null
                : $this->formatPrice($this->downwind_sail_deposit),
        ], fn (?string $value): bool => $value !== null);
    }

    /**
     * Занятость по объявленным вариантам: «за что» => «занято 4 из 5…».
     *
     * @return array<string, string>
     */
    public function occupancyLabels(): array
    {
        if (! $this->sellsDirectly()) {
            return [];
        }

        return array_filter(
            collect(ParticipationOption::cases())
                ->mapWithKeys(fn (ParticipationOption $option): array => [
                    $option->label() => $this->occupancyLabel($option),
                ])
                ->all(),
            fn (?string $value): bool => $value !== null,
        );
    }

    private function formatPrice(int $value): string
    {
        return $this->priceCurrency()->format($value);
    }

    /** Заголовок секции на витрине: название, а без него — модель лодок. */
    /** Модель лодок дивизиона: своя строка, иначе из справочника. */
    public function effectiveModel(): ?string
    {
        $own = trim((string) $this->model);

        return $own !== '' ? $own : $this->yachtModel?->name;
    }

    public function effectiveDescription(): ?string
    {
        $own = trim((string) $this->description);

        return $own !== '' ? $own : $this->yachtModel?->description;
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

        // Количество ведётся только там, где лодки заводятся по числу;
        // в монотипе с выбором их столько, сколько завели поимённо.
        $count = (int) ($this->yachts_count ?? 0);
        $model = trim((string) $this->effectiveModel());

        if ($count < 1) {
            return $model === '' ? null : $model;
        }

        $label = Plural::with($count, 'яхта', 'яхты', 'яхт');

        return $model === '' ? $label : $label.' '.$model;
    }
}
