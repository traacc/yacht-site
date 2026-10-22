<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Журнал замены одного пользователя другим в заявках и командах
 * (App\Actions\User\ReplaceUserAction).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_replacements', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Заменённый (после замены обычно мягко удалён) и тот, на кого заменили.
            $table->foreignUuid('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('to_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Снапшоты ФИО: журнал читается и после физического удаления учёток.
            $table->string('from_name');
            $table->string('to_name');

            $table->boolean('from_deleted')->default(false);

            // Что изменено: заявки, команды, протоколы, сезоны, причины не удалять учётку.
            $table->json('summary');

            $table->foreignUuid('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('performed_by_name')->nullable();

            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_replacements');
    }
};
