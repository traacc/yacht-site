<?php

namespace App\Livewire;

use App\Models\Regatta;
use App\Models\RegattaEntry;
use App\Models\Team;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Support\Collection;
use Livewire\Component;

class RegattaResults extends Component
{
    /**
     * Режим отображения:
     *  - 'home'  — одна регата (последняя завершённая/активная), используется на главной
     *  - 'show'  — одна конкретная регата, используется на странице регаты
     *  - 'list'  — список регат с фильтрами, используется на странице результатов
     */
    public string $mode = 'home';

    /**
     * ID конкретной регаты (для режимов 'show' и 'home' с явным указанием).
     * Если null — компонент сам находит нужную регату.
     */
    public ?string $regattaId = null;

    // ──────────────────────────────────────────────
    // Фильтры (режим 'list')
    // ──────────────────────────────────────────────

    public string $yearFilter   = '';
    public string $statusFilter = '';

    // ──────────────────────────────────────────────
    // Состояние модального окна участников
    // ──────────────────────────────────────────────

    /** Данные активной команды для модального окна (null = закрыто) */
    public ?array $activeTeamModal = null;

    // ──────────────────────────────────────────────
    // Lifecycle
    // ──────────────────────────────────────────────

    public function mount(
        string  $mode      = 'home',
        ?string $regattaId = null,
        string  $year      = '',
        string  $status    = '',
    ): void {
        $this->mode         = $mode;
        $this->regattaId    = $regattaId;
        $this->yearFilter   = $year;
        $this->statusFilter = $status;
    }

    // ──────────────────────────────────────────────
    // Computed helpers
    // ──────────────────────────────────────────────

    /**
     * Возвращает одну регату (для режимов 'home' и 'show').
     */
    protected function resolveRegatta(): ?Regatta
    {
        if ($this->regattaId) {
            return Regatta::with($this->eagerLoads())
                ->find($this->regattaId);
        }

        // Режим 'home': последняя завершённая или активная регата
        return Regatta::with($this->eagerLoads())
            ->where(function ($query) {
                $query->where('date_end', '<', now())
                      ->orWhere(function ($q) {
                          $q->where('date_start', '<=', now())
                            ->where('date_end', '>=', now());
                      });
            })
            ->orderBy('date_end', 'desc')
            ->first();
    }

    /**
     * Возвращает коллекцию регат с фильтрами (для режима 'list').
     */
    protected function resolveRegattas(): Collection
    {
        $query = Regatta::with($this->eagerLoads())
            ->where(function ($q) {
                $q->where('date_end', '<', now())
                  ->orWhere(function ($inner) {
                      $inner->where('date_start', '<=', now())
                            ->where('date_end', '>=', now());
                  });
            });

        if ($this->yearFilter !== '') {
            $query->whereYear('date_start', $this->yearFilter);
        }

        if ($this->statusFilter === 'finished') {
            $query->where('date_end', '<', now());
        } elseif ($this->statusFilter === 'preliminary') {
            $query->where('date_start', '<=', now())
                  ->where('date_end', '>=', now());
        }

        return $query->orderBy('date_end', 'desc')->get();
    }

