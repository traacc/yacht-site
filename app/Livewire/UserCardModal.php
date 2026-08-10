<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\SportCategory;
use App\Enums\TeamMemberRole;
use App\Models\PersonalRating;
use App\Models\RegattaEntry;
use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Глобальная карточка пользователя.
 *
 * Открывается из любого места событием 'open-user-card' с id пользователя:
 *   - в Livewire-шаблоне:  wire:click="$dispatch('open-user-card', { userId: '...' })"
 *   - в обычном Alpine:    @click="Livewire.dispatch('open-user-card', { userId: '...' })"
 *
 * Регистрируется один раз в общем layout (resources/views/layouts/public.blade.php).
 */
class UserCardModal extends Component
{
    public bool $isOpen = false;

    /** Подготовленные для отображения данные пользователя (null = окно закрыто). */
    public ?array $user = null;

    #[On('open-user-card')]
    public function open(string $userId): void
    {
        $user = User::find($userId);

        if (! $user instanceof User) {
            return;
        }

        $this->user = [
            'name' => $user->name,
            'avatar' => $user->photo_url ? asset('storage/'.$user->photo_url) : null,
            'number' => $user->formatted_external_id,
            'birthday' => $user->birth_date?->format('d.m.Y') ?? '—',
            'rank' => SportCategory::labelOrNone($user->sport_category),
            'about' => $user->about,
            'team' => $this->resolveMainTeam($user),
            'regattas' => $this->resolveParticipationCount($user),
            'rating' => $this->resolveRating($user),
        ];

        $this->isOpen = true;
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->user = null;
    }

    /**
     * Главная команда пользователя.
     *
     * Так как явного признака «основной» команды нет, выбираем по приоритету:
     * сначала команда, где пользователь капитан (организатор), затем по старшинству
     * роли, затем постоянное членство, затем самое раннее вступление.
     *
     * @return array{name: string, role: string}|null
     */
    private function resolveMainTeam(User $user): ?array
    {
        $rolePriority = [
            TeamMemberRole::Organizer->value => 0,
            TeamMemberRole::TeamAdmin->value => 1,
            TeamMemberRole::Member->value => 2,
        ];

        $membership = $user->teamMemberships()
            ->where('status', 'active')
            ->with('team')
            ->get()
            ->filter(fn ($m) => $m->team !== null)
            ->sortBy([
                fn ($m) => $rolePriority[$m->role] ?? 99,
                fn ($m) => $m->is_permanent ? 0 : 1,
                fn ($m) => $m->joined_at?->timestamp ?? PHP_INT_MAX,
            ])
            ->first();

        if (! $membership) {
            return null;
        }

        return [
            'name' => $membership->team->name,
            'role' => TeamMemberRole::tryFrom((string) $membership->role)?->label() ?? 'Участник',
        ];
    }

    /** Количество регат, в которых пользователь участвовал (одобренные заявки). */
    private function resolveParticipationCount(User $user): int
    {
        return RegattaEntry::query()
            ->where('status', 'approved')
            ->whereHas('crew.teamMember', fn ($q) => $q->where('user_id', $user->id))
            // Только активные и прошедшие регаты (уже начавшиеся), без предстоящих.
            ->whereHas('regatta', fn ($q) => $q->where('date_start', '<=', now()))
            ->distinct()
            ->count('regatta_id');
    }

    /**
     * Личный рейтинг за последний сезон.
     *
     * @return array{position: ?int, points: ?float, season: ?int}|null
     */
    private function resolveRating(User $user): ?array
    {
        $rating = PersonalRating::query()
            ->where('user_id', $user->id)
            ->with('season')
            ->get()
            ->sortByDesc(fn ($r) => $r->season?->year ?? 0)
            ->first();

        if (! $rating) {
            return null;
        }

        return [
            'position' => $rating->rank_position,
            'points' => (float) $rating->total_points,
            'season' => $rating->season?->year,
        ];
    }

    public function render()
    {
        return view('livewire.user-card-modal');
    }
}
