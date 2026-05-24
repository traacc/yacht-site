<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\TeamMemberRole;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamRoleGuard;

final class TeamPolicy
{
    /**
     * Добавление / удаление участников команды.
     */
    public function addMembers(User $user, Team $team): bool
    {
        return TeamRoleGuard::check($team, $user, TeamMemberRole::ACTION_MANAGE_MEMBERS);
    }

    /**
     * Подача заявки на регату от имени команды.
     */
    public function submitEntry(User $user, Team $team): bool
    {
        return TeamRoleGuard::check($team, $user, TeamMemberRole::ACTION_SUBMIT_ENTRY);
    }

    /**
     * Редактирование данных команды.
     */
    public function editTeam(User $user, Team $team): bool
    {
        return TeamRoleGuard::check($team, $user, TeamMemberRole::ACTION_EDIT_TEAM);
    }

    /**
     * Архивирование / восстановление команды.
     */
    public function archiveTeam(User $user, Team $team): bool
    {
        return TeamRoleGuard::check($team, $user, TeamMemberRole::ACTION_ARCHIVE_TEAM);
    }
}
