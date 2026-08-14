<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ParticipationKind;
use App\Enums\ParticipationOption;
use App\Enums\RegattaType;
use App\Models\ForeignRegatta;
use App\Models\Regatta;
use App\Models\RegattaEntry;
use App\Models\Yacht;
use Illuminate\Support\Collection;

/**
 * Что доступно человеку, который хочет участвовать: подбор регат, лодок и мест
 * для мастера «Хочу участвовать» (@see App\Livewire\ParticipationWizard).
 *
 * Живёт в сервисе, а не в Livewire-компоненте: компонент отвечает за шаги
 * и переходы, а «где есть свободные лодки и места» — предметный вопрос.
 */
final class ParticipationOptions
{
    /** Источник строки списка: своя регата или зарубежная из раздела «Услуги». */
    public const SOURCE_REGATTA = 'regatta';

    public const SOURCE_FOREIGN = 'foreign';

    /**
     * Регаты, куда можно заявиться выбранным способом.
     *
     * Клубные с экипажем показываем все открытые: даже без свободных лодок
     * в аренду остаётся вариант «своя лодка». Регулярные отбираются по наличию
     * того, что в них покупают, — мест или лодки. В выездные попадают и наши
     * регаты на площадках партнёров, и зарубежные регаты раздела «Услуги»
     * (@see foreignRegattas()): для участника это один и тот же вопрос — лодка
     * целиком или место в экипаже.
     *
     * @return Collection<int, array{
     *     id: string, source: string, name: string, dates: string, location: ?string,
     *     type: string, type_label: string, background_class: string,
     *     yachts_count: int, crews_count: int,
     *     seat_price: ?float, boat_price: ?float, crew_limit: ?int, url: string
     * }>
     */
    public function regattas(ParticipationKind $kind, RegattaType $type): Collection
    {
        $own = $this->openRegattas($type)
            ->map(fn (Regatta $regatta): array => $this->describe($regatta, $kind))
            ->filter(fn (array $item): bool => $this->isOffered($item, $kind, $type));

        if ($type !== RegattaType::Travel) {
            return $own->values();
        }

        return $own
            ->concat($this->foreignRegattas($kind))
            ->sortBy('sort_date')
            ->values();
    }

    /**
     * Зарубежные регаты раздела «Услуги» — они же выездные для участника.
     *
     * Заявка на них живёт своим порядком (ServiceRequest с вариантами участия
     * и ценами за место или каюту), поэтому мастер только доводит человека до
     * карточки регаты — там форма со всеми её опциями.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function foreignRegattas(ParticipationKind $kind): Collection
    {
        return ForeignRegatta::query()
            ->published()
            ->upcoming()
            ->ordered()
            ->get()
            ->filter(fn (ForeignRegatta $regatta): bool => $this->foreignOffers($regatta, $kind))
            ->map(fn (ForeignRegatta $regatta): array => [
                'id' => (string) $regatta->id,
                'source' => self::SOURCE_FOREIGN,
                'name' => $regatta->title,
                'dates' => $regatta->dateRange(),
                'sort_date' => $regatta->date_start->toDateString(),
                'location' => $regatta->placeLabel(),
                'type' => SeasonCalendar::FOREIGN_TYPE,
                'type_label' => 'За рубежом',
                'background_class' => 'bg-[#7B5FC4]',
                'yachts_count' => 0,
                'crews_count' => 0,
                'seat_price' => $regatta->price_per_seat !== null ? (float) $regatta->price_per_seat : null,
                'boat_price' => null,
                'crew_limit' => null,
                'url' => $regatta->publicUrl(),
            ])
            ->values();
    }

    /**
     * Подходит ли зарубежная регата выбранному способу участия.
     *
     * Варианты объявляет сама регата: место и каюта — это индивидуальное
     * участие, яхта целиком — экипажем. Если организатор вариантов не указал,
     * регату показываем в обоих случаях: детали выяснятся в заявке.
     */
    private function foreignOffers(ForeignRegatta $regatta, ParticipationKind $kind): bool
    {
        $options = $regatta->participationOptions();

        if ($options === []) {
            return true;
        }

        // Сравниваем сами enum-случаи: array_intersect привёл бы их к строке.
        return $kind === ParticipationKind::Crew
            ? in_array(ParticipationOption::Yacht, $options, true)
            : in_array(ParticipationOption::Seat, $options, true)
                || in_array(ParticipationOption::Cabin, $options, true);
    }

