<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class ListUsersWithoutPatronymic extends Command
{
    protected $signature = 'users:without-patronymic
                            {--count : Вывести только количество}';

    protected $description = 'Выводит список пользователей без отчества в поле name (ФИО менее чем из трёх слов).';

    public function handle(): int
    {
        // Предварительный SQL-фильтр: в name меньше двух пробелов (грубая отсечка),
        // затем точная проверка на стороне PHP (учитывает повторяющиеся пробелы).
        $users = User::query()
            ->whereRaw("LENGTH(TRIM(name)) - LENGTH(REPLACE(TRIM(name), ' ', '')) < 2")
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

        $this->table(
            ['ID', 'ФИО', 'Email', 'Дата рождения'],
            $users->map(fn (User $user) => [
                $user->id,
                $user->name,
                $user->email,
                $user->birth_date?->format('Y-m-d') ?? '—',
            ])->all()
        );

        $this->info("Всего: {$users->count()}.");

        return self::SUCCESS;
    }
}
