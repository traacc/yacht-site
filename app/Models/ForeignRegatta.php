<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\ServiceOptionProvider;
use App\Contracts\ServiceSubject;
use App\Enums\Currency;
use App\Enums\ParticipationOption;
use App\Models\Concerns\HasCaptionedGallery;
use App\Models\Concerns\RegistersResponsiveFormats;
use App\Support\Plural;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Регата за рубежом (раздел «Услуги», ТЗ 3-го этапа, п. 7).
 *
 * Контентная модель, а не соревновательная `Regatta`: рейтингов, протоколов и
 * заявок команд здесь нет, зато есть цены, варианты участия и чартерный флот.
 * В общий календарь сезона попадает через App\Services\SeasonCalendar.
 *
 * Заявка на участие — обычный ServiceRequest типа ForeignRegatta, связанный с
 * регатой через morph-поле `subject`.
 */
class ForeignRegatta extends Model implements HasMedia, ServiceOptionProvider, ServiceSubject
{
    use HasCaptionedGallery, HasUuids, InteractsWithMedia, RegistersResponsiveFormats, SoftDeletes;

    protected $fillable = [
        'season_id',
        'title',
        'slug',
        'summary',
        'content',
        'schedule',
        'country',
        'region',
        'route_summary',
        'fleet_note',
        'date_start',
        'date_end',
        'participation_options',
        'price_per_seat',
        'price_per_cabin',
        'price_note',
        'currency',
        'video_links',
        'is_published',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'date_start' => 'date',
            'date_end' => 'date',
            'participation_options' => 'array',
            'price_per_seat' => 'integer',
            'price_per_cabin' => 'integer',
            'currency' => Currency::class,
            'video_links' => 'array',
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $regatta): void {
            // Сезон нужен календарю. Админ может выбрать его вручную, но чаще
            // достаточно года начала — подставляем, как это делает Regatta со
            // своими автополями в booted().
            if ($regatta->season_id === null && $regatta->date_start !== null) {
                $regatta->season_id = Season::query()
                    ->where('year', (int) $regatta->date_start->format('Y'))
                    ->value('id');
            }
        });
    }

    // ──────────────────────────────────────────────
    // Media
    // ──────────────────────────────────────────────

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')
            ->useDisk('public')
            ->singleFile();

        $this->addMediaCollection('gallery')
            ->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addResponsiveFormatConversions();
    }

    // ──────────────────────────────────────────────
    // Связи
    // ──────────────────────────────────────────────

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /** Дивизионы флота: «флот одинаковых лодок» и «список конкретных лодок». */
    public function divisions(): HasMany
    {
        // chaperone: дивизион спрашивает регату о валюте цен
        // (@see ForeignRegattaDivision::priceCurrency()) — обратная связь
        // проставляется сразу, без запроса на каждую запись.
        return $this->hasMany(ForeignRegattaDivision::class)->chaperone('regatta')->ordered();
    }

    /** Чартерный флот регаты: список «яхт под аренду» из ТЗ. */
    public function charterYachts(): HasMany
    {
        // chaperone — по той же причине, что и у дивизионов:
        // @see ForeignRegattaYacht::effectiveCurrency().
        return $this->hasMany(ForeignRegattaYacht::class)->chaperone('regatta')->ordered();
    }

    /** Заявки на участие — для счётчика в админке. */
    public function serviceRequests(): MorphMany
    {
        return $this->morphMany(ServiceRequest::class, 'subject');
    }

    // ──────────────────────────────────────────────
    // Скоупы
    // ──────────────────────────────────────────────

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Предстоящие регаты.
     *
     * Считаем по дате окончания: идущая сейчас регата не должна уезжать в
     * архив на второй день. У однодневных гонок date_end пуста.
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereRaw(
            'COALESCE(date_end, date_start) >= ?',
            [now()->toDateString()],
        );
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->whereRaw(
            'COALESCE(date_end, date_start) < ?',
            [now()->toDateString()],
        );
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('date_start');
    }

    /** Для архива: сначала самые свежие регаты. */
    public function scopeRecentFirst(Builder $query): Builder
    {
        return $query->orderByDesc('date_start');
    }

    /**
     * Регаты сезона.
     *
     * Сезон проставляется автоматически, но своей записи Season на нужный год
     * может ещё не быть — тогда ориентируемся на год начала регаты.
     */
    public function scopeOfSeasonYear(Builder $query, int $year): Builder
    {
        return $query->where(fn (Builder $inner) => $inner
            ->whereHas('season', fn (Builder $season) => $season->where('year', $year))
            ->orWhere(fn (Builder $fallback) => $fallback
                ->whereNull('season_id')
                ->whereYear('date_start', $year)));
    }

    // ──────────────────────────────────────────────
    // Контракт ServiceSubject
    // ──────────────────────────────────────────────

    public function acceptsServiceRequests(): bool
    {
        return $this->is_published && ! $this->isPast();
    }

    public function subjectLabel(): string
    {
        return 'Регата «'.$this->title.'», '.$this->dateRange();
    }

    public function subjectUrl(): ?string
    {
        return $this->publicUrl();
    }

    // ──────────────────────────────────────────────
    // Контракт ServiceOptionProvider
    // ──────────────────────────────────────────────

    /**
     * Варианты участия — объявленные регатой, предложения — те, что ещё есть.
     *
     * Под каждый вариант свой список: где-то места ещё остались, а лодка
     * целиком уже занята. Предложить может как лодка, так и дивизион — монотип
     * без выбора яхт продаёт места общим пулом
     * (@see ForeignRegattaDivision::sellsDirectly()), поэтому значения
     * помечены источником: `yacht:<id>` или `division:<id>`.
     *
     * @return array<string, array<string, string>>
     */
    public function serviceOptions(): array
    {
        $options = [
            'participation' => collect($this->participationOptions())
                ->mapWithKeys(fn (ParticipationOption $option): array => [
                    $option->value => $option->label(),
                ])
                ->all(),
        ];

        foreach (ParticipationOption::cases() as $option) {
            $options[$option->payloadField()] = $this->offersFor($option);
        }

        return $options;
    }

    /**
     * Кто ещё предлагает этот вариант: ключ => подпись.
     *
     * @return array<string, string>
     */
    public function offersFor(ParticipationOption $option): array
    {
        $fromDivisions = $this->divisions
            ->filter(fn (ForeignRegattaDivision $division): bool => in_array(
                $option,
                $division->offeredParticipations(),
                strict: true,
            ))
            ->mapWithKeys(fn (ForeignRegattaDivision $division): array => [
                self::offerKey($division) => 'Дивизион «'.$division->title().'» — '
                    .mb_strtolower($option->label())
                    .' '.$division->participationPriceLabel($option)
                    .', '.$division->occupancyLabel($option),
            ]);

        $fromYachts = $this->visibleCharterYachts()
            ->filter(fn (ForeignRegattaYacht $yacht): bool => $yacht->offers($option))
            ->mapWithKeys(fn (ForeignRegattaYacht $yacht): array => [
                self::offerKey($yacht) => $yacht->title()
                    .' — '.mb_strtolower($option->label())
                    .' '.$yacht->participationPriceLabel($option)
                    .($yacht->occupancyLabel($option) === null ? '' : ', '.$yacht->occupancyLabel($option))
                    .($yacht->hasSkipper() ? ', шкипер '.$yacht->skipper_name : ''),
            ]);

        return $fromDivisions->union($fromYachts)->all();
    }

    /** `yacht:<id>` или `division:<id>` — источник предложения в заявке. */
    public static function offerKey(Model $seller): string
    {
        $prefix = $seller instanceof ForeignRegattaDivision ? 'division' : 'yacht';

        return $prefix.':'.$seller->getKey();
    }

    /**
     * Подпись сохранённого значения: ищем среди всех, включая занятые и
     * удалённые, — иначе поданная вчера заявка покажет в админке голый uuid.
     *
     * Значения без префикса остались от заявок, поданных до дивизионных
     * предложений: тогда в поле лежал голый id лодки.
     */
    public function serviceOptionLabel(string $field, string $value): ?string
    {
        if ($field === 'participation') {
            return ParticipationOption::tryFrom($value)?->label();
        }

        $fields = array_map(
            fn (ParticipationOption $option): string => $option->payloadField(),
            ParticipationOption::cases(),
        );

        if (! in_array($field, $fields, strict: true)) {
            return null;
        }

        [$kind, $id] = str_contains($value, ':')
            ? explode(':', $value, 2)
            : ['yacht', $value];

        if ($kind === 'division') {
            $division = $this->divisions()->withTrashed()->whereKey($id)->first();

            return $division === null ? null : 'Дивизион «'.$division->title().'»';
        }

        return $this->charterYachts()
            ->withTrashed()
            ->with('division')
            ->whereKey($id)
            ->first()
            ?->title();
    }

    // ──────────────────────────────────────────────
    // Вывод на сайте
    // ──────────────────────────────────────────────

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function publicUrl(): string
    {
        return route('services.foreign-regatta-item', $this);
    }

    public function isPast(): bool
    {
        return ($this->date_end ?? $this->date_start)->endOfDay()->isPast();
    }

    public function dateRange(): string
    {
        if ($this->date_end === null || $this->date_start->isSameDay($this->date_end)) {
            return $this->date_start->format('d.m.Y');
        }

        return $this->date_start->format('d.m.Y').' — '.$this->date_end->format('d.m.Y');
    }

    /** «8 дней» — длительность регаты включительно. */
    public function durationLabel(): ?string
    {
        if ($this->date_end === null) {
            return null;
        }

        $days = $this->date_start->diffInDays($this->date_end) + 1;

        return $days > 1 ? Plural::with((int) $days, 'день', 'дня', 'дней') : null;
    }

    /** «Хорватия, Далмация» — место проведения одной строкой. */
    public function placeLabel(): ?string
    {
        $place = array_filter([
            trim((string) $this->country),
            trim((string) $this->region),
        ], fn (string $part): bool => $part !== '');

        return $place === [] ? null : implode(', ', $place);
    }

    /**
     * Варианты участия: объявленные галочками плюс те, что предлагает флот.
     *
     * @return list<ParticipationOption>
     */
    public function participationOptions(): array
    {
        return collect($this->participation_options ?? [])
            ->map(fn ($value): ?ParticipationOption => ParticipationOption::tryFrom((string) $value))
            ->filter()
            ->concat($this->participationOfferedByFleet())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Варианты, которые следуют из состояния флота.
     *
     * Кнопка на витрине подставляет вариант участия в заявку, а форма
     * принимает только объявленные варианты. Поэтому всё, что предлагают
     * дивизионы и лодки, объявляется само — даже если галочку в форме регаты
     * забыли поставить.
     *
     * @return Collection<int, ParticipationOption>
     */
    private function participationOfferedByFleet(): Collection
    {
        // И дивизионы, продающие общим пулом, и отдельные лодки: форма заявки
        // принимает только объявленные варианты, поэтому пропустить нельзя ни
        // тех, ни других.
        return $this->divisions
            ->flatMap(fn (ForeignRegattaDivision $division): array => $division->offeredParticipations())
            ->concat($this->visibleCharterYachts()
                ->flatMap(fn (ForeignRegattaYacht $yacht): array => $yacht->offeredParticipations()))
            ->unique()
            ->values();
    }

    public function offers(ParticipationOption $option): bool
    {
        return in_array($option, $this->participationOptions(), strict: true);
    }

    /**
     * Лодки, которые вообще показываются на витрине.
     *
     * Снятая флагом лодка не должна ни попадать в таблицу флота, ни считаться в
     * счётчиках, ни предлагаться в заявке
     * (@see ForeignRegattaYacht::isPublished()).
     *
     * @return Collection<int, ForeignRegattaYacht>
     */
    public function visibleCharterYachts(): Collection
    {
        return $this->charterYachts->filter(
            fn (ForeignRegattaYacht $yacht): bool => $yacht->isPublished(),
        )->values();
    }

    /** Показывать ли флот на странице регаты. */
    public function showsCharterFleet(): bool
    {
        return $this->fleetGroups()->isNotEmpty();
    }

    /**
     * Флот, разложенный по дивизионам, — так он и выводится на странице.
     *
     * Лодки без дивизиона (заведённые до появления дивизионов) собираются в
     * группу без заголовка, чтобы не пропасть с витрины.
     *
     * @return Collection<int, array{division: ForeignRegattaDivision|null, yachts: Collection<int, ForeignRegattaYacht>}>
     */
    public function fleetGroups(): Collection
    {
        $byDivision = $this->visibleCharterYachts()->groupBy('division_id');

        $groups = $this->divisions
            ->map(function (ForeignRegattaDivision $division) use ($byDivision): array {
                $yachts = $byDivision->get((string) $division->getKey(), collect())->values();

                // Дивизион, продающий общим пулом, сам и рассказывает о флоте:
                // из лодок показываем только те, у которых есть что добавить.
                if ($division->sellsDirectly()) {
                    $yachts = $yachts->filter(
                        fn (ForeignRegattaYacht $yacht): bool => $yacht->hasOwnDetails(),
                    )->values();
                }

                return ['division' => $division, 'yachts' => $yachts];
            })
            ->filter(fn (array $group): bool => $group['yachts']->isNotEmpty()
                || ($group['division']?->offeredParticipations() ?? []) !== []);

        // groupBy приводит null-ключ к пустой строке.
        $orphans = $byDivision->get('', collect())->values();

        if ($orphans->isNotEmpty()) {
            $groups = $groups->concat([['division' => null, 'yachts' => $orphans]]);
        }

        return $groups->values();
    }

    /**
     * Лодки, которые сдаются целиком: задана цена чартера, статус свободный.
     *
     * @return Collection<int, ForeignRegattaYacht>
     */
    public function yachtsForWholeCharter(): Collection
    {
        return $this->visibleCharterYachts()->filter(
            fn (ForeignRegattaYacht $yacht): bool => $yacht->offersWholeCharter(),
        )->values();
    }

    /**
     * Сколько мест этого варианта ещё свободно по всему флоту.
     *
     * Считаем и по дивизионам, которые продают сами, и по отдельным лодкам:
     * на витрине это одна цифра «в экипажи набирается N человек».
     */
    public function vacancies(ParticipationOption $option): int
    {
        $fromDivisions = $this->divisions
            ->filter(fn (ForeignRegattaDivision $division): bool => in_array(
                $option,
                $division->offeredParticipations(),
                strict: true,
            ))
            ->sum(fn (ForeignRegattaDivision $division): int => $division->occupancyLeft($option) ?? 0);

        $fromYachts = $this->visibleCharterYachts()
            ->filter(fn (ForeignRegattaYacht $yacht): bool => $yacht->offers($option))
            ->sum(function (ForeignRegattaYacht $yacht) use ($option): int {
                // Лодка целиком счётчика не имеет: она либо свободна, либо нет.
                return $option === ParticipationOption::Yacht
                    ? 1
                    : ($yacht->occupancyLeft($option) ?? 0);
            });

        return (int) $fromDivisions + (int) $fromYachts;
    }

    /** Сколько мест в экипажи продаётся по всему флоту — местами и каютами. */
    public function freeCrewSeats(): int
    {
        return collect(ParticipationOption::cases())
            ->filter(fn (ParticipationOption $option): bool => $option->isSeatLike())
            ->sum(fn (ParticipationOption $option): int => $this->vacancies($option));
    }

    /** Сколько лодок ещё можно взять целиком. */
    public function freeWholeYachts(): int
    {
        return $this->vacancies(ParticipationOption::Yacht);
    }

    /**
     * «от 150 000 ₽ за место» — самое дешёвое предложение регаты для витрины.
     *
     * Считается по флоту, а не по полям регаты: цены живут у дивизионов и
     * лодок, и отдельная цифра на регате с ними расходится. Поля регаты
     * остаются запасным вариантом — для регат, флот которых ещё не заведён.
     *
     * Варианты перебираются от самого доступного входа к самому дорогому:
     * место, каюта, яхта целиком.
     */
    public function priceFromLabel(): ?string
    {
        // От самого доступного входа к самому дорогому.
        $units = [
            ParticipationOption::Seat->value => 'за место',
            ParticipationOption::SoloSeat->value => 'за место в одноместной каюте',
            ParticipationOption::Cabin->value => 'за каюту',
            ParticipationOption::Yacht->value => 'за яхту целиком',
        ];

        foreach ($units as $value => $unit) {
            $option = ParticipationOption::from($value);
            $cheapest = $this->cheapestOffer($option);

            if ($cheapest !== null) {
                return 'от '.$this->priceCurrency()->format($cheapest).' '.$unit;
            }
        }

        $own = $this->seatPriceLabel() ?? $this->cabinPriceLabel();

        return $own === null ? null : 'от '.$own;
    }

    /**
     * Самая низкая цена варианта по всему флоту — у дивизионов и у лодок.
     *
     * Сравнивать суммы можно напрямую: валюта у регаты одна и на дивизионы с
     * лодками не делится (@see ForeignRegattaYacht::effectiveCurrency()).
     */
    private function cheapestOffer(ParticipationOption $option): ?int
    {
        $fromDivisions = $this->divisions
            ->filter(fn (ForeignRegattaDivision $division): bool => in_array(
                $option,
                $division->offeredParticipations(),
                strict: true,
            ))
            ->map(fn (ForeignRegattaDivision $division): ?int => $division->priceFor($option));

        $fromYachts = $this->visibleCharterYachts()
            ->filter(fn (ForeignRegattaYacht $yacht): bool => $yacht->offers($option))
            ->map(fn (ForeignRegattaYacht $yacht): ?int => $yacht->priceFor($option));

        return $fromDivisions
            ->concat($fromYachts)
            ->filter(fn (?int $amount): bool => $amount !== null)
            ->min();
    }

    public function seatPriceLabel(): ?string
    {
        return $this->price_per_seat === null
            ? null
            : $this->formatPrice($this->price_per_seat).' за место в двухместной каюте';
    }

    public function cabinPriceLabel(): ?string
    {
        return $this->price_per_cabin === null
            ? null
            : $this->formatPrice($this->price_per_cabin).' за двухместную каюту';
    }

    /** Валюта цен регаты: пусто в БД — рубли. */
    public function priceCurrency(): Currency
    {
        return Currency::fromNullable($this->currency);
    }

    private function formatPrice(int $value): string
    {
        return $this->priceCurrency()->format($value);
    }
}
