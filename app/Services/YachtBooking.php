<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RentalRequestStatus;
use App\Models\Yacht;
use App\Models\YachtRental;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Витрина бронирования яхт (ТЗ 3-го этапа, п. 7, подраздел «Аренда яхт»).
 *
 * Поиск по датам, фильтры и стоимость периода считаются на сервере — как у
 * досок объявлений (@see AdvertBoard) и подбора флота (@see FleetAvailability).
 * Каталог /yachts отдаёт весь список одним JSON и фильтрует его в Alpine; для
 * витрины с пагинацией и ссылками, которые можно переслать, это не подходит.
 *
 * Занятость берётся из скоупа `Yacht::availableForRent()` — общего с подбором
 * флота, чтобы страницы не разошлись в ответе «свободна / занята».
 */
final class YachtBooking
{
    private const PER_PAGE = 12;

    /**
     * Результат поиска: разобранные даты и страница подходящих яхт.
     *
     * Даты разбираются мягко: мусор в query-параметрах не роняет страницу, а
     * трактуется как «даты не выбраны» — тогда показывается весь арендный флот.
     *
     * @param  array<string, mixed>  $filters  date_start, date_end, q, region, yacht_class, purpose, price_from, price_to, sort
     * @return array{
     *     from: ?CarbonImmutable,
     *     to: ?CarbonImmutable,
     *     days: int,
     *     searched: bool,
     *     yachts: LengthAwarePaginator<int, Yacht>,
     * }
     */
    public function search(array $filters): array
    {
        [$from, $to] = $this->parseRange($filters['date_start'] ?? null, $filters['date_end'] ?? null);

        $searched = $from !== null && $to !== null;

        $query = $this->baseQuery();

        if ($searched) {
            $query->availableForRent($from->toDateString(), $to->toDateString());
        }

        $this->applyFilters($query, $filters, $from, $to);

        // Цена карточки и сортировка по цене считаются одним подзапросом:
        // минимум среди предложений, покрывающих выбранный период.
        $query->withMin(
            ['rentals as price_per_day' => fn (Builder $rentals) => $this->coveringRentals($rentals, $from, $to)],
            'price_event',
        );

        $this->applySort($query, (string) ($filters['sort'] ?? ''));

        return [
            'from' => $from,
            'to' => $searched ? $to : null,
            'days' => $searched ? $this->days($from, $to) : 0,
            'searched' => $searched,
            'yachts' => $query->paginate(self::PER_PAGE)->withQueryString(),
        ];
    }

    /**
     * Свободна ли конкретная яхта на весь диапазон.
     *
     * Нужна на странице яхты: даты приходят из ссылки с витрины и могли быть
     * заняты, пока пользователь листал каталог.
     */
    public function isAvailable(Yacht $yacht, CarbonImmutable $from, CarbonImmutable $to): bool
    {
        return Yacht::query()
            ->whereKey($yacht->getKey())
            ->availableForRent($from->toDateString(), $to->toDateString())
            ->exists();
    }

    /**
     * Расчёт стоимости периода по обеим ценам предложения.
     *
     * Цену проставляют не все владельцы: часть яхт сдаётся «по запросу», у них
     * обе цены остаются null, а итог не показывается.
     *
     * @return array{days: int, event: ?float, pro: ?float, event_total: ?float, pro_total: ?float}
     */
    public function quote(Yacht $yacht, ?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        $days = $from !== null && $to !== null ? $this->days($from, $to) : 0;

        $rentals = $yacht->rentals->filter(
            fn ($rental): bool => $this->covers($rental->date_start, $rental->date_end, $from, $to),
        );

        $event = $this->minPrice($rentals, 'price_event');
        $pro = $this->minPrice($rentals, 'price_pro');

        return [
            'days' => $days,
            'event' => $event,
            'pro' => $pro,
            'event_total' => $event !== null && $days > 0 ? $event * $days : null,
            'pro_total' => $pro !== null && $days > 0 ? $pro * $days : null,
        ];
    }

