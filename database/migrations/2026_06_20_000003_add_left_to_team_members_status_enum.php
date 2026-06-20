<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL не поддерживает добавление значения в ENUM через Schema Builder,
        // поэтому используем raw SQL.
        DB::statement("ALTER TABLE `team_members` MODIFY COLUMN `status` ENUM('invited', 'active', 'declined', 'left') NOT NULL DEFAULT 'invited'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `team_members` MODIFY COLUMN `status` ENUM('invited', 'active', 'declined') NOT NULL DEFAULT 'invited'");
    }
};
