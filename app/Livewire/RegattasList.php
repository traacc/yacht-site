<?php

namespace App\Livewire;

use App\Models\Regatta;
use App\Models\Season;
use Livewire\Component;
use Livewire\WithPagination;

class RegattasList extends Component
{
    use WithPagination;

    protected $queryString = ['view', 'search', 'filter', 'year'];

    public string $search = '';

    public string $filter = 'all';

    public string $sortField = 'date_start';

    public string $sortDirection = 'desc';

    public int $perPage = 10;

    public string $view = 'grid';

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
        $this->view = request()->query('view', session('regattas_view', 'grid'));
    }

    public function setView(string $view): void
    {
        $this->view = $view;
        session(['regattas_view' => $view]);
    }

    public function render()
    {
        $regattas = Regatta::query()
            ->when($this->year, fn ($q) => $q->whereHas('season', fn ($sq) => $sq->where('year', $this->year)
            )
            )
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
            )
            ->when($this->filter === 'upcoming', fn ($q) => $q->where('date_start', '>', now())->where('date_start', '<=', now()->addMonth()))
            ->when($this->filter === 'planned', fn ($q) => $q->where('date_start', '>', now()))
            ->when($this->filter === 'finished', fn ($q) => $q->where('date_end', '<', now()))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        return view('livewire.regattas-list', [
            'regattas' => $regattas,
            'years' => $this->years,
        ]);
    }
}
