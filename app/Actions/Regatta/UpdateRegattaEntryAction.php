<?php

declare(strict_types=1);

namespace App\Actions\Regatta;

use App\Enums\TeamMemberRole;
use App\Exceptions\InsufficientTeamRoleException;
use App\Models\Regatta;
use App\Models\RegattaEntry;
use App\Models\Team;
use App\Models\User;
use App\Models\Yacht;
use App\Services\TeamRoleGuard;
use Illuminate\Validation\ValidationException;

final class UpdateRegattaEntryAction
{
    /**
     * Обновить заявку команды на участие в регате.
     * Требует роль Organizer или TeamAdmin у вызывающего пользователя.
     *
     * @param array<string, 'main'|'reserve'> $crew  team_member_id => role
     *
     * @throws InsufficientTeamRoleException
     * @throws ValidationException
     */
    public function handle(RegattaEntry $entry, Team $team, Yacht $yacht, User $actor, array $crew = []): RegattaEntry
    {
        TeamRoleGuard::authorize($team, $actor, TeamMemberRole::ACTION_SUBMIT_ENTRY);

        // Редактировать можно только pending-заявки
        if (! $entry->isPending()) {
            throw ValidationException::withMessages([
                'general' => 'Нельзя редактировать утверждённую или отклонённую заявку.',
            ]);
        }

        // Проверяем занятость яхты в период регаты
        $regatta = $entry->regatta;
        if ($yacht->isBusyDuring(
            $regatta->date_start->format('Y-m-d'),
            $regatta->date_end->format('Y-m-d'),
        )) {
            throw ValidationException::withMessages([
                'yachtId' => 'Выбранная яхта уже занята в этот период.',
            ]);
        }

        $entry->update([
            'team_id'  => $team->id,
            'yacht_id' => $yacht->id,
        ]);

        // Обновляем экипаж: удаляем старый, создаём новый
        $entry->crew()->delete();

        if ($crew !== []) {
            $validMemberIds = $team->members()
                ->wherePivot('status', 'active')
                ->pluck('team_members.id')
                ->toArray();

            foreach ($crew as $memberId => $role) {
                if ($role === '' || $role === null) {
                    continue;
                }

                if (! in_array($memberId, $validMemberIds, true)) {
                    throw ValidationException::withMessages([
                        'crew' => "Участник {$memberId} не состоит в команде или не активен.",
                    ]);
                }

                $entry->crew()->create([
                    'team_member_id' => $memberId,
                    'role'           => $role,
                ]);
            }
        }

        return $entry->fresh();
    }
}
