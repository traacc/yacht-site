<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Синхронизирует значения enum role в team_members с TeamMemberRole:
 *   'admin' → 'team_admin'
 *
 * MySQL: меняет тип колонки через MODIFY COLUMN.
 * SQLite: пересоздаёт таблицу (нативный ALTER не поддерживается).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Переименовываем существующие значения 'admin' → 'team_admin'
        DB::table('team_members')
            ->where('role', 'admin')
            ->update(['role' => 'team_admin']);

        Schema::table('team_members', function (Blueprint $table) {
            $table->enum('role', ['organizer', 'team_admin', 'member'])
                ->default('member')
                ->change();
        });
    }

    public function down(): void
    {
        DB::table('team_members')
            ->where('role', 'team_admin')
            ->update(['role' => 'admin']);

        Schema::table('team_members', function (Blueprint $table) {
            $table->enum('role', ['organizer', 'admin', 'member'])
                ->default('member')
                ->change();
        });
    }
};
