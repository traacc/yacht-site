<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TeamMemberRole;
use App\Exceptions\InsufficientTeamRoleException;
use App\Models\Team;
use App\Models\User;

/**
 * Централизованная проверка прав участника команды.
 *
 * Использование:
 *   TeamRoleGuard::authorize($team, $user, TeamMemberRole::ACTION_MANAGE_MEMBERS);
 *   TeamRoleGuard::check($team, $user, TeamMemberRole::ACTION_SUBMIT_ENTRY); // bool
 */
final class TeamRoleGuard
{
    /**
     * Возвращает роль пользователя в команде или null, если он не является участником.
     */
    public static function roleOf(Team $team, User $user): ?TeamMemberRole
    {
        $roleValue = $team->teamMembers()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->value('role');

        if ($roleValue === null) {
            return null;
        }

        return TeamMemberRole::tryFrom($roleValue);
    }

    /**
     * Проверяет, может ли пользователь выполнить действие в контексте команды.
     */
    public static function check(Team $team, User $user, string $action): bool
    {
        $role = self::roleOf($team, $user);

        return $role !== null && $role->canPerform($action);
    }

    /**
     * Авторизует действие: выбрасывает InsufficientTeamRoleException при недостаточных правах.
     *
     * @throws InsufficientTeamRoleException
     */
    public static function authorize(Team $team, User $user, string $action): void
    {
        $role = self::roleOf($team, $user);

        if ($role === null || ! $role->canPerform($action)) {
            throw new InsufficientTeamRoleException($action, $role);
        }
    }
}