    /**
     * Данные для календаря на странице яхты.
     *
     * `windows` — периоды, объявленные владельцем (в календаре зелёные),
     * `busy` — отдельные занятые дни: одобренные брони и регаты. Регаты в
     * календаре каталога /yachts не учитывались, хотя подбор флота их
     * исключает; здесь занятость считается по тем же правилам, что и поиск.
     *
     * @return array{
     *     windows: list<array{start: string, end: string, price_event: ?float, price_pro: ?float}>,
     *     busy: list<string>,
     * }
     */
    public function calendar(Yacht $yacht): array
    {
        $windows = $yacht->rentals
            ->filter(fn ($rental): bool => $rental->date_start !== null && $rental->date_end !== null)
            ->sortBy('date_start')
            ->map(fn ($rental): array => [
                'start' => $rental->date_start->toDateString(),
                'end' => $rental->date_end->toDateString(),
                'price_event' => $rental->price_event !== null ? (float) $rental->price_event : null,
                'price_pro' => $rental->price_pro !== null ? (float) $rental->price_pro : null,
            ])
            ->values()
            ->all();

        return [
            'windows' => $windows,
            'busy' => $this->busyDates($yacht),
        ];
    }

    /**
     * Регионы базирования арендного флота — для выпадающего списка фильтра.
     *
     * @return list<string>
     */
    public function regions(): array
    {
        return $this->facet('home_region');
    }

    /**
     * Классы яхт, представленные в аренде.
     *
     * @return list<string>
     */
    public function classes(): array
    {
        return $this->facet('class');
    }

    /**
     * Значения «для чего подходит яхта»: справочника у них нет, поэтому список
     * собирается из самих яхт.
     *
     * @return list<string>
     */
    public function purposes(): array
    {
        return $this->rentableQuery()
            ->whereNotNull('suitable_for')
            ->pluck('suitable_for')
            ->flatten()
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => trim($value))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /** @return array<string, string> */
    public static function sortOptions(): array
    {
        return [
            'name' => 'По названию',
            'price_asc' => 'Сначала дешевле',
            'price_desc' => 'Сначала дороже',
            'year_desc' => 'Сначала новее',
        ];
    }

    // ──────────────────────────────────────────────
    // Выборка
    // ──────────────────────────────────────────────

    /** @return Builder<Yacht> */
    private function baseQuery(): Builder
    {
        return $this->rentableQuery()->with(['media', 'rentals', 'user']);
    }

    /**
     * Арендный флот: OwnedScope не снимаем — яхту без владельца сдавать некому.
     *
     * @return Builder<Yacht>
     */
    private function rentableQuery(): Builder
    {
        return Yacht::query()
            ->where('for_rent', true)
            ->where('approval_status', 'approved');
    }

    /**
     * @param  Builder<Yacht>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters, ?CarbonImmutable $from, ?CarbonImmutable $to): void
    {
        $search = trim((string) ($filters['q'] ?? ''));

        $query->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
            ->where('name', 'like', '%'.$search.'%')
            ->orWhere('vfps_number', 'like', '%'.$search.'%')
            ->orWhere('project', 'like', '%'.$search.'%')));

        $query
            ->when(filled($filters['region'] ?? null), fn (Builder $q) => $q->where('home_region', $filters['region']))
            ->when(filled($filters['yacht_class'] ?? null), fn (Builder $q) => $q->where('class', $filters['yacht_class']))
            // suitable_for — json-массив свободных значений, справочника у него нет.
            ->when(filled($filters['purpose'] ?? null), fn (Builder $q) => $q->whereJsonContains('suitable_for', $filters['purpose']));

        // Цена фильтруется по предложениям, покрывающим период: у яхты может
        // быть несколько диапазонов аренды с разной стоимостью дня.
        $priceFrom = $filters['price_from'] ?? null;
        $priceTo = $filters['price_to'] ?? null;

        if (! is_numeric($priceFrom) && ! is_numeric($priceTo)) {
            return;
        }

        $query->whereHas('rentals', function (Builder $rentals) use ($from, $to, $priceFrom, $priceTo): void {
            $this->coveringRentals($rentals, $from, $to);

            $rentals
                ->whereNotNull('price_event')
                ->when(is_numeric($priceFrom), fn (Builder $q) => $q->where('price_event', '>=', (float) $priceFrom))
                ->when(is_numeric($priceTo), fn (Builder $q) => $q->where('price_event', '<=', (float) $priceTo));
        });
    }

    /**
     * Предложения аренды, покрывающие весь запрошенный период.
     *
     * Без дат условие не накладывается: цена берётся по всем предложениям
     * яхты — это и есть «от» на карточке.
     *
     * @param  Builder<YachtRental>  $rentals
     * @return Builder<YachtRental>
     */
    private function coveringRentals(Builder $rentals, ?CarbonImmutable $from, ?CarbonImmutable $to): Builder
    {
        if ($from === null || $to === null) {
            return $rentals;
        }

        return $rentals
            ->whereDate('date_start', '<=', $from->toDateString())
            ->whereDate('date_end', '>=', $to->toDateString());
    }

