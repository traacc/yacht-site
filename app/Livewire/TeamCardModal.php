<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\SportCategory;
use App\Models\Team;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Глобальная карточка команды.
 *
 * Открывается из любого места событием 'open-team-card' с id команды:
 *   - в Livewire-шаблоне:  wire:click="$dispatch('open-team-card', { teamId: '...' })"
 *   - в обычном Alpine:    @click="Livewire.dispatch('open-team-card', { teamId: '...' })"
 *   - в обычном JS:        onclick="Livewire.dispatch('open-team-card', { teamId: '...' })"
 *
 * Регистрируется один раз в общем layout (resources/views/layouts/public.blade.php).
 * По аналогии с {@see UserCardModal}.
 */
class TeamCardModal extends Component
{
    public bool $isOpen = false;

    /** Подготовленные для отображения данные команды (null = окно закрыто). */
    public ?array $team = null;

    #[On('open-team-card')]
    public function open(string $teamId): void
    {
        $team = Team::with([
            'organizer',
            'activeMembers',
            'teamRatings.season',
            'regattaEntries.regatta',
        ])->find($teamId);

        if (! $team instanceof Team) {
            return;
        }

        $this->team = [
            'name' => $team->name,
            'photo' => $team->picture ? Storage::url($team->picture) : null,
            'description' => $team->description ?? '',
            'status' => $team->is_archived ? 'Неактивная' : 'Активная',
            'captain' => $team->organizer?->name,
            'captain_id' => $team->organizer?->id,
            'rating' => $team->teamRatings
                ->sortByDesc(fn ($r) => $r->season?->year ?? 0)
                ->first()?->rank_position ?? '—',
            'regattas' => $team->regattaEntries
                ->filter(fn ($e) => $e->regatta?->date_start?->isPast())
                ->count(),
            'members' => $team->activeMembers->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'avatar' => $m->photo_url ? Storage::url($m->photo_url) : null,
                'birthday' => $m->birth_date?->format('d.m.Y') ?? '—',
                'category' => SportCategory::labelOrNone($m->sport_category),
            ])->values()->toArray(),
        ];

        $this->isOpen = true;
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->team = null;
    }

    public function render()
    {
        return view('livewire.team-card-modal');
    }
}
