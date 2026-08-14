<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\ServiceOptionProvider;
use App\Contracts\ServiceSubject;
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
        return $this->hasMany(ForeignRegattaDivision::class)->ordered();
    }

    /** Чартерный флот регаты: список «яхт под аренду» из ТЗ. */
    public function charterYachts(): HasMany
    {
        return $this->hasMany(ForeignRegattaYacht::class)->ordered();
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
     * Варианты участия — объявленные регатой, яхты — те, что ещё предлагаются.
     *
     * Списка яхт два, потому что вопросы разные: под «яхту целиком» подходят
     * свободные лодки без шкипера, под «место» — лодки со шкипером, у которых
     * остались места.
     *
     * @return array<string, array<string, string>>
     */
    public function serviceOptions(): array
    {
        return [
            'participation' => collect($this->participationOptions())
                ->mapWithKeys(fn (ParticipationOption $option): array => [
                    $option->value => $option->label(),
                ])
                ->all(),

            'charter_yacht' => $this->yachtsForWholeCharter()
                ->mapWithKeys(fn (ForeignRegattaYacht $yacht): array => [
                    (string) $yacht->getKey() => $yacht->title()
                        .($yacht->priceLabel() === null ? '' : ' — '.$yacht->priceLabel()),
                ])
                ->all(),

            'crew_yacht' => $this->yachtsSellingSeats()
                ->mapWithKeys(fn (ForeignRegattaYacht $yacht): array => [
                    (string) $yacht->getKey() => $yacht->title()
                        .' — шкипер '.$yacht->skipper_name
                        .', '.$yacht->freeSeatsLabel(),
                ])
                ->all(),
        ];
    }

    /**
     * Подпись сохранённого значения: яхту ищем среди всех, включая занятые и
     * удалённые, — иначе поданная вчера заявка покажет в админке голый uuid.
     */
    public function serviceOptionLabel(string $field, string $value): ?string
    {
        if ($field === 'participation') {
            return ParticipationOption::tryFrom($value)?->label();
        }

        if ($field !== 'charter_yacht' && $field !== 'crew_yacht') {
            return null;
        }

        return $this->charterYachts()
            ->withTrashed()
            ->with('division')
            ->whereKey($value)
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
     * Кнопка на карточке лодки подставляет вариант участия в заявку, а форма
     * принимает только объявленные варианты. Поэтому лодка со свободными
     * местами объявляет «место», а свободная лодка без шкипера — «яхту
     * целиком», даже если галочку в форме регаты забыли поставить.
     *
     * @return Collection<int, ParticipationOption>
     */
    private function participationOfferedByFleet(): Collection
    {
        return $this->charterYachts
            ->map(fn (ForeignRegattaYacht $yacht): ?ParticipationOption => $yacht->offeredParticipation())
            ->filter()
            ->unique()
            ->values();
    }

    public function offers(ParticipationOption $option): bool
    {
        return in_array($option, $this->participationOptions(), strict: true);
    }

    /** Показывать ли флот на странице регаты. */
    public function showsCharterFleet(): bool
    {
        return $this->charterYachts->isNotEmpty();
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
        $byDivision = $this->charterYachts->groupBy('division_id');

        $groups = $this->divisions
            ->map(fn (ForeignRegattaDivision $division): array => [
                'division' => $division,
                'yachts' => $byDivision->get((string) $division->getKey(), collect())->values(),
            ])
            ->filter(fn (array $group): bool => $group['yachts']->isNotEmpty());

        // groupBy приводит null-ключ к пустой строке.
        $orphans = $byDivision->get('', collect())->values();

        if ($orphans->isNotEmpty()) {
            $groups = $groups->concat([['division' => null, 'yachts' => $orphans]]);
        }

        return $groups->values();
    }

    /**
     * Лодки, которые сдаются целиком: шкипера нет, статус свободный.
     *
     * @return Collection<int, ForeignRegattaYacht>
     */
    public function yachtsForWholeCharter(): Collection
    {
        return $this->charterYachts->filter(
            fn (ForeignRegattaYacht $yacht): bool => $yacht->offersWholeCharter(),
        )->values();
    }

    /**
     * Лодки, которые набирают экипаж: шкипер есть, места остались.
     *
     * @return Collection<int, ForeignRegattaYacht>
     */
    public function yachtsSellingSeats(): Collection
    {
        return $this->charterYachts->filter(
            fn (ForeignRegattaYacht $yacht): bool => $yacht->sellsSeats(),
        )->values();
    }

    /** Сколько всего мест в экипажи продаётся по всему флоту. */
    public function freeCrewSeats(): int
    {
        return $this->yachtsSellingSeats()->sum(
            fn (ForeignRegattaYacht $yacht): int => $yacht->freeSeats(),
        );
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

    private function formatPrice(int $value): string
    {
        return number_format((float) $value, 0, ',', ' ').' ₽';
    }
}
