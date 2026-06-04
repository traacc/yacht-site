<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class UpdateUserNames extends Command
{
    protected $signature = 'users:update-names';

    protected $description = 'Синхронизирует поле name из first_name, last_name, patronymic для всех пользователей.';

    public function handle(): int
    {
        $updated = 0;
        $errors  = 0;

        $this->info('Начинаю синхронизацию name...');

        User::query()
            ->whereNotNull('last_name')
            ->whereNotNull('first_name')
            ->chunk(200, function ($users) use (&$updated, &$errors) {
                foreach ($users as $user) {
                    $expectedName = trim(
                        ($user->last_name ?? '') . ' ' .
                        ($user->first_name ?? '') . ' ' .
                        ($user->patronymic ?? '')
                    );

                    if ($user->name !== $expectedName) {
                        $user->name = $expectedName;

                        if ($user->saveQuietly()) {
                            $updated++;
                        } else {
                            $errors++;
                        }
                    }
                }
            });

        /*
            // Пользователи, у которых нет last_name или first_name — сбросить name
            User::query()
                ->whereNull('last_name')
                ->orWhereNull('first_name')
                ->whereNotNull('name')
                ->chunk(200, function ($users) use (&$updated, &$errors) {
                    foreach ($users as $user) {
                        $user->name = null;

                        if ($user->saveQuietly()) {
                            $updated++;
                        } else {
                            $errors++;
                        }
                    }
                });
        */
        $this->info("Синхронизация завершена. Обновлено: {$updated}, ошибок: {$errors}.");

        return self::SUCCESS;
    }
}