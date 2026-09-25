<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DownwindSail;
use App\Models\Concerns\HasCaptionedGallery;
use App\Models\Concerns\RegistersResponsiveFormats;
use App\Support\Plural;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Модель чартерной яхты из общего справочника.
 *
 * Описание, фотографии и характеристики лодки не зависят от регаты, поэтому
 * живут здесь и переиспользуются: каждая лодка флота ссылается на свою модель
 * (@see ForeignRegattaYacht::spec()). Монотипный дивизион без выбора яхт
 * справочником не пользуется — там модель вводится строкой.
 *
 * Чего здесь нет намеренно:
 *  - года и названия — они у каждой лодки свои, даже в монотипном флоте;
 *  - цен — они зависят от регаты и сезона и остаются у дивизиона и лодки.
 *
 * Страна базирования проставляется от регаты, где модель завели впервые
 * (@see App\Actions\Service\AssignCharterYachtModelCountry), и дальше правится
 * руками: одна и та же модель может стоять в нескольких странах, но справочник
 * подсказывает ту, где её уже брали.
 */
class CharterYachtModel extends Model implements HasMedia
{
    use HasCaptionedGallery, HasUuids, InteractsWithMedia, RegistersResponsiveFormats, SoftDeletes;

    protected $fillable = [
        'name',
        'country',
        'cabins',
        'downwind_sail',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'cabins' => 'integer',
            'downwind_sail' => DownwindSail::class,
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

    /** Конкретные лодки этой модели во флотах регат. */
    public function yachts(): HasMany
    {
        return $this->hasMany(ForeignRegattaYacht::class, 'yacht_model_id');
    }

    // ──────────────────────────────────────────────
    // Скоупы
    // ──────────────────────────────────────────────

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('name');
    }

    /**
     * Модели, которые уже брали в этой стране, — и те, у кого страна не указана.
     *
     * Страна фильтрует, но не прячет: лодку можно перегнать, а справочник
     * общий, поэтому выбор из другой страны остаётся возможным.
     */
    public function scopeOfCountry(Builder $query, ?string $country): Builder
    {
        $country = trim((string) $country);

        if ($country === '') {
            return $query;
        }

        return $query->where(fn (Builder $inner) => $inner
            ->where('country', $country)
            ->orWhereNull('country'));
    }

    // ──────────────────────────────────────────────
    // Представление
    // ──────────────────────────────────────────────

    /** «Bavaria 46 (Хорватия)» — подпись для выпадающих списков и таблиц. */
    public function label(): string
    {
        $name = trim((string) $this->name);
        $country = trim((string) $this->country);

        return $country === '' ? $name : $name.' ('.$country.')';
    }

    public function cabinsLabel(): ?string
    {
        return $this->cabins === null ? null : Plural::with($this->cabins, 'каюта', 'каюты', 'кают');
    }
}
