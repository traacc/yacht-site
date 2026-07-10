<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Колонка user_id была создана через foreignId() (bigint unsigned),
     * тогда как users.id — UUID (char(36)). Из-за несовпадения типов
     * привязка сессии к пользователю не работала. Пересоздаём колонку
     * как uuid; существующие значения — мусор (0/NULL), поэтому данные
     * не переносим: сессии сохраняются, привязка появится при следующей
     * записи сессии авторизованного пользователя.
     */
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->uuid('user_id')->nullable()->index()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->index()->after('id');
        });
    }
};
