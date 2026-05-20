<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Seeder;

class TeamMemberSeeder extends Seeder
{
    public function run(): void
    {
        $teams = Team::all();
        $users = User::all();

        if ($teams->isEmpty() || $users->isEmpty()) {
            return;
        }

        $teams->each(function (Team $team) use ($users) {
            // Берем случайное количество пользователей для команды
            $members = $users->random(rand(1, min(5, $users->count())));

            foreach ($members as $user) {
                // Проверяем уникальность пары team_id и user_id
                if (!TeamMember::where('team_id', $team->id)->where('user_id', $user->id)->exists()) {
                    TeamMember::factory()
                        ->for($team)
                        ->for($user)
                        ->create();
                }
            }
        });
    }
}