    /** @param  Builder<Yacht>  $query */
    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            // Яхты «по запросу» (без цены) в ценовых сортировках уводим вниз,
            // иначе они занимают верх выдачи как самые дешёвые.
            'price_asc' => $query->orderByRaw('price_per_day is null')->orderBy('price_per_day'),
            'price_desc' => $query->orderByRaw('price_per_day is null')->orderByDesc('price_per_day'),
            'year_desc' => $query->orderByRaw('year is null')->orderByDesc('year')->orderBy('name'),
            default => $query->orderBy('name'),
        };
    }

    /**
     * Значения колонки, реально встречающиеся в арендном флоте.
     *
     * @return list<string>
     */
    private function facet(string $column): array
    {
        return $this->rentableQuery()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->all();
    }

    // ──────────────────────────────────────────────
    // Даты и цены
    // ──────────────────────────────────────────────

    /**
     * Занятые дни: одобренные брони и регаты, развёрнутые в отдельные даты.
     *
     * @return list<string>
     */
    private function busyDates(Yacht $yacht): array
    {
        $booked = $yacht->rentalRequests()
            ->where('status', RentalRequestStatus::Approved)
            ->whereNotNull('desired_date')
            ->get(['desired_date', 'desired_date_end'])
            ->flatMap(fn ($request): array => $this->expand(
                $request->desired_date,
                $request->desired_date_end ?? $request->desired_date,
            ));

        $regattas = $yacht->regattaEntries()
            ->where('status', 'approved')
            ->with('regatta:id,date_start,date_end')
            ->get()
            ->flatMap(fn ($entry): array => $entry->regatta === null
                ? []
                : $this->expand($entry->regatta->date_start, $entry->regatta->date_end));

        return $booked
            ->merge($regattas)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Период в список дат. Перевёрнутый диапазон (конец раньше начала) —
     * не повод ронять страницу: считаем его одним днём.
     *
     * @return list<string>
     */
    private function expand(mixed $start, mixed $end): array
    {
        if ($start === null) {
            return [];
        }

        $from = CarbonImmutable::parse($start)->startOfDay();
        $to = $end === null ? $from : CarbonImmutable::parse($end)->startOfDay();

        if ($to->lt($from)) {
            $to = $from;
        }

        return collect(CarbonPeriod::create($from, $to))
            ->map(fn ($date): string => $date->format('Y-m-d'))
            ->all();
    }

    /**
     * Покрывает ли предложение аренды весь период.
     *
     * Без дат покрытием считается любое предложение: цена «от» на карточке
     * считается по всему календарю яхты.
     */
    private function covers(mixed $start, mixed $end, ?CarbonImmutable $from, ?CarbonImmutable $to): bool
    {
        if ($start === null || $end === null) {
            return false;
        }

        if ($from === null || $to === null) {
            return true;
        }

        return CarbonImmutable::parse($start)->lte($from) && CarbonImmutable::parse($end)->gte($to);
    }

    /**
     * @param  Collection<int, YachtRental>  $rentals
     */
    private function minPrice($rentals, string $column): ?float
    {
        $prices = $rentals
            ->map(fn ($rental): ?float => $rental->{$column} !== null ? (float) $rental->{$column} : null)
            ->filter(fn (?float $price): bool => $price !== null && $price > 0);

        return $prices->isEmpty() ? null : (float) $prices->min();
    }

    /** Аренда считается по суткам: один и тот же день — это один день. */
    private function days(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) $from->diffInDays($to) + 1;
    }

    /**
     * Разбор пары дат из ссылки: конец раньше начала подтягивается к началу,
     * мусор трактуется как «даты не выбраны». Публичный, потому что страница
     * яхты принимает те же параметры, что и витрина.
     *
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    public function parseRange(mixed $from, mixed $to): array
    {
        $start = $this->parseDate($from);

        if ($start === null) {
            return [null, null];
        }

        $end = $this->parseDate($to) ?? $start;

        return [$start, $end->lt($start) ? $start : $end];
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        $value = trim((string) (is_scalar($value) ? $value : ''));

        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (\Exception) {
            return null;
        }
    }
}
