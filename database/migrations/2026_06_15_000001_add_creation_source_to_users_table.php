<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Источник создания пользователя:
            // registration  — самостоятельная регистрация
            // admin         — создан из админки
            // quick_request — создан из быстрой заявки
            $table->enum('creation_source', ['registration', 'admin', 'quick_request', 'unknown'])
                  ->default('unknown')
                  ->after('system_role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('creation_source');
        });
    }
};
