<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Enums\TeamMemberInvitationStatus;
use App\Enums\TeamMemberRole;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TeamMemberInvitation;
use App\Models\User;
use App\Services\TeamRoleGuard;
use Illuminate\Validation\ValidationException;

final class InviteMemberFromOtherTeamAction
{
    /**
     * Создаёт запрос на приглашение постоянного участника из другой команды.
     * Требует роль Organizer или TeamAdmin в целевой команде.
     *
     * @param  Team   $team     Команда, в которую приглашают (новая главная команда)
     * @param  User   $user     Приглашаемый участник
     * @param  User   $actor    Капитан, отправляющий запрос
     * @param  string|null $message  Сопроводительное сообщение
     *
     * @throws ValidationException
     * @throws \App\Exceptions\InsufficientTeamRoleException
     */
    public function handle(Team $team, User $user, User $actor, ?string $message = null): TeamMemberInvitation
    {
        TeamRoleGuard::authorize($team, $actor, TeamMemberRole::ACTION_MANAGE_MEMBERS);

        // Текущая главная команда участника
        $currentPermanent = TeamMember::query()
            ->where('user_id', $user->id)
            ->where('is_permanent', true)
            ->first();

        if ($currentPermanent === null) {
            throw ValidationException::withMessages([
                'user_id' => "Участник «{$user->name}» не является постоянным участником ни одной команды.",
            ]);
        }

        if ((string) $currentPermanent->team_id === (string) $team->id) {
            throw ValidationException::withMessages([
                'user_id' => "Участник «{$user->name}» уже является постоянным участником этой команды.",
            ]);
        }

        if ($team->activeMembers()->count() >= Team::MAX_MEMBERS) {
            throw ValidationException::withMessages([
                'user_id' => 'Превышен лимит участников команды (максимум ' . Team::MAX_MEMBERS . ').',
            ]);
        }

        $duplicate = TeamMemberInvitation::query()
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->where('status', TeamMemberInvitationStatus::Pending->value)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'user_id' => 'Запрос этому участнику уже отправлен и ожидает ответа.',
            ]);
        }

        return TeamMemberInvitation::create([
            'team_id'      => $team->id,
            'user_id'      => $user->id,
            'from_team_id' => $currentPermanent->team_id,
            'requested_by' => $actor->id,
            'status'       => TeamMemberInvitationStatus::Pending,
            'message'      => $message,
        ]);
    }
}
