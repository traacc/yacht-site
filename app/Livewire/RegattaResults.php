<?php

namespace App\Livewire;

use App\Models\Regatta;
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
        $this->mode       = $mode;
        $this->regattaId  = $regattaId;
        $this->yearFilter = $year;
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
     * Формирует массив участников команды для передачи в модальное окно.
     */
    public function buildMembersPayload(\App\Models\Team $team): array
    {
        return $team->activeMembers
            ?->map(fn ($m) => [
                'name'     => $m->full_name ?? '',
                'birthday' => $m->birth_date?->format('d.m.Y') ?? '—',
                'rank'     => $m->sport_category ?? '—',
            ])
            ->values()
            ->toArray() ?? [];
    }

    /**
     * Eager-loads, необходимые для отображения результатов.
     */
    protected function eagerLoads(): array
    {
        return [
            'results.items'                       => fn ($q) => $q->orderBy('final_position'),
            'results.items.team.organizer',
            'results.items.team.activeMembers',
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

        return compact('regatta', 'resultItems');
    }

    private function renderList(): array
    {
        $regattas = $this->resolveRegattas()
            ->each(fn ($r) => $r->setRelation(
                'resultItems',
                $r->results->flatMap->items
            ));

        $availableYears = $this->availableYears();

        return compact('regattas', 'availableYears');
    }
}
