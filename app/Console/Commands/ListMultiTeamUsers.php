<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ListMultiTeamUsers extends Command
{
    protected $signature = 'users:multi-team
                            {--min=2 : Минимальное число команд}
                            {--status= : Учитывать только участие с этим статусом (например active)}';

    protected $description = 'Выводит список пользователей, состоящих в двух и более командах.';

    public function handle(): int
    {
        $min    = (int) $this->option('min');
        $status = $this->option('status');

        $users = User::query()
            ->withCount(['teams' => function ($query) use ($status) {
                if ($status !== null && $status !== '') {
                    $query->where('team_members.status', $status);
                }
            }])
            ->having('teams_count', '>=', $min)
            ->orderByDesc('teams_count')
            ->get();

        if ($users->isEmpty()) {
            $this->info("Пользователей, состоящих в {$min}+ командах, не найдено.");

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Имя', 'Email', 'Команд'],
            $users->map(fn (User $user) => [
                $user->id,
                $user->name,
                $user->email,
                $user->teams_count,
            ])->all()
        );

        $this->info("Всего: {$users->count()}.");

        return self::SUCCESS;
    }
}
