<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_registry_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Платёж. Nullable + nullOnDelete: запись журнала переживает
            // физическое удаление реестра (ТЗ требует полную историю).
            $table->foreignUuid('payment_registry_id')->nullable()
                ->constrained('payment_registries')->nullOnDelete();

            // Снапшот платежа на момент события — журнал читается
            // даже если сам платёж удалён.
            $table->string('registry_name');
            $table->decimal('registry_amount', 12, 2)->nullable();

            // Кто выполнил действие. Null — системное изменение
            // (вебхук эквайринга, консольная команда, публичная форма).
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Снапшот ФИО актора: пользователя могут удалить.
            $table->string('actor_name')->nullable();

            // App\Enums\PaymentRegistryLogEvent (строка + PHP-каст, не БД-ENUM).
            $table->string('event');

            // Изменённые поля: [{field, label, old, new, old_label, new_label}, ...]
            // Имя changed_fields, а не changes: последнее конфликтует
            // с внутренним свойством Eloquent\Model::$changes.
            $table->json('changed_fields')->nullable();

            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['payment_registry_id', 'created_at'], 'payment_registry_logs_registry_created_index');
            $table->index(['user_id', 'created_at'], 'payment_registry_logs_user_created_index');
            $table->index(['event', 'created_at'], 'payment_registry_logs_event_created_index');
            // Отдельный индекс под сортировку по умолчанию и фильтр по периоду.
            $table->index('created_at', 'payment_registry_logs_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_registry_logs');
    }
};
