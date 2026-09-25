<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CharterPriceUnit;
use App\Enums\CharterYachtStatus;
use App\Enums\Currency;
use App\Enums\DownwindSail;
use App\Enums\FleetYachtAvailability;
use App\Enums\ParticipationOption;
use App\Models\Concerns\CountsFleetOccupancy;
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
    use CountsFleetOccupancy, HasCaptionedGallery, HasUuids, InteractsWithMedia, RegistersResponsiveFormats, SoftDeletes;

    protected $fillable = [
        'foreign_regatta_id',
        'division_id',
        'yacht_model_id',
        'model',
        'name',
        'year',
        'description',
        'cabins',
        'downwind_sail',
        'base_marina',
        'price',
        'price_unit',
        'charter_fee',
        'deposit',
        'downwind_sail_price',
        'downwind_sail_deposit',
        'price_note',
        'skipper_name',
        'skipper_note',
        'seats_total',
        'seats_taken',
        'solo_seats_total',
        'solo_seats_taken',
        'cabins_total',
        'cabins_taken',
        'seat_price',
        'solo_seat_price',
        'cabin_price',
        'seat_note',
        'status',
        'is_hidden',
        'sort_order',
    ];

    protected $attributes = [
        'status' => CharterYachtStatus::Free->value,
    ];

    /**
     * Поля, которые наследуются от дивизиона с общей ценой.
     *
     * Остальные поля спецификации приходят только от монотипа без выбора яхт
     * (@see inheritsFromDivision()).
     */
    private const SHARED_PRICING = [
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
            'downwind_sail_price' => 'integer',
            'downwind_sail_deposit' => 'integer',
            'seats_total' => 'integer',
            'seats_taken' => 'integer',
            'solo_seats_total' => 'integer',
            'solo_seats_taken' => 'integer',
            'cabins_total' => 'integer',
            'cabins_taken' => 'integer',
            'seat_price' => 'integer',
            'solo_seat_price' => 'integer',
            'cabin_price' => 'integer',
            'status' => CharterYachtStatus::class,
            'is_hidden' => 'boolean',
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

    /** Модель из общего справочника: описание, фотографии, каюты, парус. */
    public function yachtModel(): BelongsTo
    {
        return $this->belongsTo(CharterYachtModel::class, 'yacht_model_id');
    }

    // ──────────────────────────────────────────────
    // Скоупы
    // ──────────────────────────────────────────────

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', CharterYachtStatus::Free->value);
    }

    /** Лодки, не снятые с витрины. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_hidden', false);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // ──────────────────────────────────────────────
    // Характеристики: свои или унаследованные от дивизиона
    // ──────────────────────────────────────────────

    /**
     * Значение характеристики: своё, иначе из дивизиона-флота, иначе из
     * справочника моделей.
     *
     * Три уровня, потому что заполнять одно и то же трижды никто не станет:
     * общее для всех лодок модели лежит в справочнике, общее для дивизиона —
     * на дивизионе, своё — здесь.
     *
     * Что именно наследуется от дивизиона, решает его тип: спецификацию делит
     * только монотип без выбора яхт, цены — оба монотипа, гандикап не делит
     * ничего (@see App\Enums\FleetDivisionType).
     */
    private function spec(string $attribute): mixed
    {
        $own = $this->getAttribute($attribute);

        if ($own !== null && $own !== '') {
            return $own;
        }

        $division = $this->division;

        if ($division !== null && $this->inheritsFromDivision($division, $attribute)) {
            $inherited = $division->getAttribute($attribute);

            if ($inherited !== null && $inherited !== '') {
                return $inherited;
            }
        }

        return $this->catalogSpec($attribute);
    }

    /** Делится ли дивизион именно этим полем. */
    private function inheritsFromDivision(ForeignRegattaDivision $division, string $attribute): bool
    {
        return in_array($attribute, self::SHARED_PRICING, true)
            ? $division->sharesPrices()
            : $division->sharesSpec();
    }

    /**
     * То же значение из справочника — по модели, выбранной у самой лодки.
     *
     * Цен и года в справочнике нет (@see CharterYachtModel), поэтому отвечает
     * он только за то, что у всех лодок модели одинаково.
     */
    private function catalogSpec(string $attribute): mixed
    {
        $catalog = $this->catalogModel();

        if ($catalog === null) {
            return null;
        }

        return match ($attribute) {
            'model' => $catalog->name,
            'description' => $catalog->description,
            'cabins' => $catalog->cabins,
            'downwind_sail' => $catalog->downwind_sail,
            default => null,
        };
    }

    /** Запись справочника, из которой лодка берёт характеристики. */
    public function catalogModel(): ?CharterYachtModel
    {
        return $this->yachtModel;
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

    public function effectiveBaseMarina(): ?string
    {
        return $this->spec('base_marina');
    }

    public function effectivePrice(): ?int
    {
        return $this->spec('price');
    }

    public function effectivePriceUnit(): ?CharterPriceUnit
    {
        return $this->spec('price_unit');
    }

    /** Цена варианта участия: своя, а у лодки монотипа — общая цена дивизиона. */
    public function priceFor(ParticipationOption $option): ?int
    {
        return $this->spec($option->priceColumn());
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

    public function effectiveDownwindSailPrice(): ?int
    {
        return $this->spec('downwind_sail_price');
    }

    public function effectiveDownwindSailDeposit(): ?int
    {
        return $this->spec('downwind_sail_deposit');
    }

    public function effectivePriceNote(): ?string
    {
        return $this->spec('price_note');
    }

    /** Валюта цен: одна на всю регату, у лодки и дивизиона своей нет. */
    public function effectiveCurrency(): Currency
    {
        return Currency::fromNullable($this->regatta?->currency);
    }

    /**
     * Фотографии лодки, а если своих нет — дивизиона-флота, а затем модели
     * из справочника.
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

        if ($division?->sharesSpec()) {
            $inherited = $division->galleryPhotos();

            if ($inherited !== []) {
                return $inherited;
            }
        }

        return $this->catalogModel()?->galleryPhotos() ?? [];
    }

    // ──────────────────────────────────────────────
    // Что предлагается по лодке
    // ──────────────────────────────────────────────

    /** Шкипер назначен — значит лодка идёт со своим капитаном и набирает экипаж. */
    public function hasSkipper(): bool
    {
        return trim((string) $this->skipper_name) !== '';
    }

    /**
     * Сколько всего мест этого типа у лодки.
     *
     * «Яхта целиком» счётчиком не считается: лодка одна, и её состояние
     * описывает занятость (@see isAvailable()). Счётчики не наследуются от
     * дивизиона: там, где он ведёт их сам, лодки мест не продают
     * (@see ForeignRegattaDivision::sellsDirectly()).
     */
    public function occupancyTotal(ParticipationOption $option): ?int
    {
        return $option === ParticipationOption::Yacht
            ? null
            : $this->getAttribute($option->totalColumn());
    }

    /**
     * Свободна ли лодка под чартер целиком.
     *
     * Занятость — только про всю лодку: её забрали одним куском. Мест в экипаже
     * это не касается, поэтому «целиком занята, но в экипаж ещё берём» —
     * обычное состояние, а не противоречие.
     */
    public function isAvailable(): bool
    {
        return $this->status->isAvailable();
    }

    /**
     * Занятость для таблицы флота: «свободна», «есть места» или «занята».
     *
     * «Свободна» — лодку можно взять целиком. Если целиком её уже забрали (или
     * целиком она и не сдаётся), но в экипаж ещё набирают, — «есть места».
     * Лодка без цены чартера и без мест в продаже, но со свободным статусом —
     * тоже «свободна»: цены не пришли от чартера, а спросить про неё можно.
     */
    public function availability(): FleetYachtAvailability
    {
        if ($this->isAvailable() && $this->effectivePrice() !== null) {
            return FleetYachtAvailability::Free;
        }

        if ($this->hasAnyVacancy()) {
            return FleetYachtAvailability::SeatsLeft;
        }

        return $this->isAvailable()
            ? FleetYachtAvailability::Free
            : FleetYachtAvailability::Taken;
    }

    /** Остались ли у лодки свободные места хоть какого-то типа. */
    public function hasAnyVacancy(): bool
    {
        foreach (ParticipationOption::cases() as $option) {
            if ($option->isSeatLike() && $this->hasVacancy($option)) {
                return true;
            }
        }

        return false;
    }

    /** Показывается ли лодка на витрине вообще. */
    public function isPublished(): bool
    {
        return ! $this->is_hidden;
    }

    /**
     * Есть ли у лодки что-то своё, ради чего её стоит показать отдельно.
     *
     * Нужно там, где места продаёт сам дивизион: лодки в нём одинаковые и
     * взаимозаменяемые, поэтому три пустые карточки «№1, №2, №3» посетителю
     * ничего не говорят — а вот лодка со шкипером, своим описанием, своими
     * фотографиями или уже занятая говорит.
     */
    public function hasOwnDetails(): bool
    {
        return $this->hasSkipper()
            || trim((string) $this->description) !== ''
            || ! $this->isAvailable()
            || $this->getMedia('gallery')->isNotEmpty();
    }

    /** Продаёт ли лодка места этого типа: цена задана и свободные остались. */
    public function sellsSeatsOf(ParticipationOption $option): bool
    {
        return $this->isPublished()
            && $this->priceFor($option) !== null
            && $this->hasVacancy($option);
    }

    /** Есть ли у лодки хоть какие-то места в продаже. */
    public function sellsAnySeats(): bool
    {
        foreach (ParticipationOption::cases() as $option) {
            if ($option->isSeatLike() && $this->sellsSeatsOf($option)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Сдаётся ли лодка целиком: задана цена чартера и лодка свободна.
     *
     * Шкипер не мешает — лодку берут и с ним. Снять лодку со всех вариантов
     * разом можно флагом «не показывать на сайте»: занятость для этого не
     * годится, она про чартер целиком.
     */
    public function offersWholeCharter(): bool
    {
        return $this->isPublished()
            && $this->isAvailable()
            && $this->effectivePrice() !== null;
    }

    /**
     * Варианты участия, которые предлагаются по этой лодке.
     *
     * Их может быть несколько сразу: одна и та же лодка продаёт отдельные
     * места, каюты и себя целиком — на витрине это отдельные кнопки с ценой
     * каждого варианта. Пустой список — предлагать нечего: лодка снята с
     * витрины, места разобраны, чартер занят или цены не заведены.
     *
     * @return list<ParticipationOption>
     */
    public function offeredParticipations(): array
    {
        // Монотип без выбора яхт продаёт сам: там и счётчик, и кнопки на
        // дивизионе, а лодки в нём взаимозаменяемы.
        if ($this->division?->sellsDirectly()) {
            return [];
        }

        return array_values(array_filter(
            ParticipationOption::cases(),
            fn (ParticipationOption $option): bool => $option === ParticipationOption::Yacht
                ? $this->offersWholeCharter()
                : $this->sellsSeatsOf($option),
        ));
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
        if ($option === ParticipationOption::Yacht) {
            return $this->priceLabel();
        }

        $price = $this->priceFor($option);

        return $price === null ? null : $this->formatPrice($price);
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

    public function downwindSailPriceLabel(): ?string
    {
        $price = $this->effectiveDownwindSailPrice();

        return $price === null ? null : $this->formatPrice($price);
    }

    public function downwindSailDepositLabel(): ?string
    {
        $deposit = $this->effectiveDownwindSailDeposit();

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

    /**
     * Занятость по объявленным типам мест: «за что» => «занято 4 из 5…».
     *
     * @return array<string, string>
     */
    public function occupancyLabels(): array
    {
        return array_filter(
            collect(ParticipationOption::cases())
                ->filter(fn (ParticipationOption $option): bool => $option->isSeatLike())
                ->mapWithKeys(fn (ParticipationOption $option): array => [
                    $option->label() => $this->occupancyLabel($option),
                ])
                ->all(),
            fn (?string $value): bool => $value !== null,
        );
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
            && ! $this->is_hidden
            && $this->seats_total === null
            && $this->solo_seats_total === null
            && $this->cabins_total === null
            && $this->seat_price === null
            && $this->solo_seat_price === null
            && $this->cabin_price === null
            && $this->status === CharterYachtStatus::Free
            && trim((string) $this->model) === ''
            && trim((string) $this->base_marina) === ''
            && trim((string) $this->description) === ''
            && $this->getMedia('gallery')->isEmpty();
    }

    private function formatPrice(int $value): string
    {
        return $this->effectiveCurrency()->format($value);
    }
}
