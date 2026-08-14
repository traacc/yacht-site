<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CharterPriceUnit;
use App\Enums\CharterYachtStatus;
use App\Enums\DownwindSail;
use App\Enums\ParticipationOption;
use App\Enums\ServiceType;
use App\Models\Concerns\HasCaptionedGallery;
use App\Models\Concerns\RegistersResponsiveFormats;
use App\Support\Plural;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Лодка зарубежной регаты (ТЗ 3-го этапа, п. 7).
 *
 * К реестру `yachts` отношения не имеет: лодка берётся в чартер за границей,
 * владельца и документов на сайте у неё нет.
 *
 * Характеристики берутся у дивизиона-флота, если своих нет (@see spec()): у
 * восьми одинаковых Bavaria 46 модель, каюты и цена вводятся один раз. Своими
 * у такой лодки остаются то, что у неё и правда своё, — шкипер, свободные
 * места и занятость.
 *
 * Что предлагается по лодке, определяет наличие шкипера: со шкипером она
 * набирает экипаж и продаёт места, без шкипера — сдаётся целиком.
 */
class ForeignRegattaYacht extends Model implements HasMedia
{
    use HasCaptionedGallery, HasUuids, InteractsWithMedia, RegistersResponsiveFormats, SoftDeletes;

    protected $fillable = [
        'foreign_regatta_id',
        'division_id',
        'model',
        'name',
        'year',
        'description',
        'cabins',
        'downwind_sail',
        'price',
        'price_unit',
        'charter_fee',
        'deposit',
        'price_note',
        'skipper_name',
        'skipper_note',
        'free_seats',
        'seat_price',
        'seat_note',
        'status',
        'sort_order',
    ];

