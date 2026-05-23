<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class AddMembersToTeamAction
{
    /**
     * Добавляет свободных участников в команду капитана.
     *
     * @param  Team  $team       Команда капитана
     * @param  array<string>  $userIds  UUID пользователей для добавления
     * @param  User  $captain    Пользователь-капитан (организатор команды)
     *
     * @throws ValidationException
     */
    public function handle(Team $team, array $userIds, User $captain): Collection
    {
        // Только организатор команды может добавлять участников
        if ($team->organizer_id !== $captain->id) {
            throw ValidationException::withMessages([
                'team' => 'Вы не являетесь капитаном этой команды.',
            ]);
        }

        $currentCount = $team->activeMembers()->count();
        $toAdd        = count($userIds);

        if ($currentCount + $toAdd > Team::MAX_MEMBERS) {
            $available = Team::MAX_MEMBERS - $currentCount;
            throw ValidationException::withMessages([
                'user_ids' => "Превышен лимит участников. Можно добавить ещё {$available} чел. (максимум " . Team::MAX_MEMBERS . ').',
            ]);
        }

        $added = collect();

        foreach ($userIds as $userId) {
            $user = User::find($userId);

            if (!$user) {
                continue;
            }

            // Проверяем, что пользователь свободен (не состоит ни в одной команде)
            $alreadyInTeam = TeamMember::where('user_id', $userId)
                ->where('status', 'active')
                ->exists();

            if ($alreadyInTeam) {
                throw ValidationException::withMessages([
                    'user_ids' => "Участник «{$user->full_name}» уже состоит в другой команде.",
                ]);
            }

            // Проверяем, нет ли уже записи в этой команде
            $existingMembership = TeamMember::where('team_id', $team->id)
                ->where('user_id', $userId)
                ->first();

            if ($existingMembership) {
                throw ValidationException::withMessages([
                    'user_ids' => "Участник «{$user->full_name}» уже добавлен в эту команду.",
                ]);
            }

            $member = TeamMember::create([
                'team_id'   => $team->id,
                'user_id'   => $userId,
                'role'      => 'member',
                'status'    => 'active',
                'joined_at' => now(),
            ]);

            $added->push($member);
        }

        return $added;
    }
}
