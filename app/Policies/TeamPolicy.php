<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

final class TeamPolicy
{
    /**
     * Только организатор (капитан) команды может добавлять участников.
     */
    public function addMembers(User $user, Team $team): bool
    {
        return $team->organizer_id === $user->id;
    }
}