    /**
     * Список годов для фильтра (режим 'list').
     */
    protected function availableYears(): array
    {
        return Regatta::query()
            ->where(function ($q) {
                $q->where('date_end', '<', now())
                  ->orWhere(function ($inner) {
                      $inner->where('date_start', '<=', now())
                            ->where('date_end', '>=', now());
                  });
            })
            ->selectRaw('YEAR(date_start) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->toArray();
    }

    // ──────────────────────────────────────────────
    // Actions
    // ──────────────────────────────────────────────

    /**
     * Открывает модальное окно с составом команды.
     * Вызывается из шаблона через wire:click.
     *
     * @param  string  $teamId   UUID команды
     * @param  string  $teamName Название команды
     * @param  array   $members  Массив участников [['name'=>..., 'birthday'=>..., 'rank'=>...], ...]
     */
    public function openTeamModal(string $teamId, string $teamName, array $members): void
    {
        $this->activeTeamModal = [
            'team_id'   => $teamId,
            'team_name' => $teamName,
            'members'   => $members,
        ];
    }

    /** Закрывает модальное окно. */
    public function closeTeamModal(): void
    {
        $this->activeTeamModal = null;
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    /**
     * Строит карту экипажа: team_id → массив участников, заявленных на регату (через RegattaEntryCrew).
     */
    protected function buildCrewMap(Regatta $regatta, Collection $resultItems): array
    {
        $teamIds = $resultItems->pluck('team_id')->filter()->unique()->values()->toArray();

        if (empty($teamIds)) {
            return [];
        }

        $entries = RegattaEntry::where('regatta_id', $regatta->id)
            ->whereIn('team_id', $teamIds)
            ->with(['crew.teamMember.user'])
            ->get();

        $crewMap = [];
        foreach ($entries as $entry) {
            $crewList = $entry->crew->map(fn ($c) => [
                'name'     => $c->teamMember->user->name ?? '',
                'birthday' => $c->teamMember->user->birth_date?->format('d.m.Y') ?? '—',
                'rank'     => $c->teamMember->user->sport_category?->getLabel() ?? '—',
            ])->toArray();

            // Если у команды несколько заявок (разные яхты) — объединяем экипаж
            $crewMap[$entry->team_id] = array_merge(
                $crewMap[$entry->team_id] ?? [],
                $crewList
            );
        }

        return $crewMap;
    }

    /**
     * Eager-loads, необходимые для отображения результатов.
     */
    protected function eagerLoads(): array
    {
        return [
            'results.items'                       => fn ($q) => $q->orderBy('final_position'),
            'results.items.team.organizer',
            'results.items.yacht',
            'season',
        ];
    }

    // ──────────────────────────────────────────────
    // Render
    // ──────────────────────────────────────────────

    public function render()
    {
        $data = match ($this->mode) {
            'show', 'home' => $this->renderSingle(),
            'list'         => $this->renderList(),
            default        => $this->renderSingle(),
        };

        return view('livewire.regatta-results', $data);
    }

    private function renderSingle(): array
    {
        $regatta     = $this->resolveRegatta();
        $resultItems = $regatta?->results?->flatMap->items ?? collect();

        $crewMap = $regatta ? $this->buildCrewMap($regatta, $resultItems) : [];

        $topTeams        = collect();
        $topParticipants = collect();

        if ($this->mode === 'home') {
            $settings = app(SettingsService::class);

            $topTeamData = $settings->get('home.top_teams', []);
            $topTeams = collect($topTeamData)
                ->filter(fn ($item) => !empty($item['id']))
                ->map(fn ($item) => [
                    'model'  => \App\Models\Team::find($item['id']),
                    'points' => $item['points'] ?? null,
                ])
                ->filter(fn ($item) => $item['model'] !== null)
                ->values();

            $topParticipantData = $settings->get('home.top_participants', []);
            $topParticipants = collect($topParticipantData)
                ->filter(fn ($item) => !empty($item['id']))
                ->map(fn ($item) => [
                    'model'  => \App\Models\User::find($item['id']),
                    'points' => $item['points'] ?? null,
                ])
                ->filter(fn ($item) => $item['model'] !== null)
                ->values();
        }

        return compact('regatta', 'resultItems', 'topTeams', 'topParticipants', 'crewMap');
    }

    private function renderList(): array
    {
        $crewMaps = [];
        $regattas = $this->resolveRegattas();

        $regattas->each(function ($r) use (&$crewMaps) {
            $resultItems = $r->results->flatMap->items ?? collect();
            $r->setRelation('resultItems', $resultItems);
            $crewMaps[$r->id] = $this->buildCrewMap($r, $resultItems);
        });

        $availableYears = $this->availableYears();

        return compact('regattas', 'availableYears', 'crewMaps');
    }
}
