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

    public int $perPage = 250;

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
            'teamRatings.season',
        ])
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->sort === 'name', fn ($q) => $q
                ->orderByRaw("name REGEXP '^[А-Яа-яЁё]' DESC")
                ->orderBy('name'))
            ->when($this->sort === 'rating', fn ($q) => $q->orderByRaw(
                'COALESCE((SELECT r.rank_position FROM team_ratings r JOIN seasons s ON s.id = r.season_id WHERE r.team_id = teams.id ORDER BY s.year DESC LIMIT 1), -1) DESC'
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
            'captain_id' => $team->organizer?->id,
            'rating' => $team->teamRatings->sortByDesc(fn ($r) => $r->season?->year ?? 0)->first()?->rank_position ?? '—',
            'participation_count' => $team->regattaEntries->filter(fn ($e) => $e->regatta?->date_start?->isPast())->count(),
            'members' => $team->activeMembers->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'avatar' => $m->photo_url ? Storage::url($m->photo_url) : null,
                'birthday' => $m->birth_date?->format('d.m.Y') ?? '',
                'category' => $m->sport_category?->getLabel() ?? '',
            ])->values()->toArray(),
            'years' => $team->regattaEntries
                ->filter(fn ($e) => $e->regatta?->isFinished())
                ->pluck('regatta.date_start')
                ->filter()
                ->map->year
                ->unique()
                ->sortDesc()
                ->values()
                ->toArray(),
            'upcoming_entries' => $team->regattaEntries->filter(fn ($e) => $e->regatta && ! $e->regatta->isFinished())->map(fn ($entry) => [
                'regatta' => $entry->regatta?->name ?? '—',
                'yacht' => $entry->yacht?->name ?? '—',
                'date_event' => $entry->regatta?->dateRange() ?? '—',
                'status' => $entry->status,
            ])->values()->toArray(),
            'participation' => $team->regattaEntries->filter(fn ($e) => $e->regatta?->isFinished())->map(fn ($entry) => [
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
            'download_url' => route('team.history.pdf', $team),
            'can_edit' => auth()->check() && auth()->user()->can('editTeam', $team),
            'edit_url' => auth()->check() && auth()->user()->isAdmin() ? '/admin/teams' : '/user/teams',
        ])->values();

        return view('livewire.teams-list', [
            'teams' => $teams,
            'teamsJson' => $teamsJson,
        ]);
    }
}
