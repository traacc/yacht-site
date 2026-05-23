<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class CreateTeamAction
{
    /**
     * Создаёт команду, автоматически добавляет создателя как организатора,
     * и при наличии initial_member_ids — добавляет свободных участников.
     *
     * @param  array<string, mixed>  $data       Данные команды (name, description, organizer_id, ...)
     * @param  User                  $organizer  Пользователь, создающий команду
     *
     * @throws ValidationException
     */
    public function handle(array $data, User $organizer): Team
    {
        $team = Team::create([
            'name'            => $data['name'],
            'description'     => $data['description'] ?? null,
            'organizer_id'    => $organizer->id,
            'default_yacht_id'=> $data['default_yacht_id'] ?? null,
            'is_archived'     => $data['is_archived'] ?? false,
            'approval_status' => $data['approval_status'] ?? 'pending',
            'picture'         => $data['picture'] ?? null,
        ]);

        // Автоматически добавляем создателя как организатора команды
        TeamMember::create([
            'team_id'   => $team->id,
            'user_id'   => $organizer->id,
            'role'      => 'organizer',
            'status'    => 'active',
            'joined_at' => now(),
        ]);

        // Добавляем начальных участников, если они были выбраны в форме
        $initialMemberIds = $data['initial_member_ids'] ?? [];

        if (!empty($initialMemberIds)) {
            $toAdd = count($initialMemberIds);

            if ($toAdd > Team::MAX_MEMBERS - 1) {
                throw ValidationException::withMessages([
                    'initial_member_ids' => 'Превышен лимит участников. Максимум ' . (Team::MAX_MEMBERS - 1) . ' чел. (одно место занимает капитан).',
                ]);
            }

            foreach ($initialMemberIds as $userId) {
                // Пропускаем самого организатора
                if ($userId === $organizer->id) {
                    continue;
                }

                $user = User::find($userId);
                if (!$user) {
                    continue;
                }

                // Проверяем, что пользователь свободен
                $alreadyInTeam = TeamMember::where('user_id', $userId)
                    ->where('status', 'active')
                    ->exists();

                if ($alreadyInTeam) {
                    throw ValidationException::withMessages([
                        'initial_member_ids' => "Участник «{$user->full_name}» уже состоит в другой команде.",
                    ]);
                }

                TeamMember::create([
                    'team_id'   => $team->id,
                    'user_id'   => $userId,
                    'role'      => 'member',
                    'status'    => 'active',
                    'joined_at' => now(),
                ]);
            }
        }

        return $team;
    }
}
