<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ForeignRegatta;
use App\Models\Regatta;
use Illuminate\Support\Collection;

/**
 * Календарь регат сезона, разложенный по месяцам.
 *
 * Один календарь на два источника: соревновательные регаты ассоциации
 * (`regattas`) и регаты за рубежом раздела «Услуги» (`foreign_regattas`) — по
 * ТЗ 3-го этапа (п. 7) зарубежные регаты «также попадают в общий календарь
 * регат сезона», и из календаря ведут в свою карточку.
 *
 * Живёт в сервисе, а не в Livewire-компоненте: компонент отвечает за выбор
 * года и рендер, данные собираются здесь.
 */
final class SeasonCalendar
{
    /** Статус-маркер зарубежных регат: отдельный цвет и пункт легенды. */
    public const FOREIGN_STATUS = 'foreign';

    private const MONTH_NAMES = [
        1 => 'Январь', 2 => 'Февраль', 3 => 'Март',
        4 => 'Апрель', 5 => 'Май', 6 => 'Июнь',
        7 => 'Июль', 8 => 'Август', 9 => 'Сентябрь',
        10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь',
    ];

    /**
     * Двенадцать месяцев года с событиями каждого.
     *
     * @return list<array{name: string, is_current: bool, events: list<array<string, mixed>>}>
     */
    public function months(?int $year = null): array
    {
        $events = $this->regattaEvents($year)
            ->concat($this->foreignRegattaEvents($year))
            ->sortBy('sort_date')
            ->groupBy('month');

        $currentMonth = (int) now()->format('n');

        $months = [];

        foreach (self::MONTH_NAMES as $number => $name) {
            $months[] = [
                'name' => $name,
                'is_current' => $number === $currentMonth,
                'events' => $events->has($number)
                    ? $events->get($number)->values()->all()
                    : [],
            ];
        }

        return $months;
    }

    /** @return Collection<int, array<string, mixed>> */
    private function regattaEvents(?int $year): Collection
    {
        return Regatta::query()
            ->when($year, fn ($query) => $query->whereHas('season', fn ($season) => $season->where('year', $year)))
            ->withCount(['documents' => fn ($query) => $query->whereNotNull('url')->where('url', '!=', '')])
            ->orderBy('date_start')
            ->get()
            ->map(fn (Regatta $regatta): array => [
                'month' => (int) $regatta->date_start->format('n'),
                'sort_date' => $regatta->date_start->toDateString(),
                'id' => $regatta->id,
                'external_id' => $regatta->external_id,
                'date' => $regatta->dateRange(),
                'title' => $regatta->name,
                'city' => $regatta->location,
                'status' => $regatta->regatta_status->value,
                'postponed_to' => $regatta->postponed_to_date?->isoFormat('LL'),
                'postponed_note' => $regatta->postponed_note,
                'url' => route('competition-details', $regatta),
                'has_documents' => $regatta->documents_count > 0,
                'documents_url' => route('regatta.documents.download', $regatta),
                'can_join' => $regatta->isOpenForRegistration(),
                'is_foreign' => false,
            ]);
    }

    /**
     * Зарубежные регаты.
     *
     * Заявок команд, документов и рейтинга у них нет, поэтому в календаре
     * остаётся только ссылка в карточку услуги.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function foreignRegattaEvents(?int $year): Collection
    {
        return ForeignRegatta::query()
            ->published()
            ->when($year, fn ($query) => $query->ofSeasonYear($year))
            ->orderBy('date_start')
            ->get()
            ->map(fn (ForeignRegatta $regatta): array => [
                'month' => (int) $regatta->date_start->format('n'),
                'sort_date' => $regatta->date_start->toDateString(),
                'id' => $regatta->id,
                'external_id' => null,
                'date' => $regatta->dateRange(),
                'title' => $regatta->title,
                'city' => $regatta->placeLabel(),
                'status' => self::FOREIGN_STATUS,
                'postponed_to' => null,
                'postponed_note' => null,
                'url' => $regatta->publicUrl(),
                'has_documents' => false,
                'documents_url' => null,
                'can_join' => false,
                'is_foreign' => true,
            ]);
    }
}
