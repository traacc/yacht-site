<?php

namespace App\Livewire;

use App\Enums\RegattaStatus;
use App\Enums\RegattaType;
use App\Models\Regatta;
use App\Services\SeasonContext;
use Livewire\Component;
use Livewire\WithPagination;

class RegattasList extends Component
{
    use WithPagination;

    // Год в адресе держит страница (?season=), компонент получает его в mount().
    protected $queryString = ['view', 'search', 'filter', 'type'];

    public string $search = '';

    public string $filter = 'all';

    /** Тип соревнования: club / regular / travel; null — все типы. */
    public ?string $type = null;

    public string $sortField = 'date_start';

    public string $sortDirection = 'asc';

    public int $perPage = 50;

    public string $view = 'list';

    /** Год сезона: регаты разных сезонов в одном списке не показываем. */
    public ?int $year = null;

    // Сброс пагинации при изменении фильтров
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->resetPage();
    }

    /** Фильтр по типу соревнования; повторный клик по активному типу его снимает. */
    public function setType(?string $type): void
    {
        $this->type = $this->type === $type ? null : $type;
        $this->resetPage();
    }

    public function sort(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function mount(?int $year = null): void
    {
        $this->year = $year ?? app(SeasonContext::class)->current()?->year;
        $this->view = request()->query('view', session('regattas_view', 'list'));
    }

    public function setView(string $view): void
    {
        $this->view = $view;
        session(['regattas_view' => $view]);
    }

    public function render()
    {
        $regattas = Regatta::query()
            // Снимки статуса «Перенесена» — это не отдельные события, а история
            // изменения даты «живой» регаты (см. RegattaService::postpone()).
            // Без исключения они дублируют карточку в списке под старой датой.
            // Факт переноса показывается на карточке живой регаты, см. блейд.
            ->where('regatta_status', '!=', RegattaStatus::Postponed)
            ->with(['series', 'series.regattas:id,series_id,date_start,time_start', 'postponedFromRegattas'])
            ->whereHas('season', fn ($q) => $q->where('year', $this->year))
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
            )
            ->when($this->filter === 'upcoming', fn ($q) => $q->where('regatta_status', 'upcoming'))
            ->when($this->filter === 'closest', fn ($q) => $q->where('regatta_status', 'closest'))
            ->when($this->filter === 'finished', fn ($q) => $q->where('regatta_status', 'finished'))
            ->ofType($this->type)
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        return view('livewire.regattas-list', [
            'regattas' => $regattas,
            'types' => RegattaType::cases(),
        ]);
    }
}
