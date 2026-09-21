<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CharterPriceUnit;
use App\Enums\CharterYachtStatus;
use App\Enums\Currency;
use App\Enums\DownwindSail;
use App\Enums\ParticipationOption;
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
 * Что предлагается по лодке, определяют заполненные цены, а не шкипер: цена
 * места и свободные места продают места, цена каюты — каюты, цена чартера —
 * лодку целиком, и всё это одновременно. Шкипер ни одному варианту не мешает —
 * чартер со шкипером обычное дело. Занятость гасит сразу все варианты.
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
        'currency',
        'skipper_name',
        'skipper_note',
        'free_seats',
        'seat_price',
        'cabin_price',
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
            'cabin_price' => 'integer',
            'currency' => Currency::class,
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

    /** Цена места: своя, а у лодки из флота — общая цена дивизиона. */
    public function effectiveSeatPrice(): ?int
    {
        return $this->spec('seat_price');
    }

    /** Цена каюты: своя, а у лодки из флота — общая цена дивизиона. */
    public function effectiveCabinPrice(): ?int
    {
        return $this->spec('cabin_price');
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
     * Валюта цен лодки: своя, иначе дивизиона-флота, иначе регаты.
     *
     * Наследуется вместе с ценой: у восьми лодок одного дивизиона валюта
     * задаётся там же, где сумма, а у списка конкретных лодок — на регате.
     */
    public function effectiveCurrency(): Currency
    {
        $own = $this->spec('currency');

        return $own instanceof Currency
            ? $own
            : Currency::fromNullable($this->regatta?->currency);
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

    /** Остались места и задана их цена — можно проситься в экипаж. */
    public function sellsSeats(): bool
    {
        return $this->isAvailable()
            && $this->freeSeats() > 0
            && $this->effectiveSeatPrice() !== null;
    }

    /** Остались места и задана цена каюты — продаётся каюта целиком. */
    public function sellsCabins(): bool
    {
        return $this->isAvailable()
            && $this->freeSeats() > 0
            && $this->effectiveCabinPrice() !== null;
    }

    /**
     * Сдаётся ли лодка целиком: задана цена чартера и лодка свободна.
     *
     * Шкипер не мешает — лодку берут и с ним. Забронированную лодку из всех
     * вариантов убирает статус, а не обнуление цен: занятость ставится один раз
     * и гасит места, каюты и чартер разом.
     */
    public function offersWholeCharter(): bool
    {
        return $this->isAvailable() && $this->effectivePrice() !== null;
    }

    /**
     * Варианты участия, которые предлагаются по этой лодке.
     *
     * Их может быть до трёх сразу: одна и та же лодка продаёт отдельные места,
     * каюты и себя целиком — на витрине это отдельные кнопки с ценой каждого
     * варианта. Пустой список — предлагать нечего: лодка занята, места кончились
     * или цены не заведены.
     *
     * @return list<ParticipationOption>
     */
    public function offeredParticipations(): array
    {
        return array_values(array_filter([
            $this->sellsSeats() ? ParticipationOption::Seat : null,
            $this->sellsCabins() ? ParticipationOption::Cabin : null,
            $this->offersWholeCharter() ? ParticipationOption::Yacht : null,
        ]));
    }

    public function offers(ParticipationOption $option): bool
    {
        return in_array($option, $this->offeredParticipations(), strict: true);
    }

    /**
     * Цена варианта участия по этой лодке — подпись на кнопке заявки.
     *
     * У предлагаемого варианта цена есть всегда: без неё вариант и не
     * предлагается (@see offeredParticipations()).
     */
    public function participationPriceLabel(ParticipationOption $option): ?string
    {
        return match ($option) {
            ParticipationOption::Seat => $this->seatPriceLabel(),
            ParticipationOption::Cabin => $this->cabinPriceLabel(),
            ParticipationOption::Yacht => $this->priceLabel(),
        };
    }

    // ──────────────────────────────────────────────
    // Представление
    // ──────────────────────────────────────────────

    /** «Bavaria 46 «Nika», 2018» — подпись для выпадающего списка заявки и админки. */
    public function title(): string
    {
        $year = $this->effectiveYear();

        return $year === null ? $this->shortTitle() : $this->shortTitle().', '.$year;
    }

    /**
     * «Bavaria 46 «Nika»» — заголовок карточки лодки, без года.
     *
     * Год на витрине печатается строкой характеристик под заголовком, поэтому
     * в самом заголовке он был бы дублем. В списках заявки и админки, где
     * характеристик рядом нет, год нужен — там используется title().
     */
    public function shortTitle(): string
    {
        $title = trim((string) $this->effectiveModel());

        $name = trim((string) $this->name);
        if ($name !== '') {
            $title = $title === '' ? $name : $title.' «'.$name.'»';
        }

        return $title === '' ? 'Яхта' : $title;
    }

    /** Берёт ли лодка цены у дивизиона-флота, а не несёт свои. */
    public function inheritsPrices(): bool
    {
        return $this->division?->sharesSpec() === true;
    }

    /**
     * Цены, заданные у самой лодки, — без унаследованных от дивизиона.
     *
     * Общие цены дивизиона-флота витрина печатает один раз над списком лодок
     * (@see ForeignRegattaDivision::priceLabels()), поэтому в карточке остаются
     * только собственные переопределения лодки: иначе одна и та же сумма стоит
     * и в шапке дивизиона, и в каждой его карточке.
     *
     * @return array{charter: ?string, fee: ?string, deposit: ?string, seat: ?string, cabin: ?string, note: ?string}
     */
    public function ownPriceLabels(): array
    {
        $own = function (string $attribute): bool {
            $value = $this->getAttribute($attribute);

            return ! $this->inheritsPrices() || ($value !== null && $value !== '');
        };

        return [
            'charter' => $own('price') ? $this->priceLabel() : null,
            'fee' => $own('charter_fee') ? $this->charterFeeLabel() : null,
            'deposit' => $own('deposit') ? $this->depositLabel() : null,
            'seat' => $own('seat_price') ? $this->seatPriceLabel() : null,
            'cabin' => $own('cabin_price') ? $this->cabinPriceLabel() : null,
            'note' => $own('price_note') ? $this->effectivePriceNote() : null,
        ];
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
        $price = $this->effectiveSeatPrice();

        return $price === null ? null : $this->formatPrice($price);
    }

    public function cabinPriceLabel(): ?string
    {
        $price = $this->effectiveCabinPrice();

        return $price === null ? null : $this->formatPrice($price);
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
            && $this->cabin_price === null
            && $this->status === CharterYachtStatus::Free
            && trim((string) $this->model) === ''
            && trim((string) $this->description) === ''
            && $this->getMedia('gallery')->isEmpty();
    }

    private function formatPrice(int $value): string
    {
        return $this->effectiveCurrency()->format($value);
    }
}
