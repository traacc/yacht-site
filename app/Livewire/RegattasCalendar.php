<?php

namespace App\Livewire;

use App\Models\Regatta;
use App\Models\Season;
use Livewire\Component;
use Livewire\Attributes\Computed; // Важно для Livewire v3

class RegattasCalendar extends Component
{
    /** Выбранный год (сезон) */
    public ?int $year = null;

    /** Показывать ли селект выбора года */
    public bool $showSelector = true;

    public function mount(?int $year = null): void
    {
        // Если год передан явно — используем его, иначе текущий год
        $this->year = $year ?? (int) now()->format('Y');
    }

    /** Установить год и перезагрузить данные */
    public function setYear(?int $year): void
    {
        $this->year = $year;
        // В Livewire v3 свойства с #[Computed] сбросятся автоматически при изменении $this->year
    }

    /** Список доступных годов */
    #[Computed]
    public function years(): array
    {
        return Season::query()
            ->orderByDesc('year')
            ->pluck('year')
            ->toArray();
    }

    /** Возвращает регаты, сгруппированные по месяцам */
    #[Computed]
    public function months(): array
    {
        $monthNames = [
            1 => 'Январь', 2 => 'Февраль', 3 => 'Март',
            4 => 'Апрель', 5 => 'Май', 6 => 'Июнь',
            7 => 'Июль', 8 => 'Август', 9 => 'Сентябрь',
            10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь',
        ];

        $regattas = Regatta::query()
            ->when($this->year, fn ($q) => $q->whereHas('season', fn ($sq) => $sq->where('year', $this->year)))
            ->withCount(['documents' => fn ($q) => $q->whereNotNull('url')->where('url', '!=', '')])
            ->orderBy('date_start')
            ->get();

        // Группируем. Убедитесь, что date_start кастится к Carbon в модели!
        $grouped = $regattas->groupBy(fn (Regatta $r) => (int) \Carbon\Carbon::parse($r->date_start)->format('n'));

        $currentMonth = (int) now()->format('n');

        $months = [];
        foreach ($monthNames as $num => $name) {
            $months[] = [
                'name' => $name,
                'is_current' => $num === $currentMonth,
                'events' => $grouped->has($num)
                    ? $grouped->get($num)->map(fn (Regatta $r) => [
                        'id' => $r->id,
                        'date' => $r->dateRange(),
                        'title' => $r->name,
                        'city' => $r->location,
                        'status' => $r->regatta_status->value,
                        'url' => route('competition-details', $r),
                        'has_documents' => $r->documents_count > 0,
                        'documents_url' => route('regatta.documents.download', $r),
                        'can_join' => ! in_array($r->regatta_status, [
                            \App\Enums\RegattaStatus::Finished,
                            \App\Enums\RegattaStatus::Cancelled,
                        ], true),
                    ])->values()->toArray()
                    : [],
            ];
        }

        return $months;
    }

    public function render()
    {
        // В Livewire v3 к Computed-свойствам в render обращаются как к динамическим свойствам:
        return view('livewire.regattas-calendar', [
            'years' => $this->years,   // вызывает метод years()
            'months' => $this->months, // вызывает метод months()
        ]);
    }
}