    protected $attributes = [
        'status' => CharterYachtStatus::Free->value,
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'cabins' => 'integer',
            'downwind_sail' => DownwindSail::class,
            'price' => 'integer',
            'price_unit' => CharterPriceUnit::class,
            'charter_fee' => 'integer',
            'deposit' => 'integer',
            'free_seats' => 'integer',
            'seat_price' => 'integer',
            'status' => CharterYachtStatus::class,
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

    public function division(): BelongsTo
    {
        return $this->belongsTo(ForeignRegattaDivision::class, 'division_id');
    }

    // ──────────────────────────────────────────────
    // Скоупы
    // ──────────────────────────────────────────────

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', CharterYachtStatus::Free->value);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // ──────────────────────────────────────────────
    // Характеристики: свои или унаследованные от дивизиона
    // ──────────────────────────────────────────────

    /**
     * Значение характеристики: своё, а если не задано — из дивизиона-флота.
     *
     * У дивизиона-списка своей спецификации нет, поэтому наследовать нечего:
     * там пустое поле так и остаётся пустым.
     */
    private function spec(string $attribute): mixed
    {
        $own = $this->getAttribute($attribute);

        if ($own !== null && $own !== '') {
            return $own;
        }

        $division = $this->division;

        return $division?->sharesSpec() ? $division->getAttribute($attribute) : null;
    }

    public function effectiveModel(): ?string
    {
        return $this->spec('model');
    }

    public function effectiveYear(): ?int
    {
        return $this->spec('year');
    }

    public function effectiveDescription(): ?string
    {
        return $this->spec('description');
    }

    public function effectiveCabins(): ?int
    {
        return $this->spec('cabins');
    }

    public function effectiveDownwindSail(): ?DownwindSail
    {
        return $this->spec('downwind_sail');
    }

    public function effectivePrice(): ?int
    {
        return $this->spec('price');
    }

    public function effectivePriceUnit(): ?CharterPriceUnit
    {
        return $this->spec('price_unit');
    }

    public function effectiveCharterFee(): ?int
    {
        return $this->spec('charter_fee');
    }

    public function effectiveDeposit(): ?int
    {
        return $this->spec('deposit');
    }

    public function effectivePriceNote(): ?string
    {
        return $this->spec('price_note');
    }

    /**
     * Фотографии лодки, а если своих нет — фотографии дивизиона-флота.
     *
     * @return list<array{src: string, webp: string|null, avif: string|null, caption: string}>
     */
    public function effectivePhotos(): array
    {
        $own = $this->galleryPhotos();

        if ($own !== []) {
            return $own;
        }

        $division = $this->division;

        return $division?->sharesSpec() ? $division->galleryPhotos() : [];
    }

    // ──────────────────────────────────────────────
    // Что предлагается по лодке
    // ──────────────────────────────────────────────

    /** Шкипер назначен — значит лодка идёт со своим капитаном и набирает экипаж. */
    public function hasSkipper(): bool
    {
        return trim((string) $this->skipper_name) !== '';
    }

    public function freeSeats(): int
    {
        return max(0, (int) $this->free_seats);
    }

    public function isAvailable(): bool
    {
        return $this->status->isAvailable();
    }

    /** Есть шкипер и остались места — можно проситься в экипаж. */
    public function sellsSeats(): bool
    {
        return $this->hasSkipper() && $this->freeSeats() > 0;
    }

    /** Шкипера нет, лодка свободна — сдаётся целиком. */
    public function offersWholeCharter(): bool
    {
        return ! $this->hasSkipper() && $this->isAvailable();
    }

    /**
     * Вариант участия, который заявка получит по кнопке на карточке лодки.
     *
     * null — кнопки нет: у лодки со шкипером кончились места, а лодка без
     * шкипера уже забронирована.
     */
    public function offeredParticipation(): ?ParticipationOption
    {
        return match (true) {
            $this->sellsSeats() => ParticipationOption::Seat,
            $this->offersWholeCharter() => ParticipationOption::Yacht,
            default => null,
        };
    }

    public function ctaLabel(): ?string
    {
        return match ($this->offeredParticipation()) {
            ParticipationOption::Seat => 'Хочу в экипаж',
            ParticipationOption::Yacht => 'Хочу эту яхту',
            default => null,
        };
    }

    /**
     * Поле формы заявки, в которое подставляется эта лодка.
     *
     * @see ServiceType::declaredPayloadFields()
     */
    public function ctaPayloadField(): ?string
    {
        return match ($this->offeredParticipation()) {
            ParticipationOption::Seat => 'crew_yacht',
            ParticipationOption::Yacht => 'charter_yacht',
            default => null,
        };
    }

    // ──────────────────────────────────────────────
    // Представление
    // ──────────────────────────────────────────────

    /** «Bavaria 46 «Nika», 2018» — подпись для витрины и выпадающего списка заявки. */
    public function title(): string
    {
        $title = trim((string) $this->effectiveModel());

        $name = trim((string) $this->name);
        if ($name !== '') {
            $title = $title === '' ? $name : $title.' «'.$name.'»';
        }

        if ($title === '') {
            $title = 'Яхта';
        }

        $year = $this->effectiveYear();

        return $year === null ? $title : $title.', '.$year;
    }

    public function priceLabel(): ?string
    {
        $price = $this->effectivePrice();

        if ($price === null) {
            return null;
        }

        $label = $this->formatPrice($price);
        $unit = $this->effectivePriceUnit();

        return $unit === null ? $label : $label.' '.$unit->label();
    }

    public function charterFeeLabel(): ?string
    {
        $fee = $this->effectiveCharterFee();

        return $fee === null ? null : $this->formatPrice($fee);
    }

    public function depositLabel(): ?string
    {
        $deposit = $this->effectiveDeposit();

        return $deposit === null ? null : $this->formatPrice($deposit);
    }

    public function cabinsLabel(): ?string
    {
        $cabins = $this->effectiveCabins();

        return $cabins === null ? null : Plural::with($cabins, 'каюта', 'каюты', 'кают');
    }

    public function seatPriceLabel(): ?string
    {
        return $this->seat_price === null ? null : $this->formatPrice($this->seat_price);
    }

    public function freeSeatsLabel(): ?string
    {
        $seats = $this->freeSeats();

        return $seats === 0 ? null : 'свободно '.Plural::with($seats, 'место', 'места', 'мест');
    }

    /**
     * Заготовка ли это — строка, заведённая под количество лодок дивизиона, в
     * которую админ ещё ничего не вносил.
     *
     * По этому признаку синхронизация решает, какие лишние строки можно убрать
     * при уменьшении `yachts_count` (@see App\Actions\Service\SyncFleetDivisionYachts).
     */
    public function isUntouchedStub(): bool
    {
        return ! $this->hasSkipper()
            && $this->free_seats === null
            && $this->seat_price === null
            && $this->status === CharterYachtStatus::Free
            && trim((string) $this->model) === ''
            && trim((string) $this->description) === ''
            && $this->getMedia('gallery')->isEmpty();
    }

    private function formatPrice(int $value): string
    {
        return number_format((float) $value, 0, ',', ' ').' ₽';
    }
}
