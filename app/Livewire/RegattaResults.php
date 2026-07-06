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

    /** Данные активной строки для модального окна результатов по гонкам (null = закрыто) */
    public ?array $activeRacesModal = null;

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

    /**
     * Открывает модальное окно с результатами команды по каждой гонке.
     * Вызывается из шаблона через wire:click.
     *
     * @param  string       $teamName  Название команды
     * @param  string|null  $yachtName Название яхты (для подзаголовка)
     * @param  float|string $total     Итоговые очки
     * @param  array        $races     Массив гонок [['num'=>..,'name'=>..,'pos'=>..,'pts'=>..], ...]
     */
    public function openRacesModal(string $teamName, ?string $yachtName, float|string|null $total, array $races): void
    {
        $this->activeRacesModal = [
            'team_name'  => $teamName,
            'yacht_name' => $yachtName,
            'total'      => $total,
            'races'      => $races,
        ];
    }

    /** Закрывает модальное окно результатов по гонкам. */
    public function closeRacesModal(): void
    {
        $this->activeRacesModal = null;
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
            ->where('status', 'approved')
            ->with(['crew.teamMember.user'])
            ->get();

        $crewMap = [];
        foreach ($entries as $entry) {
            $crewList = $entry->crew->map(fn ($c) => [
                'id'       => $c->teamMember->user->id ?? null,
                'name'     => $c->teamMember->user->name ?? '',
                'birthday' => $c->teamMember->user->birth_date?->format('d.m.Y') ?? '—',
                'rank'     => $c->teamMember->user->sport_category?->getLabel() ?? '—',
                'avatar'   => $c->teamMember->user->photo_url ? asset('storage/'.$c->teamMember->user->photo_url) : null,
                'role'     => $c->role,
            ])->toArray();

            // Если у команды несколько заявок (разные яхты) — объединяем экипаж
            $crewMap[$entry->team_id] = array_merge(
                $crewMap[$entry->team_id] ?? [],
                $crewList
            );
        }

        // Сортировка: капитан всегда сверху, остальные — по алфавиту (ФИО)
        foreach ($crewMap as $teamId => $members) {
            usort($members, function ($a, $b) {
                $aCaptain = ($a['role'] ?? null) === 'captain';
                $bCaptain = ($b['role'] ?? null) === 'captain';

                if ($aCaptain !== $bCaptain) {
                    return $aCaptain ? -1 : 1;
                }

                return strcoll($a['name'], $b['name']);
            });

            $crewMap[$teamId] = $members;
        }

        return $crewMap;
    }

    /**
     * Строит карту результатов по гонкам: team_id → массив гонок с местом и очками.
     * Порядок гонок соответствует их проведению (event_datetime), нумерация 1..N.
     * Логика сбора повторяет GenerateRegattaResultPdfAction: связь через заявку
     * команды (RegattaEntry) по team_id, приоритет у одобренной заявки.
     *
     * @return array<string, array<int, array{num:int, name:string, pos:string, pts:float|string|null}>>
     */
    protected function buildRacesMap(Regatta $regatta, Collection $resultItems): array
    {
        $teamIds = $resultItems->pluck('team_id')->filter()->unique()->values()->toArray();

        if (empty($teamIds)) {
            return [];
        }

        // Гонки регаты → порядковые номера 1..N
        $raceEvents = $regatta->races()->get();

        if ($raceEvents->isEmpty()) {
            return [];
        }

        $raceMeta = [];
        foreach ($raceEvents->values() as $i => $event) {
            $raceMeta[$event->id] = [
                'num'  => $i + 1,
                'name' => $event->name ?: ('Гонка ' . ($i + 1)),
            ];
        }

        // Заявки регаты, индексированные по команде (приоритет — approved).
        $entriesByTeam = RegattaEntry::query()
            ->where('regatta_id', $regatta->id)
            ->whereIn('team_id', $teamIds)
            ->with('raceResults')
            ->orderByRaw("status = 'approved' ASC")
            ->get()
            ->keyBy('team_id');

        $racesMap = [];
        foreach ($teamIds as $teamId) {
            $entry = $entriesByTeam->get($teamId);

            if (! $entry) {
                continue;
            }

            $resultsByEvent = $entry->raceResults->keyBy('event_id');

            $races  = [];
            $hasAny = false;

            foreach ($raceMeta as $eventId => $meta) {
                $rr = $resultsByEvent->get($eventId);

                if ($rr) {
                    $hasAny = true;
                }

                if ($rr && $rr->penalty_code) {
                    $pos = mb_strtoupper($rr->penalty_code);
                } else {
                    $pos = $rr && $rr->position !== null ? (string) $rr->position : '—';
                }

                $races[] = [
                    'num'  => $meta['num'],
                    'name' => $meta['name'],
                    'pos'  => $pos,
                    'pts'  => $rr && $rr->points !== null ? $rr->points : null,
                ];
            }

            // Показываем только команды, у которых есть хотя бы один результат гонки.
            if ($hasAny) {
                $racesMap[$teamId] = $races;
            }
        }

        return $racesMap;
    }

    /**
     * Строит карту капитанов: team_id → ['id' => ?string, 'name' => ?string]
     * (участник экипажа с ролью 'captain').
     *
     * @param  array<string, array<int, array{id: ?string, name: string, role: ?string}>>  $crewMap
     * @return array<string, array{id: ?string, name: ?string}>
     */
    protected function buildCaptainMap(array $crewMap): array
    {
        $captainMap = [];
        foreach ($crewMap as $teamId => $members) {
            foreach ($members as $member) {
                if (($member['role'] ?? null) === 'captain') {
                    $captainMap[$teamId] = [
                        'id'   => $member['id'] ?? null,
                        'name' => $member['name'] ?: null,
                    ];
                    break;
                }
            }
        }

        return $captainMap;
    }

    /**
     * Eager-loads, необходимые для отображения результатов.
     */
    protected function eagerLoads(): array
    {
        return [
            'results'                             => fn ($q) => $q->where('is_published', true),
            'results.items'                       => fn ($q) => $q->reorder()
                ->orderByRaw('final_position IS NULL')
                ->orderByRaw('CAST(final_position AS UNSIGNED)')
                ->orderBy('final_position'),
            'results.items.team',
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

        $crewMap    = $regatta ? $this->buildCrewMap($regatta, $resultItems) : [];
        $captainMap = $this->buildCaptainMap($crewMap);
        $racesMap   = $regatta ? $this->buildRacesMap($regatta, $resultItems) : [];

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

        return compact('regatta', 'resultItems', 'topTeams', 'topParticipants', 'crewMap', 'captainMap', 'racesMap');
    }

    private function renderList(): array
    {
        $crewMaps    = [];
        $captainMaps = [];
        $racesMaps   = [];
        $regattas    = $this->resolveRegattas();

        $regattas->each(function ($r) use (&$crewMaps, &$captainMaps, &$racesMaps) {
            $resultItems = $r->results->flatMap->items ?? collect();
            $r->setRelation('resultItems', $resultItems);
            $crewMaps[$r->id]    = $this->buildCrewMap($r, $resultItems);
            $captainMaps[$r->id] = $this->buildCaptainMap($crewMaps[$r->id]);
            $racesMaps[$r->id]   = $this->buildRacesMap($r, $resultItems);
        });

        $availableYears = $this->availableYears();

        return compact('regattas', 'availableYears', 'crewMaps', 'captainMaps', 'racesMaps');
    }
}
