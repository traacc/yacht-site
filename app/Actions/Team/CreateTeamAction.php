<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;

final class CreateTeamAction
{
    /**
     * Создаёт команду и автоматически добавляет создателя как организатора (organizer)
     * с активным статусом в таблицу team_members.
     *
     * @param  array<string, mixed>  $data  Данные команды (name, description, organizer_id, ...)
     * @param  User  $organizer  Пользователь, создающий команду
     */
    public function handle(array $data, User $organizer): Team
    {
        $team = Team::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'organizer_id' => $organizer->id,
            'is_archived' => $data['is_archived'] ?? false,
            'approval_status' => $data['approval_status'] ?? 'pending',
        ]);

        // Автоматически добавляем создателя как организатора команды
        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $organizer->id,
            'role' => 'organizer',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return $team;
    }
}
