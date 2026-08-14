<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CharterPriceUnit;
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
        'model',
        'description',
        'year',
        'cabins',
        'downwind_sail',
        'price',
        'price_unit',
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

    /** Наследуют ли лодки дивизиона его характеристики. */
    public function sharesSpec(): bool
    {
        return $this->type->sharesSpec();
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
