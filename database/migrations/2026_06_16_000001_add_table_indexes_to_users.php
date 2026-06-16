<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Покрывает дефолтный запрос списка: WHERE deleted_at IS NULL ORDER BY name
            // (фильтрация по soft-delete + сортировка по умолчанию без filesort).
            $table->index(['deleted_at', 'name'], 'users_deleted_at_name_index');
            // Сортировка по дате регистрации ("Рег.").
            $table->index('created_at', 'users_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_deleted_at_name_index');
            $table->dropIndex('users_created_at_index');
        });
    }
};
