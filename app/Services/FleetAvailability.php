<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Yacht;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Подбор яхт под «Аренду флота» (ТЗ 3-го этапа, п. 7).
 *
 * Выборка и разбор параметров живут здесь, а не в замыкании маршрута — как у
 * досок объявлений (@see AdvertBoard). Свободной считается яхта, у которой
 * весь запрошенный диапазон покрыт предложением аренды и не пересекается ни с
 * одобренной бронью, ни с регатой.
 *
 * Занятость считается по тем же данным, что и календарь на /yachts, поэтому
 * страницы не могут разойтись в ответе «свободна / занята».
 */
final class FleetAvailability
{
    /** Сколько яхт запрашивают по умолчанию, если параметр не передан. */
    public const DEFAULT_NEEDED = 2;

    /**
     * Весь арендный флот — показывается, пока даты не выбраны.
     *
     * @return Collection<int, Yacht>
     */
    public function fleet(): Collection
    {
        return $this->baseQuery()->get();
    }

    /**
     * Яхты, свободные на весь диапазон.
     *
     * @return Collection<int, Yacht>
     */
    public function availableYachts(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        // Условия «свободна на диапазон» лежат в скоупе модели: ими же
        // пользуется витрина бронирования (@see YachtBooking).
        return $this->baseQuery()
            ->availableForRent($from->toDateString(), $to->toDateString())
            ->get();
    }

    /**
     * Готовые данные для страницы подбора.
     *
     * Даты разбираются мягко: мусор в query-параметрах не должен ронять
     * страницу, он просто трактуется как «даты не выбраны».
     *
     * @return array{
     *     from: ?CarbonImmutable,
     *     to: ?CarbonImmutable,
     *     days: int,
     *     needed: int,
     *     searched: bool,
     *     yachts: Collection<int, Yacht>,
     *     available: int,
     *     enough: bool,
     *     price_from: ?float,
     *     estimate: ?float,
     * }
     */
    public function summary(?string $from, ?string $to, ?int $needed = null): array
    {
        $needed = max(1, min(50, $needed ?? self::DEFAULT_NEEDED));

        $start = $this->parseDate($from);
        $end = $this->parseDate($to) ?? $start;

        if ($start !== null && $end !== null && $end->lt($start)) {
            $end = $start;
        }

        $searched = $start !== null && $end !== null;

        $yachts = $searched
            ? $this->availableYachts($start, $end)
            : $this->fleet();

        // Аренда считается по суткам: один и тот же день — это один день.
        $days = $searched ? $start->diffInDays($end) + 1 : 0;

        $priceFrom = $searched ? $this->minDailyPrice($yachts, $start, $end) : null;

        return [
            'from' => $start,
            'to' => $searched ? $end : null,
            'days' => $days,
            'needed' => $needed,
            'searched' => $searched,
            'yachts' => $yachts,
            'available' => $yachts->count(),
            'enough' => $yachts->count() >= $needed,
            'price_from' => $priceFrom,
            'estimate' => $priceFrom !== null ? $priceFrom * $days * $needed : null,
        ];
    }

    /**
     * Минимальная цена за день среди предложений, покрывающих диапазон.
     *
     * Цену не проставляют все владельцы: у части яхт аренда «по запросу», и
     * такие в расчёт минимума не идут.
     *
     * @param  Collection<int, Yacht>  $yachts
     */
    private function minDailyPrice(Collection $yachts, CarbonImmutable $from, CarbonImmutable $to): ?float
    {
        $prices = $yachts
            ->flatMap(fn (Yacht $yacht) => $yacht->rentals
                ->filter(fn ($rental): bool => $rental->date_start !== null
                    && $rental->date_end !== null
                    && $rental->date_start->lte($from)
                    && $rental->date_end->gte($to)
                    && $rental->price_event !== null)
                ->map(fn ($rental): float => (float) $rental->price_event))
            ->filter(fn (float $price): bool => $price > 0);

        return $prices->isEmpty() ? null : (float) $prices->min();
    }

    /** @return Builder<Yacht> */
    private function baseQuery(): Builder
    {
        // OwnedScope не снимаем: яхту без владельца сдавать некому.
        return Yacht::query()
            ->where('for_rent', true)
            ->where('approval_status', 'approved')
            ->with(['media', 'rentals', 'user'])
            ->orderBy('name');
    }

    private function parseDate(?string $value): ?CarbonImmutable
    {
        $value = trim((string) $value);

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
