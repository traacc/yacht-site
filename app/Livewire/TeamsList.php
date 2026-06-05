<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Team;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithPagination;

class TeamsList extends Component
{
    use WithPagination;

    protected $queryString = ['search', 'sort'];

    public string $search = '';

    public string $sort = 'name';

    public string $view = 'list';

    public int $perPage = 12;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function setView(string $view): void
    {
        $this->view = $view;
    }

    public function render()
    {
        $teams = Team::with([
            'organizer',
            'activeMembers',
            'regattaEntries.regatta',
            'regattaEntries.yacht',
            'regattaResultItems.regattaResult',
            'ratings.season',
        ])
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->sort === 'name', fn ($q) => $q->orderBy('name'))
            ->when($this->sort === 'rating', fn ($q) => $q->orderByRaw(
                '(SELECT COALESCE(r.rank_position, 999999) FROM ratings r WHERE r.team_id = teams.id AND r.rating_type = \'team\' ORDER BY r.season_id DESC LIMIT 1) ASC'
            ))
            ->when($this->sort === 'newest', fn ($q) => $q->orderByDesc('created_at'))
            ->orderBy('name') // fallback
            ->paginate($this->perPage);

        $teamsJson = $teams->map(fn (Team $team) => [
            'id' => $team->id,
            'external_id' => $team->getFormattedExternalIdAttribute(),
            'name' => $team->name,
            'description' => $team->description ?? '',
            'photo' => $team->picture ? Storage::url($team->picture) : asset('images/news/news_1.webp'),
            'created_at' => $team->created_at?->format('d.m.Y') ?? '—',
            'status' => $team->is_archived ? 'Неактивная' : 'Активная',
            'status_class' => $team->is_archived ? 'inactive' : 'active',
            'captain' => $team->organizer?->name ?? '—',
            'rating' => $team->ratings->where('rating_type', 'team')->sortByDesc(fn ($r) => $r->season?->year ?? 0)->first()?->rank_position ?? '—',
            'participation_count' => $team->regattaEntries->count(),
            'members' => $team->activeMembers->map(fn ($m) => [
                'name' => $m->name,
                'birthday' => $m->birth_date?->format('d.m.Y') ?? '',
                'category' => $m->sport_category?->getLabel() ?? '',
            ])->values()->toArray(),
            'years' => $team->regattaEntries
                ->pluck('regatta.date_start')
                ->filter()
                ->map->year
                ->unique()
                ->sortDesc()
                ->values()
                ->toArray(),
            'participation' => $team->regattaEntries->map(fn ($entry) => [
                'regatta' => $entry->regatta?->name ?? '—',
                'yacht' => $entry->yacht?->name ?? '—',
                'date_event' => $entry->regatta?->dateRange() ?? '—',
                'date_registration' => $entry->submitted_at?->format('d.m.Y') ?? '—',
                'year' => $entry->regatta?->date_start?->year,
                'status' => $entry->status,
                'place' => $team->regattaResultItems
                    ->firstWhere('regattaResult.regatta_id', $entry->regatta_id)
                    ?->final_position ?? null,
            ])->values()->toArray(),
            'gallery' => $team->getMedia('gallery')->map(fn ($media) => $media->getUrl())->values()->toArray(),
        ])->values();

        return view('livewire.teams-list', [
            'teams' => $teams,
            'teamsJson' => $teamsJson,
        ]);
    }
}
