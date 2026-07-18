<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL не поддерживает добавление значения в ENUM через Schema Builder,
        // поэтому используем raw SQL.
        DB::statement("ALTER TABLE `users` MODIFY COLUMN `system_role` ENUM('user', 'admin', 'judge', 'secretary', 'accountant', 'developer_admin') NOT NULL DEFAULT 'user'");
    }

    public function down(): void
    {
        // Сначала переводим таких пользователей в обычные участники,
        // иначе MySQL обрежет значение в пустую строку.
        DB::table('users')
            ->where('system_role', 'developer_admin')
            ->update(['system_role' => 'user']);

        DB::statement("ALTER TABLE `users` MODIFY COLUMN `system_role` ENUM('user', 'admin', 'judge', 'secretary', 'accountant') NOT NULL DEFAULT 'user'");
    }
};
