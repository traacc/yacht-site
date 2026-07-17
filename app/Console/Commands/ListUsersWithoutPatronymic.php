<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class ListUsersWithoutPatronymic extends Command
{
    protected $signature = 'users:without-patronymic
                            {--count : Вывести только количество}
                            {--by-team : Разбить вывод на отдельные таблицы по постоянным командам}';

    protected $description = 'Выводит список пользователей без отчества в поле name (ФИО менее чем из трёх слов).';

    public function handle(): int
    {
        // Предварительный SQL-фильтр: в name меньше двух пробелов (грубая отсечка),
        // затем точная проверка на стороне PHP (учитывает повторяющиеся пробелы).
        $users = User::query()
            ->whereRaw("LENGTH(TRIM(name)) - LENGTH(REPLACE(TRIM(name), ' ', '')) < 2")
            ->with(['teamMemberships' => fn ($q) => $q->with('team')])
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) =>
                count(preg_split('/\s+/', trim((string) $user->name), -1, PREG_SPLIT_NO_EMPTY)) < 3
            )
            ->values();

        if ($this->option('count')) {
            $this->info((string) $users->count());

            return self::SUCCESS;
        }

        if ($users->isEmpty()) {
            $this->info('Пользователей без отчества не найдено.');

            return self::SUCCESS;
        }

        if ($this->option('by-team')) {
            $this->renderByTeam($users);

            $this->info("Всего пользователей: {$users->count()}.");

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'ФИО', 'Email', 'Дата рождения', 'Команды'],
            $users->map(fn (User $user) => [
                $user->id,
                $user->name,
                $user->email,
                $user->birth_date?->format('Y-m-d') ?? '—',
                $this->teamNamesFor($user)->implode(', ') ?: '—',
            ])->all()
        );

        $this->info("Всего: {$users->count()}.");

        return self::SUCCESS;
    }

    /**
     * Команды пользователя для отображения: приоритет — постоянные команды,
     * а если постоянных нет, показываем те команды, в которых он всё же состоит.
     */
    private function teamNamesFor(User $user): \Illuminate\Support\Collection
    {
        $permanent = $user->teamMemberships
            ->where('is_permanent', true)
            ->map(fn ($m) => $m->team?->name)
            ->filter()
            ->unique()
            ->values();

        if ($permanent->isNotEmpty()) {
            return $permanent;
        }

        return $user->teamMemberships
            ->map(fn ($m) => $m->team?->name)
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Вывести пользователей отдельными таблицами по постоянным командам.
     * Пользователь без постоянной команды попадает в группу «Без постоянной команды»,
     * состоящий в нескольких — отображается в каждой соответствующей команде.
     */
    private function renderByTeam(\Illuminate\Support\Collection $users): void
    {
        $noTeamLabel = 'Без команды';
        $groups = [];

        foreach ($users as $user) {
            $teamNames = $this->teamNamesFor($user);

            if ($teamNames->isEmpty()) {
                $teamNames = collect([$noTeamLabel]);
            }

            foreach ($teamNames as $teamName) {
                $groups[$teamName][] = $user;
            }
        }

        // Сортируем команды по названию, группу без команды выводим последней.
        uksort($groups, function (string $a, string $b) use ($noTeamLabel) {
            if ($a === $noTeamLabel) return 1;
            if ($b === $noTeamLabel) return -1;

            return strcasecmp($a, $b);
        });

        foreach ($groups as $teamName => $teamUsers) {
            $this->newLine();
            $this->line("<fg=yellow;options=bold>{$teamName}</> (" . count($teamUsers) . ')');

            $this->table(
                ['ID', 'ФИО', 'Email', 'Дата рождения'],
                collect($teamUsers)->map(fn (User $user) => [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->birth_date?->format('Y-m-d') ?? '—',
                ])->all()
            );
        }
    }
}
