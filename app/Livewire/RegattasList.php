<?php

namespace App\Livewire;

use App\Enums\RegattaType;
use App\Models\Regatta;
use App\Models\Season;
use Livewire\Component;
use Livewire\WithPagination;

class RegattasList extends Component
{
    use WithPagination;

    protected $queryString = ['view', 'search', 'filter', 'year', 'type'];

    public string $search = '';

    public string $filter = 'all';

    /** Тип соревнования: club / regular / travel; null — все типы. */
    public ?string $type = null;

    public string $sortField = 'date_start';

    public string $sortDirection = 'asc';

    public int $perPage = 50;

    public string $view = 'list';

    /** Выбранный год для фильтрации; null — все годы */
    public ?int $year = null;

    /** Список доступных годов (из seasons) */
    public function getYearsProperty(): array
    {
        return Season::query()
            ->orderByDesc('year')
            ->pluck('year')
            ->toArray();
    }

    // Сброс пагинации при изменении фильтров
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingYear(): void
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

    /** Установить год фильтрации */
    public function setYear(?int $year): void
    {
        $this->year = $year;
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

    public function mount(): void
    {
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
            ->with(['series', 'series.regattas:id,series_id,date_start,time_start'])
            ->when($this->year, fn ($q) => $q->whereHas('season', fn ($sq) => $sq->where('year', $this->year)
            )
            )
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
            'years' => $this->years,
            'types' => RegattaType::cases(),
        ]);
    }
}
