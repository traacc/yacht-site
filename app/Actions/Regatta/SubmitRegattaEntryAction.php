<?php

declare(strict_types=1);

namespace App\Actions\Regatta;

use App\Enums\TeamMemberRole;
use App\Exceptions\InsufficientTeamRoleException;
use App\Models\Regatta;
use App\Models\RegattaEntry;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Yacht;
use App\Services\TeamRoleGuard;
use Illuminate\Validation\ValidationException;

final class SubmitRegattaEntryAction
{
    /**
     * Подать заявку команды на участие в регате.
     * Требует роль Organizer или TeamAdmin у вызывающего пользователя.
     *
     * @param array<string, 'main'|'reserve'|'captain'> $crew  team_member_id => role
     *
     * @throws InsufficientTeamRoleException
     * @throws ValidationException
     */
    public function handle(Regatta $regatta, Team $team, Yacht $yacht, User $actor, array $crew = []): RegattaEntry
    {
        TeamRoleGuard::authorize($team, $actor, TeamMemberRole::ACTION_SUBMIT_ENTRY);

        // Проверяем, что яхта принадлежит пользователю
        /*
        if ($yacht->user_id !== $actor->id) {
            throw ValidationException::withMessages([
                'yachtId' => 'Выбранная яхта не принадлежит вам.',
            ]);
        }
        */
        
        // Проверяем, не подана ли уже заявка от этой команды
        if ($regatta->hasTeam($team)) {
            throw ValidationException::withMessages([
                'teamId' => 'Заявка от этой команды уже подана.',
            ]);
        }

        // Проверяем занятость яхты в период регаты
        if ($yacht->isBusyDuring(
            $regatta->date_start->format('Y-m-d'),
            $regatta->date_end->format('Y-m-d')
        )) {
            throw ValidationException::withMessages([
                'yachtId' => 'Выбранная яхта уже занята в этот период.',
            ]);
        }

        $entry = RegattaEntry::create([
            'regatta_id'   => $regatta->id,
            'team_id'      => $team->id,
            'yacht_id'     => $yacht->id,
            'status'       => 'pending',
            'submitted_at' => now(),
        ]);

        // Сохраняем экипаж
        if ($crew !== []) {
            // Валидируем, что все team_member_id принадлежат этой команде и активны
            $validMemberIds = $team->members()
                ->wherePivot('status', 'active')
                ->pluck('team_members.id')
                ->toArray();

            foreach ($crew as $memberId => $role) {
                // Пропускаем участников без выбранной роли
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

        return $entry;
    }
}