    /**
     * Есть ли вообще открытые регаты этого типа — независимо от того, осталось
     * ли в них место для конкретного человека.
     *
     * Нужно, чтобы отличить «таких регат сейчас нет» от «регаты есть, но мест
     * в них не осталось»: подсказки на шаге выбора у этих случаев разные.
     */
    public function hasAnyRegattas(RegattaType $type): bool
    {
        if ($this->openRegattas($type)->isNotEmpty()) {
            return true;
        }

        return $type === RegattaType::Travel
            && ForeignRegatta::query()->published()->upcoming()->exists();
    }

    /**
     * Свободные лодки на даты регаты: объявленные в аренду, без пересечений
     * с бронями и другими регатами (@see Yacht::scopeAvailableForRent()).
     *
     * @return Collection<int, Yacht>
     */
    public function availableYachts(Regatta $regatta): Collection
    {
        if (! $regatta->date_start || ! $regatta->date_end) {
            return collect();
        }

        return Yacht::query()
            ->where('for_rent', true)
            ->where('approval_status', 'approved')
            ->availableForRent(
                $regatta->date_start->format('Y-m-d'),
                $regatta->date_end->format('Y-m-d'),
            )
            ->orderBy('name')
            ->get();
    }

    /**
     * Экипажи клубной регаты, открытые для добора людей со стороны.
     *
     * @return Collection<int, RegattaEntry>
     */
    public function openCrews(Regatta $regatta): Collection
    {
        return $regatta->entries()
            ->where('open_for_join', true)
            ->whereIn('status', ['pending', 'approved'])
            ->with(['team', 'crew.teamMember.user', 'crew.user', 'yacht'])
            ->get();
    }

    /** @return Collection<int, Regatta> */
    private function openRegattas(RegattaType $type): Collection
    {
        return Regatta::query()
            ->ofType($type)
            ->whereNotIn('regatta_status', ['finished', 'cancelled', 'postponed'])
            ->orderBy('date_start')
            ->get();
    }

    /** @return array<string, mixed> */
    private function describe(Regatta $regatta, ParticipationKind $kind): array
    {
        return [
            'id' => $regatta->id,
            'source' => self::SOURCE_REGATTA,
            'name' => $regatta->name,
            'dates' => $regatta->dateRange(),
            'sort_date' => $regatta->date_start?->toDateString() ?? '',
            'location' => $regatta->location,
            'type' => $regatta->type->value,
            'type_label' => $regatta->type->getLabel(),
            'background_class' => $regatta->type->backgroundClass(),
            // Лодки считаем только там, где их предлагают выбирать. На выездных
            // флот даёт принимающая сторона — наши арендные лодки там не при чём.
            'yachts_count' => $kind === ParticipationKind::Crew && $regatta->type !== RegattaType::Travel
                ? $this->availableYachts($regatta)->count()
                : 0,
            'crews_count' => $kind === ParticipationKind::Individual && $regatta->type === RegattaType::Club
                ? $this->openCrews($regatta)->count()
                : 0,
            'seat_price' => $regatta->seat_price !== null ? (float) $regatta->seat_price : null,
            'boat_price' => $regatta->boat_price !== null ? (float) $regatta->boat_price : null,
            'crew_limit' => $regatta->maxCrewSize(),
            'url' => route('competition-details', $regatta),
        ];
    }

    /**
     * Предлагать ли регату в списке.
     *
     * Клубная с экипажем доступна всегда — своя лодка не зависит от аренды.
     * Индивидуальное участие в клубной возможно, только если кто-то из экипажей
     * объявил добор; в регулярной — если места вообще продаются. Выездные
     * доступны всегда: лодку и места там даёт партнёр, а цену организатор
     * нередко называет уже в переписке.
     *
     * @param  array<string, mixed>  $item
     */
    private function isOffered(array $item, ParticipationKind $kind, RegattaType $type): bool
    {
        if ($type === RegattaType::Travel) {
            return true;
        }

        if ($type === RegattaType::Club) {
            return $kind === ParticipationKind::Crew || $item['crews_count'] > 0;
        }

        return $kind === ParticipationKind::Crew
            ? $item['yachts_count'] > 0 || $item['boat_price'] !== null
            : $item['seat_price'] !== null;
    }
}
