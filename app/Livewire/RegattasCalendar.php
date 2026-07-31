<?php

namespace App\Livewire;

use App\Models\Season;
use App\Services\SeasonCalendar;
use Livewire\Attributes\Computed;
use Livewire\Component;

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
        // Ключ === значение года, чтобы custom-select передавал в wire:model
        // сам год, а не индекс элемента массива.
        return Season::query()
            ->orderByDesc('year')
            ->pluck('year')
            ->mapWithKeys(fn ($year) => [$year => (string) $year])
            ->toArray();
    }

    /**
     * Регаты сезона, сгруппированные по месяцам.
     *
     * Сборка событий — в App\Services\SeasonCalendar: в календарь попадают два
     * источника, регаты ассоциации и регаты за рубежом (ТЗ 3-го этапа, п. 7).
     */
    #[Computed]
    public function months(): array
    {
        return app(SeasonCalendar::class)->months($this->year);
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
