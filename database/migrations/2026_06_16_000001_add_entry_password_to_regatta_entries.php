<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regatta_entries', function (Blueprint $table) {
            // Специальный пароль заявки: позволяет редактировать заявку
            // на странице регаты без входа в аккаунт. Хранится хешированным.
            $table->string('entry_password')->nullable()->after('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('regatta_entries', function (Blueprint $table) {
            $table->dropColumn('entry_password');
        });
    }
};
