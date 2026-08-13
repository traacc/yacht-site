<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ParticipationKind;
use App\Enums\RegattaType;
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
    /**
     * Регаты, куда можно заявиться выбранным способом.
     *
     * Клубные с экипажем показываем все открытые: даже без свободных лодок
     * в аренду остаётся вариант «своя лодка». Остальные ветки отбираются по
     * наличию того, что в них покупают, — мест или лодки.
     *
     * @return Collection<int, array{
     *     id: string, name: string, dates: string, location: ?string,
     *     type: string, type_label: string, background_class: string,
     *     yachts_count: int, crews_count: int,
     *     seat_price: ?float, boat_price: ?float, crew_limit: ?int, url: string
     * }>
     */
    public function regattas(ParticipationKind $kind, RegattaType $type): Collection
    {
        return $this->openRegattas($type)
            ->map(fn (Regatta $regatta): array => $this->describe($regatta, $kind))
            ->filter(fn (array $item): bool => $this->isOffered($item, $kind, $type))
            ->values();
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
            'name' => $regatta->name,
            'dates' => $regatta->dateRange(),
            'location' => $regatta->location,
            'type' => $regatta->type->value,
            'type_label' => $regatta->type->getLabel(),
            'background_class' => $regatta->type->backgroundClass(),
            // Лодки считаем только там, где их предлагают выбирать.
            'yachts_count' => $kind === ParticipationKind::Crew
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
     * объявил добор; в регулярной — если места вообще продаются.
     *
     * @param  array<string, mixed>  $item
     */
    private function isOffered(array $item, ParticipationKind $kind, RegattaType $type): bool
    {
        if ($type === RegattaType::Club) {
            return $kind === ParticipationKind::Crew || $item['crews_count'] > 0;
        }

        return $kind === ParticipationKind::Crew
            ? $item['yachts_count'] > 0 || $item['boat_price'] !== null
            : $item['seat_price'] !== null;
    }
}
